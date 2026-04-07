<?php

class GoogleSearchSummary
{
    private string $summary;
    /** @var GoogleSearchSummarySource[] */
    private array $sources = [];
    private float $cost;

    public function __construct(string $summary, array $rawSources = [], float $cost = 0.0)
    {
        $this->summary = trim($summary);
        $this->cost = $cost;

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

    public function getCost(): float
    {
        return $this->cost;
    }
}