<?php

declare(strict_types=1);

namespace Kurama;

use InvalidArgumentException;
use Kurama\Bridge\PsrBridge;
use Kurama\Exceptions\HttpException;
use Kurama\Exceptions\MethodNotAllowedException;
use Kurama\Exceptions\RouteNotFoundException;
use Kurama\Console\Console;
use Kurama\Contracts\ServiceProviderInterface;
use Kurama\Events\Dispatcher;
use Kurama\Exceptions\ValidationException;
use Throwable;

/**
 * Optional application layer on top of the router.
 *
 * It owns the container, configuration, environment, middleware pipeline,
 * request/response conversion and error rendering. The Router stays independent.
 */
class Application
{
    public readonly Router $router;

    public readonly Template $template;

    public readonly Config $configuration;

    public readonly Environment $environment;

    public readonly Container $container;

    public readonly Session $session;

    public readonly Csrf $csrf;

    public readonly Logger $logger;

    public readonly Console $console;

    private readonly string $basePath;

    /** @var list<callable> */
    private array $middleware = [];

    /** @var list<callable> */
    private array $errorHandlers = [];

    /** @var array<int, callable> */
    private array $handlerCache = [];

    /** @var array<int, \Closure> */
    private array $actionCache = [];

    /** @var array<int, list<callable>> */
    private array $pipelineCache = [];

    /** @var list<ServiceProviderInterface> */
    private array $providers = [];

    /** @var array<int, ServiceProviderInterface> */
    private array $orderedProviders = [];

    private bool $providersRegistered = false;

    private bool $booted = false;

    public function __construct(
        array|Config $config = [],
        ?Router $router = null,
        ?string $basePath = null,
        ?Container $container = null,
    ) {
        $this->router = $router ?? new Router();
        $this->container = $container ?? new Container();
        $base = $basePath ?? dirname(__DIR__);
        $this->basePath = $base;

        $configFile = $base . '/config/app.php';
        $fileConfig = is_file($configFile) ? require $configFile : [];

        $this->configuration = new Config(is_array($fileConfig) ? $fileConfig : []);
        $this->configuration->load($config instanceof Config ? $config->all() : $config);

        $viewsPath = $this->configuration->get('views.path', $base . '/views');
        $this->template = new Template((string) $viewsPath);

        $this->environment = new Environment();
        $envPath = (string) $this->configuration->get('env.path', $base . '/.env');
        if ($this->configuration->get('env.enabled', true) && is_file($envPath)) {
            $this->environment->load($envPath);
        }

        if (($timezone = $this->configuration->get('timezone')) !== null) {
            date_default_timezone_set((string) $timezone);
        }

        $this->session = new Session(
            $this->configuration->get('session.name'),
            (bool) $this->configuration->get('session.native', true),
        );
        $this->csrf = new Csrf($this->session);
        $logPath = (string) $this->configuration->get('log.path', $base . '/storage/logs/app.log');
        $this->logger = new Logger(
            $logPath,
            (string) $this->configuration->get('log.level', 'debug'),
            (int) $this->configuration->get('log.max_bytes', 0),
        );

        $this->registerCoreBindings();
        $this->console = new Console($this);
        $this->container->instance(Console::class, $this->console);
        $this->container->instance('console', $this->console);

        $providers = (array) $this->configuration->get('providers', []);
        if ((bool) $this->configuration->get('discovery.enabled', true)) {
            $providers = array_merge($providers, ProviderDiscovery::discover($base));
        }

        foreach (array_values(array_unique(array_filter($providers, 'is_string'))) as $provider) {
            $this->register($provider);
        }
    }

    public function get(string $pattern, mixed $handler): Route
    {
        return $this->router->get($pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): Route
    {
        return $this->router->post($pattern, $handler);
    }

    public function put(string $pattern, mixed $handler): Route
    {
        return $this->router->put($pattern, $handler);
    }

    public function patch(string $pattern, mixed $handler): Route
    {
        return $this->router->patch($pattern, $handler);
    }

    public function delete(string $pattern, mixed $handler): Route
    {
        return $this->router->delete($pattern, $handler);
    }

    public function options(string $pattern, mixed $handler): Route
    {
        return $this->router->options($pattern, $handler);
    }

    public function head(string $pattern, mixed $handler): Route
    {
        return $this->router->head($pattern, $handler);
    }

    public function any(string $pattern, mixed $handler): Route
    {
        return $this->router->any($pattern, $handler);
    }

    /** @param list<string>|string $methods */
    public function match(array|string $methods, string $pattern, mixed $handler): Route
    {
        return $this->router->match($methods, $pattern, $handler);
    }

    public function pattern(string $name, string $regex): self
    {
        $this->router->pattern($name, $regex);

        return $this;
    }

    /**
     * @param array{prefix?: string, middleware?: list<callable>} $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->router->group($attributes, function () use ($callback): void {
            $callback($this);
        });
    }

    public function middleware(callable ...$middleware): self
    {
        array_push($this->middleware, ...$middleware);
        $this->pipelineCache = [];

        return $this;
    }

    /** Alias for Slim-style `$app->add($middleware)`. */
    public function add(callable ...$middleware): self
    {
        return $this->middleware(...$middleware);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->configuration->get($key, $default);
    }

    public function env(string $key, mixed $default = null): mixed
    {
        return $this->environment->get($key, $default);
    }

    public function events(): Dispatcher
    {
        return $this->container->get(Dispatcher::class);
    }

    public function listen(string $event, callable $listener, int $priority = 0): self
    {
        $this->events()->listen($event, $listener, $priority);

        return $this;
    }

    public function queueEvent(string $event, mixed ...$payload): self
    {
        $this->events()->queue($event, ...$payload);

        return $this;
    }

    public function flushEvents(): void
    {
        $this->events()->flush();
    }

    /** @param class-string<ServiceProviderInterface>|ServiceProviderInterface $provider */
    public function register(string|ServiceProviderInterface $provider): self
    {
        $instance = is_string($provider) ? $this->container->make($provider) : $provider;
        if (!$instance instanceof ServiceProviderInterface) {
            throw new InvalidArgumentException('Invalid service provider given.');
        }

        $this->providers[] = $instance;
        $this->providersRegistered = false;

        return $this;
    }

    /** Resolve container ids exposed by providers under a tag. */
    public function tagged(string $tag): array
    {
        $items = [];
        foreach ($this->providers as $provider) {
            if (!method_exists($provider, 'tags')) {
                continue;
            }
            foreach ((array) ($provider->tags()[$tag] ?? []) as $id) {
                if ($this->container->has($id)) {
                    $items[$id] = $this->container->get($id);
                }
            }
        }

        return array_values($items);
    }

    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        if (!$this->providersRegistered) {
            $this->orderedProviders = $this->orderProviders();
            foreach ($this->orderedProviders as $provider) {
                $this->events()->dispatch('app.registering', $this, $provider);
                $provider->register($this);
            }
            $this->providersRegistered = true;
        }

        $this->events()->dispatch('app.booting', $this);
        foreach ($this->orderedProviders as $provider) {
            $provider->boot($this);
        }
        $this->booted = true;
        $this->events()->dispatch('app.booted', $this);

        return $this;
    }

    /** @return array<int, ServiceProviderInterface> */
    private function orderProviders(): array
    {
        $byClass = [];
        foreach ($this->providers as $provider) {
            $byClass[get_class($provider)] ??= $provider;
        }

        $ordered = [];
        $visiting = [];
        $done = [];

        $visit = function (string $class) use (&$visit, &$ordered, &$visiting, &$done, $byClass): void {
            if (isset($done[$class]) || !isset($byClass[$class])) {
                return;
            }
            if (isset($visiting[$class])) {
                throw new InvalidArgumentException("Circular provider dependency [{$class}].");
            }

            $visiting[$class] = true;
            $provider = $byClass[$class];
            $requires = method_exists($provider, 'requires') ? $provider->requires() : [];
            foreach ((array) $requires as $dependency) {
                $visit((string) $dependency);
            }
            unset($visiting[$class]);
            $done[$class] = true;

            foreach ($this->providers as $candidate) {
                if (get_class($candidate) === $class) {
                    $ordered[spl_object_id($candidate)] = $candidate;
                }
            }
        };

        foreach ($this->providers as $provider) {
            $visit(get_class($provider));
        }

        return array_values($ordered);
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    /** Validate data and return only validated fields. */
    public function validate(array $data, array $rules): array
    {
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }

        return $validator->validated();
    }

    public function url(string $name, array $params = []): string
    {
        return $this->router->url($name, $params);
    }

    public function urlFor(string $target, array $params = []): string
    {
        return $this->url($target, $params);
    }

    /** @param array<string, mixed> $params */
    public function urlForHandler(mixed $handler, array $params = []): string
    {
        return $this->router->urlForHandler($handler, $params);
    }

    private function cachePath(): string
    {
        return (string) $this->config(
            'route.cache.path',
            $this->basePath . '/storage/cache/routes.php',
        );
    }

    private function closureResolver(): ?callable
    {
        $resolver = $this->config('route.cache.closure_resolver');

        return is_callable($resolver) ? $resolver : null;
    }

    /** @return list<string> */
    private function cacheSources(): array
    {
        $sources = $this->config('route.cache.sources', [
            $this->basePath . '/config',
            $this->basePath . '/routes',
        ]);

        return is_array($sources) ? array_values(array_filter($sources, 'is_string')) : [];
    }

    /** Load cached route definitions. Returns false when off, missing or stale. */
    public function routesFromCache(): bool
    {
        if (!$this->config('route.cache.enabled', false) || !is_file($this->cachePath())) {
            return false;
        }

        if (!RouteCache::isFresh($this->cachePath(), $this->cacheSources())) {
            return false;
        }

        RouteCache::load(
            $this->cachePath(),
            $this->router,
            $this->container,
            $this->closureResolver(),
        );

        return true;
    }

    /** Write the current routes to the cache file. */
    public function cacheRoutes(): void
    {
        RouteCache::write(
            $this->router,
            $this->cachePath(),
            $this->container,
            $this->cacheSources(),
            $this->closureResolver(),
        );
    }

    /** Load from cache, or run the callback and write a fresh cache. */
    public function loadRoutes(callable $callback): void
    {
        if ($this->routesFromCache()) {
            return;
        }

        $callback($this);

        if ($this->config('route.cache.enabled', false)) {
            $this->cacheRoutes();
        }
    }

    /** Render a template into an HTML response. */
    public function render(
        string $template,
        array $data = [],
        int $status = 200,
        ?string $layout = null,
    ): Response {
        return Response::html($this->template->render($template, $data, $layout), $status);
    }

    /** Add an error handler to the chain: fn (Throwable, Request, callable $next): Response */
    public function addErrorHandler(callable $handler): self
    {
        $this->errorHandlers[] = $handler;

        return $this;
    }

    /** Replace the whole chain with a single handler (legacy signature). */
    public function setErrorHandler(callable $handler): self
    {
        $this->errorHandlers = [];

        return $this->addErrorHandler(static function (Throwable $e, Request $request, callable $next) use ($handler) {
            $result = $handler($e, $request);

            return $result instanceof Response ? $result : Response::make($result);
        });
    }

    public function handle(Request $request): Response
    {
        $this->boot();
        $this->events()->dispatch('request.received', $this, $request);

        try {
            $result = $this->router->resolve($request->method(), $request->path());

            if ($result === null) {
                throw new RouteNotFoundException($request->method(), $request->path());
            }

            $route = $result['route'];
            $parameters = $result['parameters'];
            if ($parameters !== []) {
                $request = $request->withParams($parameters);
            }

            $initialResponse = Response::html('');
            $this->container->instance(Request::class, $request);
            $this->container->instance('request', $request);
            $this->container->instance(Response::class, $initialResponse);
            $this->container->instance('response', $initialResponse);

            $routeId = spl_object_id($route);
            $handler = $this->handlerCache[$routeId] ??= $this->resolveHandler($route->handler());
            $action = $this->actionCache[$routeId] ??= $this->buildAction($handler);
            $destination = static fn (Request $current): mixed => $action($current, $parameters, $initialResponse);

            $middleware = $this->pipelineCache[$routeId] ??= array_reverse(
                array_merge($this->middleware, $route->middlewares()),
            );
            $next = $destination;
            foreach ($middleware as $item) {
                $previous = $next;
                $next = static fn (Request $current): mixed => $item($current, $previous);
            }

            $response = $next($request);
        } catch (MethodNotAllowedException $exception) {
            if ($request->isMethod('OPTIONS') && $this->config('options.auto', true)) {
                $allowed = $exception->allowedMethods();
                if (!in_array('OPTIONS', $allowed, true)) {
                    $allowed[] = 'OPTIONS';
                }
                if (in_array('GET', $allowed, true) && !in_array('HEAD', $allowed, true)) {
                    $allowed[] = 'HEAD';
                }
                $response = Response::make('', 204, ['Allow' => implode(', ', $allowed)]);
            } else {
                $response = $this->renderError($exception, $request);
            }
        } catch (Throwable $exception) {
            $response = $this->renderError($exception, $request);
        }

        $response = $response instanceof Response ? $response : Response::make($response);
        $this->events()->dispatch('response.ready', $this, $response, $request);

        return $response;
    }

    /** Reset per-request state before handling another request in a worker. */
    public function prepareForRequest(): void
    {
        $this->session->reset();
        $this->router->forgetMemo();
    }

    public function run(?Request $request = null): void
    {
        $response = $this->handle($request ?? Request::capture());
        $response->send();
        $this->events()->dispatch('app.terminated', $this, $response);
        $this->flushEvents();
    }

    /** Run the CLI kernel and return an exit code. */
    public function runConsole(?array $argv = null): int
    {
        return $this->console->run($argv);
    }

    /** Handle a PSR-7-like request and return a PSR-7-like response. */
    public function handlePsr(object $psrRequest, object $responseFactory): object
    {
        $response = $this->handle(PsrBridge::toKuramaRequest($psrRequest));

        return PsrBridge::toPsrResponse($response, $responseFactory);
    }

    private function registerCoreBindings(): void
    {
        $this->container->instance(Container::class, $this->container);
        $this->container->instance('container', $this->container);
        $this->container->instance(self::class, $this);
        $this->container->instance('app', $this);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance('router', $this->router);
        $this->container->instance(Template::class, $this->template);
        $this->container->instance('template', $this->template);
        $this->container->instance(Config::class, $this->configuration);
        $this->container->instance('config', $this->configuration);
        $this->container->instance(Environment::class, $this->environment);
        $this->container->instance('env', $this->environment);
        $this->container->instance(Session::class, $this->session);
        $this->container->instance('session', $this->session);
        $this->container->instance(Csrf::class, $this->csrf);
        $this->container->instance('csrf', $this->csrf);
        $this->container->instance(Logger::class, $this->logger);
        $this->container->instance('logger', $this->logger);

        $events = new Dispatcher();
        $events->defer((bool) $this->configuration->get('events.defer', false));
        $this->container->instance(Dispatcher::class, $events);
        $this->container->instance('events', $events);
    }

    /** Build a reusable action runner for a resolved handler. */
    private function buildAction(callable $handler): \Closure
    {
        return function (Request $request, array $parameters, Response $initialResponse) use ($handler): mixed {
            if (is_array($handler) && $handler[0] instanceof Controller) {
                $handler[0]->setContext($request, $initialResponse, $this->template, $this->container);
            }

            $output = $this->container->call($handler, array_merge($parameters, [
                'request' => $request,
                'response' => $initialResponse,
                'args' => $parameters,
            ]));

            return $output instanceof Response ? $output : Response::make($output);
        };
    }

    /** Resolve class-string and Class@method handlers through the container. */
    private function resolveHandler(mixed $handler): callable
    {
        if (is_callable($handler)) {
            return $handler;
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            $handler = explode('@', $handler, 2);
        }

        if (
            is_array($handler)
            && isset($handler[0], $handler[1])
            && is_string($handler[0])
            && class_exists($handler[0])
        ) {
            $handler = [$this->container->make($handler[0]), $handler[1]];
        }

        return is_callable($handler)
            ? $handler
            : throw new InvalidArgumentException('Invalid route handler.');
    }

    private function renderError(Throwable $exception, Request $request): Response
    {
        $next = fn (Throwable $e, Request $r): Response => $this->defaultErrorResponse($e, $r);
        foreach (array_reverse($this->errorHandlers) as $handler) {
            $previous = $next;
            $next = static fn (Throwable $e, Request $r): Response => $handler($e, $r, $previous);
        }

        $response = $next($exception, $request);

        return $response instanceof Response ? $response : Response::make($response);
    }

    private function defaultErrorResponse(Throwable $exception, Request $request): Response
    {
        $debug = (bool) $this->configuration->get('debug', false);
        $status = 500;
        $headers = [];

        if ($exception instanceof HttpException) {
            $status = $exception->status;
            $headers = $exception->headers;
        }

        if ($exception instanceof RouteNotFoundException) {
            $status = 404;
        } elseif ($exception instanceof MethodNotAllowedException) {
            $status = 405;
            $headers['Allow'] = implode(', ', $exception->allowedMethods());
        } elseif (!$debug && !($exception instanceof HttpException)) {
            error_log(sprintf(
                '[Kurama] %s: %s in %s:%d',
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            ));
        }

        $message = $debug ? $exception->getMessage() : $this->statusText($status);
        $payload = ['error' => ['status' => $status, 'message' => $message]];
        if ($exception instanceof ValidationException) {
            $payload['errors'] = $exception->errors();
        }

        if ($request->wantsJson() || str_starts_with($request->path(), '/api')) {
            return Response::json($payload, $status, $headers);
        }

        $title = htmlspecialchars($this->statusText($status), ENT_QUOTES, 'UTF-8');
        $text = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $html = "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\"><title>{$status} {$title}</title></head>"
            . "<body style=\"font-family:system-ui;max-width:40rem;margin:4rem auto;padding:0 1rem\">"
            . "<h1>{$status} {$title}</h1><p>{$text}</p><hr><small>Powered by Kurama</small></body></html>";

        return Response::html($html, $status, $headers);
    }

    private function statusText(int $status): string
    {
        return match ($status) {
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error',
            default => 'Error',
        };
    }
}
