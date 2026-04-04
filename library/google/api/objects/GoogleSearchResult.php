<?php

class GoogleSearchResult
{
    private ?string
        $title,
        $link,
        $snippet,
        $source;

    public function __construct(array $rawItem)
    {
        $this->title = $rawItem['title'] ?? null;
        $this->link = $rawItem['link'] ?? null;
        $this->snippet = $rawItem['snippet'] ?? null;
        $this->source = $rawItem['displayLink']
            ?? parse_url($this->link, PHP_URL_HOST) ?? null;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function getSnippet(): string
    {
        return $this->snippet;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function toString(): string
    {
        return "Source: " . ($this->source ?? 'Unknown') .
            " | Title: " . ($this->title ?? 'No Title') .
            " | Summary: " . ($this->snippet ?? 'No Snippet') .
            " | Link: " . ($this->link ?? 'No Link');
    }

}