<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Support;

class RelationshipDetector
{
    public function __construct(
        private TypeInferrer $typeInferrer
    ) {
    }

    /**
     * Detect relationships from JSON structure.
     *
     * @return array<string, array>
     */
    public function detect(array $data, string $parentEntity = ''): array
    {
        $relationships = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->isArrayOfObjects($value)) {
                    // hasMany relationship
                    $relationships[$key] = [
                        'type' => 'hasMany',
                        'related' => $this->singularize($key),
                        'foreignKey' => $this->getForeignKey($parentEntity),
                        'localKey' => 'id',
                    ];
                } elseif ($this->typeInferrer->isAssociativeArray($value)) {
                    // belongsTo relationship (nested object)
                    $relationships[$key] = [
                        'type' => 'belongsTo',
                        'related' => $this->singularize($key),
                        'foreignKey' => $key . '_id',
                        'ownerKey' => 'id',
                    ];
                }
            }
        }

        return $relationships;
    }

    /**
     * Check if value is array of objects.
     */
    public function isArrayOfObjects(mixed $value): bool
    {
        if (!is_array($value) || empty($value)) {
            return false;
        }

        // Check if not associative (indexed array)
        if ($this->typeInferrer->isAssociativeArray($value)) {
            return false;
        }

        // Check if first element is an object (associative array)
        return is_array($value[0]) && $this->typeInferrer->isAssociativeArray($value[0]);
    }

    /**
     * Get foreign key name for a parent entity.
     */
    public function getForeignKey(string $parentEntity): string
    {
        if (empty($parentEntity)) {
            return 'parent_id';
        }

        return $this->toSnakeCase($this->singularize($parentEntity)) . '_id';
    }

    /**
     * Singularize a word (basic implementation).
     */
    public function singularize(string $word): string
    {
        $word = strtolower($word);

        // Common irregular plurals
        $irregulars = [
            'people' => 'person',
            'men' => 'man',
            'women' => 'woman',
            'children' => 'child',
            'mice' => 'mouse',
            'geese' => 'goose',
            'teeth' => 'tooth',
            'feet' => 'foot',
            'data' => 'datum',
            'media' => 'medium',
            'analyses' => 'analysis',
            'criteria' => 'criterion',
        ];

        if (isset($irregulars[$word])) {
            return $irregulars[$word];
        }

        // Common patterns
        if (str_ends_with($word, 'ies')) {
            return substr($word, 0, -3) . 'y';
        }

        if (str_ends_with($word, 'es') && (str_ends_with($word, 'sses') || str_ends_with($word, 'shes') || str_ends_with($word, 'ches') || str_ends_with($word, 'xes'))) {
            return substr($word, 0, -2);
        }

        if (str_ends_with($word, 's') && !str_ends_with($word, 'ss')) {
            return substr($word, 0, -1);
        }

        return $word;
    }

    /**
     * Pluralize a word (basic implementation).
     */
    public function pluralize(string $word): string
    {
        $word = strtolower($word);

        // Common irregular plurals
        $irregulars = [
            'person' => 'people',
            'man' => 'men',
            'woman' => 'women',
            'child' => 'children',
            'mouse' => 'mice',
            'goose' => 'geese',
            'tooth' => 'teeth',
            'foot' => 'feet',
            'datum' => 'data',
            'medium' => 'media',
            'analysis' => 'analyses',
            'criterion' => 'criteria',
        ];

        if (isset($irregulars[$word])) {
            return $irregulars[$word];
        }

        // Common patterns
        if (str_ends_with($word, 'y') && !in_array($word[-2] ?? '', ['a', 'e', 'i', 'o', 'u'])) {
            return substr($word, 0, -1) . 'ies';
        }

        if (str_ends_with($word, 's') || str_ends_with($word, 'sh') || str_ends_with($word, 'ch') || str_ends_with($word, 'x')) {
            return $word . 'es';
        }

        return $word . 's';
    }

    /**
     * Convert to snake_case.
     */
    public function toSnakeCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }

    /**
     * Convert to PascalCase.
     */
    public function toPascalCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $string)));
    }

    /**
     * Convert to camelCase.
     */
    public function toCamelCase(string $string): string
    {
        return lcfirst($this->toPascalCase($string));
    }
}
