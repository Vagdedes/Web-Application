<?php

class SerperDevGoogleSearch
{
    public const
        MAX_RESULTS = 100,
        MAX_QUERY_LENGTH = 2048,
        MAX_QUERY_WORDS = 100;

    public static function isQueryValid(string $query): bool
    {
        $len = strlen($query);
        return $len > 0
            && $len <= self::MAX_QUERY_LENGTH
            && sizeof(explode(" ", $query, self::MAX_QUERY_WORDS + 1)) <= self::MAX_QUERY_WORDS;
    }

    // Separator

    private ?string $apiKey;
    private string $baseUrl = 'https://google.serper.dev/search';

    public function __construct()
    {
        $credentials = get_keys_from_file("serper_dev_credentials", 1);

        if ($credentials === null) {
            $this->apiKey = null;
            return;
        }
        $this->apiKey = $credentials[0];
    }

    public function isValid(): bool
    {
        return $this->apiKey !== null;
    }

    /**
     * Fetches the news and returns an array of GoogleSearchResult objects.
     *
     * @param string $query The search term.
     * @param int $num The number of results to fetch (Defaults to 10).
     * @return GoogleSearchResult[]|string
     */
    public function fetch(string $query, int $num = self::MAX_RESULTS / 2, int $timeoutSeconds = 10): array|string
    {
        if (!$this->isValid()) {
            return "Serper API credentials are not properly configured.";
        }
        if (!$this->isQueryValid($query)) {
            return "Invalid query for Serper Search: " . $query;
        }
        if ($num <= 0 || $num > self::MAX_RESULTS) {
            return "Invalid number of results requested: " . $num . ". Must be between 1 and " . self::MAX_RESULTS . ".";
        }
        $payload = json_encode([
            'q' => $query,
            'num' => $num
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-KEY: ' . $this->apiKey,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false
            || $httpCode !== 200) {
            error_log("Serper API Error: " . $response);
            return "Serper API Error. HTTP Code: " . $httpCode;
        }

        $decodedResponse = json_decode($response, true);
        $results = [];

        if (!empty($decodedResponse['topStories'])) {
            foreach ($decodedResponse['topStories'] as $story) {
                $item = [
                    'title' => $story['title'] ?? '',
                    'link' => $story['link'] ?? '',
                    // Fallback to source + date to mimic the old snippet behavior
                    'snippet' => ($story['source'] ?? 'News') . ' - ' . ($story['date'] ?? ''),
                ];
                $results[] = new GoogleSearchResult($item);

                if (count($results) >= $num) {
                    break;
                }
            }
        }

        if (count($results) < $num && !empty($decodedResponse['organic'])) {
            foreach ($decodedResponse['organic'] as $organic) {
                $item = [
                    'title' => $organic['title'] ?? '',
                    'link' => $organic['link'] ?? '',
                    'snippet' => $organic['snippet'] ?? '',
                ];
                $results[] = new GoogleSearchResult($item);
                if (count($results) >= $num) break;
            }
        }
        return $results;
    }
    
}