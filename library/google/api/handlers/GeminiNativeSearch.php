<?php

class GeminiNativeSearch
{
    private ?string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent';

    public function __construct()
    {
        $credentials = get_keys_from_file("google_search_business_credentials", 2);

        if ($credentials === null) {
            $this->apiKey = null;
            return;
        }
        $this->apiKey = $credentials[1];
    }

    public function isValid(): bool
    {
        return $this->apiKey !== null;
    }

    public function fetchAndSummarize(
        string $topic,
        string $language,
        int    $timeoutSeconds = 15
    ): GoogleSearchSummary|string
    {
        if (!$this->isValid()) {
            return "Gemini API credentials are not properly configured.";
        }
        if (strlen(trim($topic)) === 0) {
            return "Invalid query for Gemini Search.";
        }
        $requestUrl = $this->baseUrl . '?key=' . $this->apiKey;
        $promptContext = "Search the live web for the latest news regarding: {$topic}. Summarize the most important updates into one concise paragraph in {$language}. Be highly factual.";

        $payload = json_encode([
            'contents' => [
                ['parts' => [['text' => $promptContext]]]
            ],
            'tools' => [
                ['google_search' => new stdClass()]
            ],
            'generationConfig' => [
                'temperature' => 0.2
            ]
        ]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("Gemini Search Error: " . $response);
            return "Google API Error. HTTP Code: " . $httpCode;
        }
        $decoded = json_decode($response, true);
        $summaryText = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? 'No summary generated.';
        $rawSources = [];
        $groundingChunks = $decoded['candidates'][0]['groundingMetadata']['groundingChunks'] ?? [];

        foreach ($groundingChunks as $chunk) {
            if (isset($chunk['web']['uri'])) {
                $rawSources[] = [
                    'title' => $chunk['web']['title'] ?? 'Unknown Source',
                    'url' => $chunk['web']['uri']
                ];
            }
        }
        $usage = $decoded['usageMetadata'] ?? [];
        $inTokens = $usage['promptTokenCount'] ?? 0;
        $outTokens = $usage['candidatesTokenCount'] ?? 0;
        $tokenCost = ($inTokens / 1_000_000 * 0.075) + ($outTokens / 1_000_000 * 0.3);
        $totalCost = $tokenCost + 0.014;
        return new GoogleSearchSummary($summaryText, $rawSources, $totalCost);
    }
}