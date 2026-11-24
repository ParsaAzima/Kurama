<?php

declare(strict_types=1);

namespace Kurama\Tests;

use Kurama\AppFactory;
use Kurama\Application;
use Kurama\Controller;
use Kurama\Request;
use Kurama\Response;
use PHPUnit\Framework\TestCase;

final class ControllerTest extends TestCase
{
    public function testClassStringControllerHandler(): void
    {
        $app = AppFactory::create();
        $app->get('/users/{id}', [UserController::class, 'show']);

        $response = $app->handle(new Request('GET', '/users/42'));

        self::assertSame(
            ['id' => '42', 'method' => 'GET'],
            json_decode($response->getContent(), true),
        );
    }

    public function testAtNotationControllerHandler(): void
    {
        $app = AppFactory::create();
        $app->get('/write', UserController::class . '@write');

        $response = $app->handle(new Request('GET', '/write'));

        self::assertSame('from controller', $response->getContent());
    }

    public function testControllerReceivesSlimStyleArguments(): void
    {
        $app = AppFactory::create();
        $app->get('/list/{id}', [UserController::class, 'list']);

        $response = $app->handle(new Request('GET', '/list/7'));

        self::assertSame('users:7', $response->getContent());
    }

    public function testReflectionCacheDoesNotBreakRepeatedRequests(): void
    {
        $app = AppFactory::create();
        $app->get('/users/{id}', [UserController::class, 'show']);

        $first = $app->handle(new Request('GET', '/users/1'))->getContent();
        $second = $app->handle(new Request('GET', '/users/2'))->getContent();

        self::assertSame(['id' => '1', 'method' => 'GET'], json_decode($first, true));
        self::assertSame(['id' => '2', 'method' => 'GET'], json_decode($second, true));
    }

    public function testControllerViewHelper(): void
    {
        $app = new Application(['views.path' => __DIR__ . '/fixtures/views']);
        $app->get('/page', [UserController::class, 'page']);

        $response = $app->handle(new Request('GET', '/page'));

        self::assertSame('Hello Controller', trim($response->getContent()));
    }

    public function testControllerWithRequiredConstructorArgumentsIsRejected(): void
    {
        $app = AppFactory::create();
        $app->get('/needs-args', [NeedsArgumentsController::class, 'index']);

        $response = $app->handle(new Request('GET', '/needs-args'));

        self::assertSame(500, $response->getStatus());
    }
}

final class UserController extends Controller
{
    public function show(string $id): Response
    {
        return $this->json([
            'id' => $this->param('id'),
            'method' => $this->request()->method(),
        ]);
    }

    public function write(): Response
    {
        return $this->text('from controller');
    }

    public function list(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write('users:' . ($args['id'] ?? ''));

        return $response;
    }

    public function page(): Response
    {
        return $this->view('hello', ['name' => 'Controller']);
    }
}

final class NeedsArgumentsController extends Controller
{
    public function __construct(private readonly string $name)
    {
    }

    public function index(): Response
    {
        return $this->text($this->name);
    }
}
