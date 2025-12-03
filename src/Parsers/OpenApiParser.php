<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Parsers;

use InvalidArgumentException;
use Shaqi\ReverseKit\Contracts\ParserInterface;
use Shaqi\ReverseKit\Support\RelationshipDetector;
use Shaqi\ReverseKit\Support\TypeInferrer;
use Symfony\Component\Yaml\Yaml;

class OpenApiParser implements ParserInterface
{
    private array $entities = [];
    private array $spec = [];

    public function __construct(
        private TypeInferrer $typeInferrer,
        private RelationshipDetector $relationshipDetector
    ) {
    }

    /**
     * Parse OpenAPI/Swagger specification file.
     *
     * @throws InvalidArgumentException
     */
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new InvalidArgumentException("Unable to read file: {$filePath}");
        }

        return $this->parseContent($content, $filePath);
    }

    /**
     * Parse OpenAPI content string.
     *
     * @throws InvalidArgumentException
     */
    public function parseContent(string $content, string $source = ''): array
    {
        $extension = pathinfo($source, PATHINFO_EXTENSION);

        // Try to parse as YAML first, then JSON
        if (in_array($extension, ['yaml', 'yml'])) {
            $this->spec = Yaml::parse($content);
        } else {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->spec = $decoded;
            } else {
                // Try YAML as fallback
                $this->spec = Yaml::parse($content);
            }
        }

        if (!is_array($this->spec)) {
            throw new InvalidArgumentException('Invalid OpenAPI specification format.');
        }

        $this->validateSpec();
        $this->extractEntities();

        return $this->entities;
    }

    /**
     * Validate OpenAPI specification structure.
     */
    private function validateSpec(): void
    {
        $version = $this->spec['openapi'] ?? $this->spec['swagger'] ?? null;

        if ($version === null) {
            throw new InvalidArgumentException(
                'Invalid OpenAPI/Swagger specification. Missing version field.'
            );
        }
    }

    /**
     * Extract entities from OpenAPI schemas.
     */
    private function extractEntities(): void
    {
        $schemas = $this->getSchemas();

        foreach ($schemas as $schemaName => $schema) {
            if ($this->isEntitySchema($schema)) {
                $this->processSchema($schemaName, $schema);
            }
        }
    }

    /**
     * Get schemas from spec (supports OpenAPI 3.x and Swagger 2.x).
     */
    private function getSchemas(): array
    {
        // OpenAPI 3.x
        if (isset($this->spec['components']['schemas'])) {
            return $this->spec['components']['schemas'];
        }

        // Swagger 2.x
        if (isset($this->spec['definitions'])) {
            return $this->spec['definitions'];
        }

        return [];
    }

    /**
     * Check if schema represents an entity (has properties).
     */
    private function isEntitySchema(array $schema): bool
    {
        return isset($schema['properties']) &&
               $schema['type'] === 'object' &&
               !($schema['x-skip-generation'] ?? false);
    }

    /**
     * Process a schema into an entity.
     */
    private function processSchema(string $schemaName, array $schema): void
    {
        $modelName = $this->relationshipDetector->toPascalCase($schemaName);
        $tableName = $this->relationshipDetector->pluralize(
            $this->relationshipDetector->toSnakeCase($schemaName)
        );

        $fields = [];
        $relationships = [];
        $casts = [];
        $required = $schema['required'] ?? [];

        foreach ($schema['properties'] as $propName => $propSchema) {
            $result = $this->processProperty($propName, $propSchema, $required);

            if ($result['isRelationship']) {
                $relationships[$propName] = $result['relationship'];
            } else {
                $fields[$propName] = $result['field'];
                if ($result['field']['cast']) {
                    $casts[$propName] = $result['field']['cast'];
                }
            }
        }

        $this->entities[$modelName] = [
            'name' => $modelName,
            'table' => $tableName,
            'fields' => $fields,
            'relationships' => $relationships,
            'casts' => $casts,
            'parent' => null,
            'hasUserId' => isset($fields['user_id']),
        ];
    }

    /**
     * Process a single property into a field or relationship.
     */
    private function processProperty(string $propName, array $propSchema, array $required): array
    {
        $isRequired = in_array($propName, $required);

        // Check for $ref (relationship)
        if (isset($propSchema['$ref'])) {
            $refModel = $this->extractRefModel($propSchema['$ref']);
            return [
                'isRelationship' => true,
                'relationship' => [
                    'type' => 'belongsTo',
                    'related' => $refModel,
                    'method' => $this->relationshipDetector->toCamelCase($propName),
                    'foreignKey' => $this->relationshipDetector->toSnakeCase($propName) . '_id',
                    'ownerKey' => 'id',
                ],
                'field' => null,
            ];
        }

        // Check for array of refs (hasMany)
        if (($propSchema['type'] ?? '') === 'array' && isset($propSchema['items']['$ref'])) {
            $refModel = $this->extractRefModel($propSchema['items']['$ref']);
            return [
                'isRelationship' => true,
                'relationship' => [
                    'type' => 'hasMany',
                    'related' => $refModel,
                    'method' => $this->relationshipDetector->toCamelCase($propName),
                    'foreignKey' => $this->relationshipDetector->toSnakeCase($propName) . '_id',
                    'localKey' => 'id',
                ],
                'field' => null,
            ];
        }

        // Regular field
        $field = $this->mapOpenApiTypeToField($propName, $propSchema, $isRequired);

        return [
            'isRelationship' => false,
            'relationship' => null,
            'field' => $field,
        ];
    }

    /**
     * Extract model name from $ref.
     */
    private function extractRefModel(string $ref): string
    {
        // #/components/schemas/User or #/definitions/User
        $parts = explode('/', $ref);
        return $this->relationshipDetector->toPascalCase(end($parts));
    }

    /**
     * Map OpenAPI type to Laravel field definition.
     */
    private function mapOpenApiTypeToField(string $propName, array $propSchema, bool $isRequired): array
    {
        $type = $propSchema['type'] ?? 'string';
        $format = $propSchema['format'] ?? null;
        $nullable = !$isRequired || ($propSchema['nullable'] ?? false);

        $mapping = $this->getTypeMapping($type, $format, $propName);

        return [
            'name' => $propName,
            'value' => $propSchema['example'] ?? $propSchema['default'] ?? null,
            'phpType' => $mapping['phpType'],
            'migration' => [
                'type' => $mapping['migrationType'],
                'nullable' => $nullable,
            ],
            'cast' => $mapping['cast'],
            'nullable' => $nullable,
        ];
    }

    /**
     * Get type mapping for OpenAPI types.
     */
    private function getTypeMapping(string $type, ?string $format, string $propName): array
    {
        // Check for common field names first
        if (str_contains($propName, 'email')) {
            return ['phpType' => 'string', 'migrationType' => 'string', 'cast' => null];
        }

        if (str_contains($propName, 'password')) {
            return ['phpType' => 'string', 'migrationType' => 'string', 'cast' => null];
        }

        return match (true) {
            $type === 'integer' && $format === 'int64' => ['phpType' => 'int', 'migrationType' => 'bigInteger', 'cast' => null],
            $type === 'integer' => ['phpType' => 'int', 'migrationType' => 'integer', 'cast' => null],
            $type === 'number' && $format === 'float' => ['phpType' => 'float', 'migrationType' => 'float', 'cast' => 'float'],
            $type === 'number' && $format === 'double' => ['phpType' => 'float', 'migrationType' => 'double', 'cast' => 'double'],
            $type === 'number' => ['phpType' => 'float', 'migrationType' => 'decimal', 'cast' => 'float'],
            $type === 'boolean' => ['phpType' => 'bool', 'migrationType' => 'boolean', 'cast' => 'boolean'],
            $type === 'string' && $format === 'date' => ['phpType' => 'string', 'migrationType' => 'date', 'cast' => 'date'],
            $type === 'string' && $format === 'date-time' => ['phpType' => 'string', 'migrationType' => 'dateTime', 'cast' => 'datetime'],
            $type === 'string' && $format === 'uuid' => ['phpType' => 'string', 'migrationType' => 'uuid', 'cast' => null],
            $type === 'string' && $format === 'email' => ['phpType' => 'string', 'migrationType' => 'string', 'cast' => null],
            $type === 'string' && $format === 'uri' => ['phpType' => 'string', 'migrationType' => 'string', 'cast' => null],
            $type === 'string' && $format === 'binary' => ['phpType' => 'string', 'migrationType' => 'binary', 'cast' => null],
            $type === 'array' => ['phpType' => 'array', 'migrationType' => 'json', 'cast' => 'array'],
            $type === 'object' => ['phpType' => 'array', 'migrationType' => 'json', 'cast' => 'object'],
            default => ['phpType' => 'string', 'migrationType' => 'string', 'cast' => null],
        };
    }

    /**
     * Get parsed entities.
     */
    public function getEntities(): array
    {
        return $this->entities;
    }
}
