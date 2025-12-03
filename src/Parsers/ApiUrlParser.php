<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Parsers;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Shaqi\ReverseKit\Contracts\ParserInterface;

class ApiUrlParser implements ParserInterface
{
    private array $entities = [];

    public function __construct(
        private JsonParser $jsonParser
    ) {
    }

    /**
     * Parse JSON from a URL.
     *
     * @throws InvalidArgumentException
     */
    public function parse(string $url): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid URL: {$url}");
        }

        $response = Http::timeout(30)
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'Laravel-ReverseKit/1.0',
            ])
            ->get($url);

        if (!$response->successful()) {
            throw new InvalidArgumentException(
                "Failed to fetch URL: {$url}. Status: {$response->status()}"
            );
        }

        $contentType = $response->header('Content-Type') ?? '';
        if (!str_contains($contentType, 'json') && !str_contains($contentType, 'text')) {
            throw new InvalidArgumentException(
                "URL does not return JSON. Content-Type: {$contentType}"
            );
        }

        $json = $response->body();

        $this->entities = $this->jsonParser->parse($json);

        return $this->entities;
    }

    /**
     * Parse with authentication.
     *
     * @throws InvalidArgumentException
     */
    public function parseWithAuth(string $url, string $token, string $tokenType = 'Bearer'): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid URL: {$url}");
        }

        $response = Http::timeout(30)
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'Laravel-ReverseKit/1.0',
                'Authorization' => "{$tokenType} {$token}",
            ])
            ->get($url);

        if (!$response->successful()) {
            throw new InvalidArgumentException(
                "Failed to fetch URL: {$url}. Status: {$response->status()}"
            );
        }

        $json = $response->body();

        $this->entities = $this->jsonParser->parse($json);

        return $this->entities;
    }

    /**
     * Parse multiple endpoints and merge entities.
     *
     * @param array<string> $urls
     * @throws InvalidArgumentException
     */
    public function parseMultiple(array $urls): array
    {
        $this->entities = [];

        foreach ($urls as $url) {
            $entities = $this->parse($url);
            $this->entities = array_merge($this->entities, $entities);
        }

        return $this->entities;
    }

    /**
     * Get parsed entities.
     */
    public function getEntities(): array
    {
        return $this->entities;
    }
}
