<?php

class GoogleSearchSummary
{
    private string $summary;
    /** @var GoogleSearchSummarySource[] */
    private array $sources = [];

    public function __construct(string $summary, array $rawSources = [])
    {
        $this->summary = trim($summary);

        foreach ($rawSources as $source) {
            $this->sources[] = new GoogleSearchSummarySource($source);
        }
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * @return GoogleSearchSummarySource[]
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    public function hasSources(): bool
    {
        return !empty($this->sources);
    }
}