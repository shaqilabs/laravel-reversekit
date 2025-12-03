<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Parsers;

use InvalidArgumentException;
use Shaqi\ReverseKit\Support\RelationshipDetector;
use Shaqi\ReverseKit\Support\TypeInferrer;

class JsonParser
{
    /**
     * Parsed entities with their metadata.
     *
     * @var array<string, array>
     */
    private array $entities = [];

    public function __construct(
        private TypeInferrer $typeInferrer,
        private RelationshipDetector $relationshipDetector
    ) {
    }

    /**
     * Parse JSON content and extract entities.
     *
     * @throws InvalidArgumentException
     */
    public function parse(string $jsonContent): array
    {
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('JSON must represent an object or array.');
        }

        $this->entities = [];
        $this->extractEntities($data);

        return $this->entities;
    }

    /**
     * Parse JSON from file.
     *
     * @throws InvalidArgumentException
     */
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new InvalidArgumentException("Unable to read file: {$filePath}");
        }

        return $this->parse($content);
    }

    /**
     * Extract entities recursively from JSON data.
     */
    private function extractEntities(array $data, string $parentEntity = '', int $depth = 0): void
    {
        // Prevent infinite recursion
        if ($depth > 10) {
            return;
        }

        foreach ($data as $key => $value) {
            // Skip numeric keys (array items)
            if (is_numeric($key)) {
                continue;
            }

            // Check if this is an entity (has id field or is an object)
            if (is_array($value) && $this->typeInferrer->isAssociativeArray($value)) {
                $this->processEntity($key, $value, $parentEntity, $depth);
            }
        }
    }

    /**
     * Process a single entity.
     */
    private function processEntity(string $entityName, array $entityData, string $parentEntity, int $depth): void
    {
        $singularName = $this->relationshipDetector->singularize($entityName);
        $modelName = $this->relationshipDetector->toPascalCase($singularName);
        $tableName = $this->relationshipDetector->pluralize(
            $this->relationshipDetector->toSnakeCase($singularName)
        );

        $fields = [];
        $relationships = [];
        $casts = [];

        foreach ($entityData as $fieldName => $fieldValue) {
            // Skip processing arrays of objects as fields - they become relationships
            if (is_array($fieldValue) && $this->relationshipDetector->isArrayOfObjects($fieldValue)) {
                // This is a hasMany relationship
                $relatedSingular = $this->relationshipDetector->singularize($fieldName);
                $relatedModel = $this->relationshipDetector->toPascalCase($relatedSingular);

                $relationships[$fieldName] = [
                    'type' => 'hasMany',
                    'related' => $relatedModel,
                    'method' => $this->relationshipDetector->toCamelCase($fieldName),
                    'foreignKey' => $this->relationshipDetector->toSnakeCase($singularName) . '_id',
                    'localKey' => 'id',
                ];

                // Process child entity
                $childData = $fieldValue[0];
                // Add foreign key to child
                $childData[$this->relationshipDetector->toSnakeCase($singularName) . '_id'] = 1;
                $this->processEntity($fieldName, $childData, $singularName, $depth + 1);

                // Add belongsTo relationship to child entity
                $childModelName = $relatedModel;
                if (isset($this->entities[$childModelName])) {
                    $this->entities[$childModelName]['relationships'][$singularName] = [
                        'type' => 'belongsTo',
                        'related' => $modelName,
                        'method' => $this->relationshipDetector->toCamelCase($singularName),
                        'foreignKey' => $this->relationshipDetector->toSnakeCase($singularName) . '_id',
                        'ownerKey' => 'id',
                    ];
                }

                continue;
            }

            // Handle nested single object as belongsTo
            if (is_array($fieldValue) && $this->typeInferrer->isAssociativeArray($fieldValue)) {
                $relatedSingular = $this->relationshipDetector->singularize($fieldName);
                $relatedModel = $this->relationshipDetector->toPascalCase($relatedSingular);

                $relationships[$fieldName] = [
                    'type' => 'belongsTo',
                    'related' => $relatedModel,
                    'method' => $this->relationshipDetector->toCamelCase($fieldName),
                    'foreignKey' => $this->relationshipDetector->toSnakeCase($fieldName) . '_id',
                    'ownerKey' => 'id',
                ];

                // Add foreign key field
                $foreignKeyField = $this->relationshipDetector->toSnakeCase($fieldName) . '_id';
                $fields[$foreignKeyField] = [
                    'name' => $foreignKeyField,
                    'value' => $fieldValue['id'] ?? 1,
                    'phpType' => 'integer',
                    'migration' => ['type' => 'unsignedBigInteger', 'nullable' => false],
                    'cast' => null,
                    'nullable' => false,
                ];

                // Process the nested entity
                $this->processEntity($fieldName, $fieldValue, $singularName, $depth + 1);

                continue;
            }

            // Regular field
            $phpType = $this->typeInferrer->inferPhpType($fieldValue);
            $migration = $this->typeInferrer->inferMigrationType($fieldValue, $fieldName);
            $cast = $this->typeInferrer->getCastType($fieldValue, $fieldName);

            $fields[$fieldName] = [
                'name' => $fieldName,
                'value' => $fieldValue,
                'phpType' => $phpType,
                'migration' => $migration,
                'cast' => $cast,
                'nullable' => is_null($fieldValue),
            ];

            if ($cast !== null) {
                $casts[$fieldName] = $cast;
            }
        }

        // Add foreign key if this entity has a parent
        if (!empty($parentEntity) && !isset($fields[$this->relationshipDetector->toSnakeCase($parentEntity) . '_id'])) {
            $foreignKeyField = $this->relationshipDetector->toSnakeCase($parentEntity) . '_id';
            $fields[$foreignKeyField] = [
                'name' => $foreignKeyField,
                'value' => 1,
                'phpType' => 'integer',
                'migration' => ['type' => 'unsignedBigInteger', 'nullable' => false],
                'cast' => null,
                'nullable' => false,
            ];
        }

        $this->entities[$modelName] = [
            'name' => $modelName,
            'table' => $tableName,
            'fields' => $fields,
            'relationships' => $relationships,
            'casts' => $casts,
            'parent' => $parentEntity ? $this->relationshipDetector->toPascalCase($parentEntity) : null,
            'hasUserId' => isset($fields['user_id']),
        ];
    }

    /**
     * Get parsed entities.
     */
    public function getEntities(): array
    {
        return $this->entities;
    }
}
