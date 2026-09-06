<?php

// --------------------
// CORS Headers
// --------------------
$allowed_origins = [
    'http://127.0.0.1:5173',
    'http://localhost:5173',
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle browser preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../app/Middleware/LoggingMiddleware.php';
require_once __DIR__ . '/../core/ExceptionHandler.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../app/Controllers/MetadataController.php';
require_once __DIR__ . '/../app/Controllers/QueryController.php';
require_once __DIR__ . '/../app/Requests/QueryRequestValidator.php';
require_once __DIR__ . '/../app/Requests/QueryRequestNormalizer.php';

// Register Global Exception Handler
ExceptionHandler::register();

// Read Request Body
$publicRequest = json_decode(file_get_contents("php://input"), true);

// Validate JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    Response::error('Invalid JSON request.', 400, 'INVALID_JSON');
}
if (!is_array($publicRequest)) {
    Response::error(
        'Invalid request.',
        400,
        'INVALID_REQUEST',
        [['path' => '', 'message' => 'Request body must be a JSON object.']]
    );
}

// Execute Middleware
$middleware = new LoggingMiddleware();
$middleware->handle($publicRequest);

$validator = new QueryRequestValidator();
$validator->validate($publicRequest);

Response::setRequestContext($publicRequest);
$normalizer = new QueryRequestNormalizer();
$request = $normalizer->normalize($publicRequest);

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
