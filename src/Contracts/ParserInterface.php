<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Contracts;

interface ParserInterface
{
    /**
     * Parse input and return entities array.
     *
     * @return array<string, array>
     */
    public function parse(string $input): array;

    /**
     * Get parsed entities.
     *
     * @return array<string, array>
     */
    public function getEntities(): array;
}
