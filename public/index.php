<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use App\Controller\FineController;

require __DIR__ . '/../vendor/autoload.php';

// Build DI container
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    PDO::class => function (): PDO {
        $host = getenv('DB_HOST') ?: getenv('POSTGRES_HOST') ?: 'db';
        $port = (int)(getenv('DB_PORT') ?: getenv('POSTGRES_PORT') ?: 5432);
        $db   = getenv('DB_NAME') ?: getenv('POSTGRES_DB') ?: 'postgres';
        $user = getenv('DB_USER') ?: getenv('POSTGRES_USER') ?: 'postgres';
        $pass = getenv('DB_PASSWORD') ?: getenv('POSTGRES_PASSWORD') ?: 'postgres';

        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $db);
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    },
]);

$container = $containerBuilder->build();
AppFactory::setContainer($container);
$app = AppFactory::create();

// Middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

$app->get('/fines', [FineController::class, 'list']);
$app->post('/fines', [FineController::class, 'create']);
$app->get('/fines/{id}', [FineController::class, 'get']);
$app->put('/fines/{id}', [FineController::class, 'update']);
$app->patch('/fines/{id}', [FineController::class, 'partialUpdate']);
$app->delete('/fines/{id}', [FineController::class, 'delete']);
$app->patch('/fines/{id}/paid', [FineController::class, 'markAsPaid']);

$app->run();
