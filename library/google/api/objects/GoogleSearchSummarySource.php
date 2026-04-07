<?php

class GoogleSearchSummarySource
{
    private ?string $title, $url;

    public function __construct(array $rawSource)
    {
        $this->title = $rawSource['title'] ?? null;
        $this->url = $rawSource['url'] ?? null;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function toString(): string
    {
        return "Title: " . ($this->getTitle() ?? "Unknown") . "\n"
            . "| URL: " . ($this->getUrl() ?? "Unknown") . "\n";
    }

}