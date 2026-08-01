<?php

declare(strict_types=1);

namespace WhatstheUp\Support;

use PDO;
use Throwable;
use WhatstheUp\Controllers\Api\V1\AuthController;
use WhatstheUp\Middleware\Authenticate;
use WhatstheUp\Services\AuditService;
use WhatstheUp\Services\AuthenticationService;

final class App
{
    public function __construct(public readonly string $root, public readonly PDO $db, public readonly Router $router)
    {
    }

    public static function create(string $root): self
    {
        $db = Database::connect();
        $router = new Router();
        $audit = new AuditService($db);
        $auth = new AuthenticationService($db, $audit);
        $controller = new AuthController($auth);
        $authenticate = new Authenticate($auth);
        require $root . '/routes/api.php';
        return new self($root, $db, $router);
    }

    public function run(Request $request): never
    {
        try {
            $this->cors($request);
            if ($request->method === 'OPTIONS') {
                Response::noContent();
            }
            $result = $this->router->dispatch($request);
            if (is_array($result)) {
                Response::json($result);
            }
            Response::noContent();
        } catch (HttpException $exception) {
            Response::json(['error' => ['code' => $exception->codeName, 'message' => $exception->getMessage()]], $exception->status);
        } catch (Throwable $exception) {
            error_log(json_encode(['level' => 'error', 'message' => 'Unhandled API error', 'exception' => $exception::class]));
            $message = Env::bool('APP_DEBUG') ? $exception->getMessage() : 'An unexpected error occurred.';
            Response::json(['error' => ['code' => 'server_error', 'message' => $message]], 500);
        }
    }

    private function cors(Request $request): void
    {
        $origin = $request->headers['origin'] ?? null;
        $allowed = array_filter(array_map('trim', explode(',', Env::get('CORS_ALLOWED_ORIGINS', '') ?? '')));
        if ($origin !== null && !in_array($origin, $allowed, true)) {
            throw new HttpException(403, 'Origin is not allowed.', 'cors_denied');
        }
        if ($origin !== null) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Vary: Origin');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Max-Age: 600');
        }
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cache-Control: no-store');
    }
}
