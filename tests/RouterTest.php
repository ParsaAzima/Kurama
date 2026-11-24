<?php

declare(strict_types=1);

namespace Kurama\Tests;

use InvalidArgumentException;
use Kurama\Exceptions\MethodNotAllowedException;
use Kurama\Exceptions\RouteNotFoundException;
use Kurama\Request;
use Kurama\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testRootRoute(): void
    {
        $router = new Router();
        $router->get('/', static fn (): string => 'Hello from Kurama!');

        self::assertSame('Hello from Kurama!', $router->dispatch('GET', '/'));
    }

    public function testStaticRoutes(): void
    {
        $router = new Router();
        $router->get('/users', static fn (): string => 'Users List');

        self::assertSame('Users List', $router->dispatch('GET', '/users'));
    }

    public function testStaticRouteDoesNotMatchUnrelatedPath(): void
    {
        $router = new Router();
        $router->get('/user', static fn (): string => 'single');

        $this->expectException(RouteNotFoundException::class);
        $router->dispatch('GET', '/users');
    }

    public function testDynamicParameter(): void
    {
        $router = new Router();
        $router->get('/users/{id}', static fn (string $id): string => "User ID: {$id}");

        self::assertSame('User ID: 42', $router->dispatch('GET', '/users/42'));
    }

    public function testMultipleParametersArePassedInDeclarationOrder(): void
    {
        $router = new Router();
        $router->get(
            '/posts/{post}/comments/{comment}',
            static fn (string $post, string $comment): string => $post . '|' . $comment,
        );

        self::assertSame('first|second', $router->dispatch('GET', '/posts/first/comments/second'));
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function httpMethodCases(): iterable
    {
        yield 'GET' => ['get', 'GET'];
        yield 'POST' => ['post', 'POST'];
        yield 'PUT' => ['put', 'PUT'];
        yield 'PATCH' => ['patch', 'PATCH'];
        yield 'DELETE' => ['delete', 'DELETE'];
        yield 'OPTIONS' => ['options', 'OPTIONS'];
        yield 'HEAD' => ['head', 'HEAD'];
    }

    #[DataProvider('httpMethodCases')]
    public function testAllSupportedHttpMethods(string $registrationMethod, string $httpMethod): void
    {
        $router = new Router();
        $router->{$registrationMethod}('/resource', static fn (): string => $httpMethod);

        self::assertSame($httpMethod, $router->dispatch($httpMethod, '/resource'));
    }

    public function testMethodIsNormalizedToUppercase(): void
    {
        $router = new Router();
        $router->get('/ping', static fn (): string => 'pong');

        self::assertSame('pong', $router->dispatch(' get ', '/ping'));
    }

    public function testGenericMatchWithMultipleMethods(): void
    {
        $router = new Router();
        $router->match(['GET', 'POST'], '/multi', static fn (): string => 'ok');

        self::assertSame('ok', $router->dispatch('GET', '/multi'));
        self::assertSame('ok', $router->dispatch('POST', '/multi'));
    }

    public function testAnyRoute(): void
    {
        $router = new Router();
        $router->any('/status', static fn (): string => 'ok');

        self::assertSame('ok', $router->dispatch('DELETE', '/status'));
        self::assertSame('ok', $router->dispatch('PATCH', '/status'));
        self::assertSame('ok', $router->dispatch('OPTIONS', '/status'));
        self::assertSame(['ANY'], $router->routes()->get(0)?->methods());
    }

    public function testMethodMismatchThrowsAndExposesAllowedMethods(): void
    {
        $router = new Router();
        $router->get('/users', static fn (): string => 'list');

        try {
            $router->dispatch('POST', '/users');
            self::fail('Expected MethodNotAllowedException.');
        } catch (MethodNotAllowedException $exception) {
            self::assertSame(['GET'], $exception->allowedMethods());
            self::assertSame('POST', $exception->method());
            self::assertSame('/users', $exception->path());
        }
    }

    public function testMissingRouteThrowsWithContext(): void
    {
        $router = new Router();

        try {
            $router->dispatch('GET', '/missing');
            self::fail('Expected RouteNotFoundException.');
        } catch (RouteNotFoundException $exception) {
            self::assertSame('GET', $exception->method());
            self::assertSame('/missing', $exception->path());
            self::assertStringContainsString('GET /missing', $exception->getMessage());
        }
    }

    public function testTrailingSlashesAreIgnored(): void
    {
        $router = new Router();
        $router->get('/users/', static fn (): string => 'list');

        self::assertSame('list', $router->dispatch('GET', '/users'));
        self::assertSame('list', $router->dispatch('GET', '/users/'));
        self::assertSame('list', $router->dispatch('GET', '/users///'));
    }

    public function testQueryStringAndFragmentAreIgnored(): void
    {
        $router = new Router();
        $router->get('/users/{id}', static fn (string $id): string => $id);

        self::assertSame('7', $router->dispatch('GET', '/users/7?page=2#section'));
    }

    public function testUrlEncodedParametersAreDecodedSafely(): void
    {
        $router = new Router();
        $router->get('/products/{slug}', static fn (string $slug): string => $slug);

        self::assertSame('hello world', $router->dispatch('GET', '/products/hello%20world'));
        self::assertSame('a/b', $router->dispatch('GET', '/products/a%2Fb'));
    }

    public function testParametersNeverCrossPathSeparators(): void
    {
        $router = new Router();
        $router->get('/users/{id}', static fn (string $id): string => $id);

        $this->expectException(RouteNotFoundException::class);
        $router->dispatch('GET', '/users/1/extra');
    }

    public function testClosureAndInvokableObjectHandlers(): void
    {
        $router = new Router();
        $router->get('/closure', static fn (): string => 'closure');
        $router->get('/invokable', new InvokableHandler());

        self::assertSame('invoked', $router->dispatch('GET', '/invokable'));
        self::assertSame('closure', $router->dispatch('GET', '/closure'));
    }

    public function testCallableArrayAndStaticMethodHandlers(): void
    {
        $router = new Router();
        $handler = new ExampleHandler();
        $router->get('/array', [$handler, 'handle']);
        $router->get('/static', [ExampleHandler::class, 'staticHandle']);

        self::assertSame('array handler', $router->dispatch('GET', '/array'));
        self::assertSame('static handler', $router->dispatch('GET', '/static'));
    }

    public function testInvalidHandlerIsRejected(): void
    {
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);
        $router->get('/broken', 'this_is_not_callable');
    }

    /** @return iterable<string, array{0: string}> */
    public static function invalidPatternCases(): iterable
    {
        yield 'missing leading slash' => ['users'];
        yield 'unbalanced open brace' => ['/users/{id'];
        yield 'unbalanced close brace' => ['/users/id}'];
        yield 'duplicate parameter' => ['/{id}/post/{id}'];
        yield 'adjacent parameters' => ['/{first}{second}'];
        yield 'empty segment' => ['/users//list'];
        yield 'query string in pattern' => ['/users?sort=asc'];
        yield 'whitespace in pattern' => ['/user name'];
        yield 'empty parameter name' => ['/users/{}'];
    }

    #[DataProvider('invalidPatternCases')]
    public function testInvalidRoutePatternsAreRejected(string $pattern): void
    {
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);
        $router->get($pattern, static fn (): string => 'never');
    }

    public function testRegistrationOrderIsPreserved(): void
    {
        $router = new Router();
        $router->get('/same', static fn (): string => 'first');
        $router->get('/same', static fn (): string => 'second');

        self::assertSame('first', $router->dispatch('GET', '/same'));
    }

    public function testStaticRouteWinsWhenRegisteredBeforeDynamicRoute(): void
    {
        $router = new Router();
        $router->get('/users/me', static fn (): string => 'static');
        $router->get('/users/{id}', static fn (string $id): string => 'dynamic:' . $id);

        self::assertSame('static', $router->dispatch('GET', '/users/me'));
        self::assertSame('dynamic:42', $router->dispatch('GET', '/users/42'));
    }

    public function testDynamicRouteWinsWhenRegisteredBeforeStaticRoute(): void
    {
        $router = new Router();
        $router->get('/users/{id}', static fn (string $id): string => 'dynamic:' . $id);
        $router->get('/users/me', static fn (): string => 'static');

        self::assertSame('dynamic:me', $router->dispatch('GET', '/users/me'));
    }

    public function testHeadFallsBackToGetWhenNoExplicitHeadRouteMatches(): void
    {
        $router = new Router();
        $router->get('/users', static fn (): string => 'list');

        self::assertSame('list', $router->dispatch('HEAD', '/users'));
    }

    public function testExplicitHeadRouteTakesPrecedence(): void
    {
        $router = new Router();
        $router->head('/users', static fn (): string => 'head');
        $router->get('/users', static fn (): string => 'get');

        self::assertSame('head', $router->dispatch('HEAD', '/users'));
        self::assertSame('get', $router->dispatch('GET', '/users'));
    }

    public function testHeadDoesNotMatchPostOnlyRoute(): void
    {
        $router = new Router();
        $router->post('/users', static fn (): string => 'created');

        $this->expectException(MethodNotAllowedException::class);
        $router->dispatch('HEAD', '/users');
    }

    public function testOptionsRequiresExplicitRegistration(): void
    {
        $router = new Router();
        $router->get('/users', static fn (): string => 'list');

        try {
            $router->dispatch('OPTIONS', '/users');
            self::fail('Expected MethodNotAllowedException.');
        } catch (MethodNotAllowedException $exception) {
            self::assertSame(['GET'], $exception->allowedMethods());
        }
    }

    public function testGroupPrefix(): void
    {
        $router = new Router();
        $router->group(['prefix' => '/api'], static function (Router $router): void {
            $router->get('/ping', static fn (): string => 'pong');
        });

        self::assertSame('pong', $router->dispatch('GET', '/api/ping'));
        self::assertSame('/api/ping', $router->routes()->get(0)?->pattern());
    }

    public function testGroupAndRouteMiddlewareAreAttached(): void
    {
        $groupMiddleware = static fn (Request $request, callable $next): mixed => $next($request);
        $routeMiddleware = static fn (Request $request, callable $next): mixed => $next($request);

        $router = new Router();
        $router->group(['prefix' => '/api', 'middleware' => [$groupMiddleware]], static function (Router $router) use ($routeMiddleware): void {
            $router->get('/item', static fn (): string => 'ok')->middleware($routeMiddleware);
        });

        $route = $router->routes()->get(0);
        self::assertNotNull($route);
        self::assertSame([$groupMiddleware, $routeMiddleware], $route->middlewares());
    }

    protected function tearDown(): void
    {
        http_response_code(200);
    }

    public function testRunEchoesHandlerResult(): void
    {
        $router = new Router();
        $router->get('/', static fn (): string => 'Hello from Kurama!');

        ob_start();
        $router->run('GET', '/');
        $output = (string) ob_get_clean();

        self::assertSame('Hello from Kurama!', $output);
        self::assertSame(200, http_response_code());
    }

    public function testRunEncodesArrayResultsAsJson(): void
    {
        $router = new Router();
        $router->get('/users/me', static fn (): array => ['id' => 7, 'name' => 'Kurama']);

        ob_start();
        $router->run('GET', '/users/me');
        $output = (string) ob_get_clean();

        self::assertSame(['id' => 7, 'name' => 'Kurama'], json_decode($output, true));
    }

    public function testRunSends404ForMissingRoute(): void
    {
        $router = new Router();

        ob_start();
        $router->run('GET', '/missing');
        $output = (string) ob_get_clean();

        self::assertSame(404, http_response_code());
        self::assertStringContainsString('No route found', $output);
    }

    public function testRunSends405ForMethodMismatch(): void
    {
        $router = new Router();
        $router->get('/users', static fn (): string => 'list');

        ob_start();
        $router->run('POST', '/users');
        $output = (string) ob_get_clean();

        self::assertSame(405, http_response_code());
        self::assertStringContainsString('GET', $output);
    }
}

final class InvokableHandler
{
    public function __invoke(): string
    {
        return 'invoked';
    }
}

final class ExampleHandler
{
    public function handle(): string
    {
        return 'array handler';
    }

    public static function staticHandle(): string
    {
        return 'static handler';
    }
}
