<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\AppFactory;
use Kurama\Application;
use Kurama\Config;
use Kurama\JsonResponse;
use Kurama\Request;
use Kurama\Template;
use Kurama\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class ApplicationTest extends TestCase
{
    protected function tearDown(): void
    {
        http_response_code(200);
    }

    public function testRootRouteReturnsResponse(): void
    {
        $app = new Application();
        $app->get('/', static fn (): Response => Response::html('<h1>Kurama</h1>'));

        $response = $app->handle(new Request('GET', '/'));
        self::assertSame(200, $response->getStatus());
        self::assertSame('<h1>Kurama</h1>', $response->getContent());
    }

    public function testRouteParametersAreInjected(): void
    {
        $app = new Application();
        $app->get('/hello/{name}', static fn (string $name): Response => Response::text("Hello, {$name}!"));

        $response = $app->handle(new Request('GET', '/hello/kurama'));
        self::assertSame('Hello, kurama!', $response->getContent());
        self::assertSame('text/plain; charset=UTF-8', $response->getHeader('Content-Type'));
    }

    public function testRequestIsInjectableByTypeAndQueryIsAvailable(): void
    {
        $app = new Application();
        $app->get('/ip', static function (Request $request): Response {
            return Response::json(['ip' => $request->ip(), 'q' => $request->query('q')]);
        });

        $response = $app->handle(new Request(
            'GET',
            '/ip',
            query: ['q' => 'abc'],
            server: ['REMOTE_ADDR' => '10.0.0.1'],
        ));

        self::assertSame(['ip' => '10.0.0.1', 'q' => 'abc'], json_decode($response->getContent(), true));
    }

    public function testArrayResultBecomesJsonResponse(): void
    {
        $app = new Application();
        $app->get('/json', static fn (): array => ['ok' => true]);

        $response = $app->handle(new Request('GET', '/json'));

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame('application/json; charset=UTF-8', $response->getHeader('Content-Type'));
        self::assertSame(['ok' => true], json_decode($response->getContent(), true));
    }

    public function testRouteGroupPrefix(): void
    {
        $app = new Application();
        $app->group(['prefix' => '/api'], static function (Application $app): void {
            $app->get('/ping', static fn (): Response => Response::json(['pong' => true]));
        });

        self::assertSame('{"pong":true}', $app->handle(new Request('GET', '/api/ping'))->getContent());
        self::assertSame(404, $app->handle(new Request('GET', '/ping'))->getStatus());
    }

    public function testGlobalGroupAndRouteMiddlewareAllRunInOrder(): void
    {
        $trace = [];

        $global = static function (Request $request, callable $next) use (&$trace): Response {
            $trace[] = 'global-before';
            $response = $next($request);
            $trace[] = 'global-after';

            return $response->withHeader('X-Global', '1');
        };
        $group = static function (Request $request, callable $next) use (&$trace): Response {
            $trace[] = 'group-before';
            $response = $next($request);
            $trace[] = 'group-after';

            return $response->withHeader('X-Group', '1');
        };
        $route = static function (Request $request, callable $next) use (&$trace): Response {
            $trace[] = 'route-before';
            $response = $next($request);
            $trace[] = 'route-after';

            return $response->withHeader('X-Route', '1');
        };

        $app = new Application();
        $app->middleware($global);
        $app->group(['prefix' => '/api', 'middleware' => [$group]], static function (Application $app) use ($route): void {
            $app->get('/item', static fn (): Response => Response::text('ok'))->middleware($route);
        });

        $response = $app->handle(new Request('GET', '/api/item'));

        self::assertSame('ok', $response->getContent());
        self::assertSame('1', $response->getHeader('X-Global'));
        self::assertSame('1', $response->getHeader('X-Group'));
        self::assertSame('1', $response->getHeader('X-Route'));
        self::assertSame([
            'global-before', 'group-before', 'route-before',
            'route-after', 'group-after', 'global-after',
        ], $trace);
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $called = false;
        $app = new Application();
        $app->middleware(static fn (Request $request, callable $next): Response => Response::text('blocked'));
        $app->get('/x', static function () use (&$called): Response {
            $called = true;

            return Response::text('ok');
        });

        $response = $app->handle(new Request('GET', '/x'));

        self::assertSame('blocked', $response->getContent());
        self::assertFalse($called);
    }

    public function testMissingRouteRenders404(): void
    {
        $app = new Application(['debug' => true]);

        $response = $app->handle(new Request('GET', '/missing'));

        self::assertSame(404, $response->getStatus());
        self::assertStringContainsString('No route found', $response->getContent());
    }

    public function testMethodMismatchRenders405WithAllowHeader(): void
    {
        $app = new Application(['debug' => true]);
        $app->get('/users', static fn (): Response => Response::text('list'));

        $response = $app->handle(new Request('POST', '/users'));

        self::assertSame(405, $response->getStatus());
        self::assertSame('GET', $response->getHeader('Allow'));
    }

    public function testApiErrorsAreJson(): void
    {
        $app = new Application(['debug' => true]);

        $response = $app->handle(new Request('GET', '/api/missing'));

        self::assertSame(404, $response->getStatus());
        self::assertSame(404, json_decode($response->getContent(), true)['error']['status']);
    }

    public function testHandlerExceptionRenders500WithDebugMessage(): void
    {
        $app = new Application(['debug' => true]);
        $app->get('/boom', static function (): Response {
            throw new RuntimeException('boom');
        });

        $response = $app->handle(new Request('GET', '/boom'));

        self::assertSame(500, $response->getStatus());
        self::assertStringContainsString('boom', $response->getContent());
    }

    public function testCustomErrorHandler(): void
    {
        $app = new Application();
        $app->setErrorHandler(static fn (Throwable $exception, Request $request): Response =>
            Response::json(['custom' => $exception->getMessage()], 418));

        $response = $app->handle(new Request('GET', '/missing'));

        self::assertSame(418, $response->getStatus());
        self::assertSame('No route found for GET /missing.', json_decode($response->getContent(), true)['custom']);
    }

    public function testSlimStyleHandlerSignature(): void
    {
        $app = AppFactory::create();
        $app->get('/hello/{name}', function (Request $request, Response $response, array $args): Response {
            $response->getBody()->write('Hello, ' . $args['name'] . '!');

            return $response;
        });

        $response = $app->handle(new Request('GET', '/hello/kurama'));

        self::assertSame('Hello, kurama!', $response->getContent());
    }

    public function testAppFactoryAndAddAlias(): void
    {
        $app = AppFactory::create(['debug' => true]);
        $app->add(function (Request $request, callable $next): Response {
            return $next($request)->withHeader('X-Add', 'yes');
        });
        $app->get('/x', static fn (): Response => Response::text('ok'));

        $response = $app->handle(new Request('GET', '/x'));

        self::assertSame('ok', $response->getContent());
        self::assertSame('yes', $response->getHeader('X-Add'));
    }

    public function testRequestSlimStyleAliases(): void
    {
        $request = new Request(
            'GET',
            '/users/7',
            query: ['q' => 'z'],
            body: ['name' => 'a'],
            headers: ['X-Test' => 'v'],
            params: ['id' => '7'],
        );

        self::assertSame('GET', $request->getMethod());
        self::assertSame(['q' => 'z'], $request->getQueryParams());
        self::assertSame(['name' => 'a'], $request->getParsedBody());
        self::assertSame('7', $request->getAttribute('id'));
        self::assertSame('v', $request->getHeaderLine('x-test'));

        $withAttribute = $request->withAttribute('user', 'u1');
        self::assertNull($request->getAttribute('user'));
        self::assertSame('u1', $withAttribute->getAttribute('user'));
    }

    public function testApplicationUrlForNamedRoute(): void
    {
        $app = AppFactory::create();
        $app->get('/users/{id:\d+}', static fn () => null)->name('user.show');

        self::assertSame('/users/42', $app->url('user.show', ['id' => 42]));
        self::assertSame('/users/42', $app->urlFor('user.show', ['id' => 42]));
    }

    public function testRenderHelperReturnsHtmlResponse(): void
    {
        $app = new Application(['views.path' => __DIR__ . '/fixtures/views']);

        $response = $app->render('hello', ['name' => 'Kurama']);

        self::assertSame(200, $response->getStatus());
        self::assertSame('text/html; charset=UTF-8', $response->getHeader('Content-Type'));
        self::assertSame('Hello Kurama', trim($response->getContent()));
    }

    public function testCoreServicesAreBoundInContainer(): void
    {
        $app = AppFactory::create();

        self::assertSame($app->router, $app->container->get('router'));
        self::assertSame($app->template, $app->container->get(Template::class));
        self::assertSame($app->configuration, $app->container->get('config'));
        self::assertSame($app->environment, $app->container->get('env'));
        self::assertSame($app, $app->container->get('app'));
    }

    public function testDottedConfigAndEnvironmentHelpers(): void
    {
        $app = new Application([
            'app.name' => 'Custom',
            'env.path' => __DIR__ . '/fixtures/.env.test',
        ]);

        self::assertSame('Custom', $app->config('app.name'));
        self::assertSame('from-env', $app->env('KURAMA_FIXTURE'));
        self::assertSame('hello world', $app->env('KURAMA_FIXTURE_QUOTED'));
        self::assertInstanceOf(Config::class, $app->configuration);
    }

    public function testContainerServiceCanBeInjectedIntoHandler(): void
    {
        $app = AppFactory::create();
        $app->container->instance(GreetingHelper::class, new GreetingHelper());

        $app->get('/service/{name}', function (string $name, GreetingHelper $greeting): Response {
            return Response::text($greeting->hello($name));
        });

        self::assertSame('Hi parsa', $app->handle(new Request('GET', '/service/parsa'))->getContent());
    }

    public function testRunUsesExplicitMethodAndUri(): void
    {
        $app = new Application();
        $app->get('/run', static fn (): Response => Response::text('ran'));

        ob_start();
        $app->run(new Request('GET', '/run'));
        $output = (string) ob_get_clean();

        self::assertSame('ran', $output);
        self::assertSame(200, http_response_code());
    }
}

final class GreetingHelper
{
    public function hello(string $name): string
    {
        return 'Hi ' . $name;
    }
}
