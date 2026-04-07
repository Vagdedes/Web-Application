<?php

class GoogleSearchSummarySource
{
    private string $title;
    private string $url;

    public function __construct(array $rawSource)
    {
        $this->title = $rawSource['title'] ?? 'Unknown Source';
        $this->url = $rawSource['url'] ?? '';
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

}