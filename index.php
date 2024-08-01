<?php
require_once 'api/RequestHandler.php';
require_once 'api/ResponseHandler.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$requestData = json_decode(file_get_contents('php://input'), true);


$requestHandler = new RequestHandler();
$response = $requestHandler->handleRequest($requestData);

$responseHandler = new ResponseHandler();
echo $responseHandler->handleResponse($response);

