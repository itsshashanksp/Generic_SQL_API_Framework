<?php

header("Content-Type: application/json");

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../app/Middleware/LoggingMiddleware.php';
require_once __DIR__ . '/../core/ExceptionHandler.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../app/Controllers/MetadataController.php';
require_once __DIR__ . '/../app/Controllers/QueryController.php';

// Register Global Exception Handler
ExceptionHandler::register();

// Read Request Body
$request = json_decode(file_get_contents("php://input"), true);

// Validate JSON
if (!$request) {
    Response::error("Invalid JSON Request", 400);
}

// Execute Middleware
$middleware = new LoggingMiddleware();
$middleware->handle($request);

// Validate Controller
Validator::required($request, [
    'controller',
    'action'
]);

// Controller Name
$controller = ucfirst($request['controller']) . "Controller";

// Action Name
$action = $request['action'];

// Controller File
$controllerFile = __DIR__ . "/../app/Controllers/" . $controller . ".php";

// Check Controller Exists
if (!file_exists($controllerFile)) {
    Response::error("Controller Not Found", 404);
}

// Load Controller
require_once $controllerFile;

// Create Controller Object
$instance = new $controller();

// Check Action Exists
if (!method_exists($instance, $action)) {
    Response::error("Action Not Found", 404);
}

// Execute Action
$instance->$action($request);