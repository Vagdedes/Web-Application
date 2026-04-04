<?php

class GoogleNewsClient
{
    private ?string $apiKey, $searchEngineId;
    private string $baseUrl = 'https://www.googleapis.com/customsearch/v1';

    public function __construct(?string $searchEngineId)
    {
        $this->searchEngineId = $searchEngineId;
        $credentials = get_keys_from_file("google_search_business_credentials", 1);

        if ($credentials === null) {
            $this->apiKey = null;
            return;
        }
        $this->apiKey = $credentials[0];
    }

    private function isValid(): bool
    {
        return $this->apiKey !== null
            && $this->searchEngineId !== null;
    }

    /**
     * Fetches the news and returns an array of GoogleNewsResult objects.
     *
     * @param string $query The search term.
     * @param int $num The number of results (max 10).
     * @return GoogleSearchResult[]
     */
    public function fetchNews(string $query, int $num = 5, int $timeoutSeconds = 10): array
    {
        $params = [
            'q' => $query,
            'key' => $this->apiKey,
            'cx' => $this->searchEngineId,
            'num' => $num,
        ];
        $requestUrl = $this->baseUrl . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false
            || $httpCode !== 200) {
            error_log("Google API Error. HTTP Code: " . $httpCode);
            return [];
        }
        $decodedResponse = json_decode($response, true);
        $items = $decodedResponse['items'] ?? [];
        $results = [];

        foreach ($items as $item) {
            $results[] = new GoogleSearchResult($item);
        }
        return $results;
    }

}