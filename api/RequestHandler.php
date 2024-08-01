<?php
require_once __DIR__ . '/../config/config.php';

class RequestHandler
{
    private int $cacheTtl = 300;

    public function handleRequest(array $requestData): array
    {
        if (!isset($requestData['action'])) {
            return ['http_code' => 400, 'response' => json_encode(['error' => 'Action not specified'])];
        }

        $cacheKey = $this->generateCacheKey($requestData);
        $cachedResponse = apcu_fetch($cacheKey);

        if ($cachedResponse !== false) {
            return $cachedResponse;
        }

        $response = match ($requestData['action']) {
            'article_details' => $this->getArticleDetails($requestData),
            'articles' => $this->getArticles($requestData),
            'properties' => $this->getProperties($requestData),
            'location' => $this->getLocation($requestData),
            'currency' => $this->getCurrency($requestData),
            'news_details' => $this->getNewsDetails($requestData),
            'news' => $this->getNewsList($requestData),
            'search' => $this->search($requestData),
            default => ['http_code' => 400, 'response' => json_encode(['error' => 'Wrong action'])],
        };


        apcu_store($cacheKey, $response, $this->cacheTtl);

        return $response;
    }

    private function generateCacheKey(array $requestData): string
    {

        return md5(json_encode($requestData));
    }

    private function getArticleDetails(array $requestData): array
    {
        if (!isset($requestData['block_id']) || !isset($requestData['article_id'])) {
            return ['http_code' => 400, 'response' => json_encode(['error' => 'Required parameters are missing'])];
        }

        return $this->sendRequestToAPI('article_details', $requestData);
    }

    private function getArticles(array $requestData): array
    {
        if (!isset($requestData['block_id'])) {
            return ['http_code' => 400, 'response' => json_encode(['error' => 'Block ID is missing'])];
        }

        $requestData['ctx'] = $requestData['ctx'] ?? 'STORIES';
        $requestData['sort_type'] = $requestData['sort_type'] ?? 'RANK';

        return $this->sendRequestToAPI('articles', $requestData);
    }

    private function getProperties(array $requestData): array
    {
        if (!isset($requestData['block_id'])) {
            return ['http_code' => 400, 'response' => json_encode(['error' => 'Block ID is missing'])];
        }

        return $this->sendRequestToAPI('properties', $requestData);
    }

    private function getLocation(array $requestData): array
    {
        if (!isset($requestData['block_id'])) {
            return ['http_code' => 400, 'response' => json_encode(['error' => 'Block ID is missing'])];
        }

        return $this->sendRequestToAPI('location', $requestData);
    }

    private function getCurrency(array $requestData): array
    {
        if (!isset($requestData['block_id'])) {
            return ['http_code' => 400, 'response' => json_encode(['error' => 'Block ID is missing'])];
        }

        return $this->sendRequestToAPI('currency', $requestData);
    }

    private function getNewsDetails(array $requestData): array
    {
        if (!isset($requestData['block_id']) || !isset($requestData['news_id'])) {
            return ['http_code' => 400, 'response' => json_encode(['error' => 'Required parameters are missing'])];
        }

        return $this->sendRequestToAPI('news_details', $requestData);
    }

    private function getNewsList(array $requestData): array
    {
        if (!isset($requestData['block_id'])) {
            return ['http_code' => 400, 'response' => json_encode(['error' => 'Block ID is missing'])];
        }

        return $this->sendRequestToAPI('news', $requestData);
    }

    private function search(array $requestData): array
    {
        if (!isset($requestData['query'])) {
            return ['http_code' => 400, 'response' => json_encode(['error' => 'Query is missing'])];
        }

        global $config;
        $searchApiUrl = $config['search_api_url'];
        $encodedQuery = rawurlencode($requestData['query']);
        $limit = $requestData['limit'] ?? 10;
        $offset = $requestData['offset'] ?? 0;
        $url = "$searchApiUrl/search?query=$encodedQuery&limit=$limit&offset=$offset";

        $cacheKey = $this->generateCacheKey($requestData);

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
                CURLOPT_CONNECTTIMEOUT_MS => 500,
                CURLOPT_TIMEOUT_MS => 500,
                CURLOPT_DNS_CACHE_TIMEOUT => 5,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response !== false && $httpCode === 200) {
                $result = [
                    'http_code' => $httpCode,
                    'response' => $response,
                ];
                apcu_store($cacheKey, $result, $this->cacheTtl);
                return $result;
            } else {
                return [
                    'http_code' => $httpCode,
                    'response' => json_encode(['error' => 'Request failed', 'curl_error' => $curlError]),
                ];
            }
        } catch (Exception $exception) {
            return ['http_code' => 500, 'response' => json_encode(['error' => $exception->getMessage()])];
        }
    }

    private function sendRequestToAPI(string $action, array $requestData): array
    {
        global $config;

        $url = $config['json_api_url'] . '?action=' . $action;

        $headers = [
            'Content-Type: application/json; charset=utf-8'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 5);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        $cacheKey = $this->generateCacheKey($requestData);

        $result = [
            'http_code' => $httpCode ?: 200,
            'response' => $response ?: json_encode(['error' => 'Empty response from API']),
        ];

        apcu_store($cacheKey, $result, $this->cacheTtl);

        return $result;
    }
}
