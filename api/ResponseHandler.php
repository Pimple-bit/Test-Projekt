<?php
class ResponseHandler {
    public function handleResponse($response) {
        header('Content-Type: application/json; charset=utf-8');
        $responseData = null;
        if (isset($response['response'])) {
            $responseData = json_decode($response['response'], true);
        }
        $httpCode = 200;
        if (isset($response['http_code'])) {
            $httpCode = $response['http_code'];
        }

        $result = match ($httpCode) {
            200 => $responseData,
            404 => ['error' => 'Content not found'],
            500 => ['error' => 'Internal Server Error'],
            default => ['error' => 'An unexpected error occurred'],
        };

        return json_encode($result);
    }
}
