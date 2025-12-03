<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Parsers;

use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Shaqi\ReverseKit\Contracts\ParserInterface;
use Shaqi\ReverseKit\Support\RelationshipDetector;

class DatabaseParser implements ParserInterface
{
    private array $entities = [];

    public function __construct(
        private RelationshipDetector $relationshipDetector
    ) {
    }

    /**
     * Parse database tables.
     *
     * @param string $input Comma-separated table names or "*" for all tables
     * @throws InvalidArgumentException
     */
    public function parse(string $input = '*'): array
    {
        $tables = $this->getTables($input);

        if (empty($tables)) {
            throw new InvalidArgumentException('No tables found in database.');
        }

        foreach ($tables as $table) {
            $this->processTable($table);
        }

        $this->detectRelationships();

        return $this->entities;
    }

    /**
     * Get list of tables to process.
     */
    private function getTables(string $input): array
    {
        $excludedTables = config('reversekit.database.excluded_tables', [
            'migrations',
            'password_reset_tokens',
            'password_resets',
            'failed_jobs',
            'personal_access_tokens',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
        ]);

        if ($input === '*') {
            $allTables = Schema::getTableListing();
            return array_filter($allTables, fn ($table) => !in_array($table, $excludedTables));
        }

        $requestedTables = array_map('trim', explode(',', $input));
        $existingTables = Schema::getTableListing();

        $tables = [];
        foreach ($requestedTables as $table) {
            if (in_array($table, $existingTables)) {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    /**
     * Process a single database table.
     */
    private function processTable(string $tableName): void
    {
        $columns = Schema::getColumns($tableName);
        $indexes = Schema::getIndexes($tableName);

        $singularName = $this->relationshipDetector->singularize($tableName);
        $modelName = $this->relationshipDetector->toPascalCase($singularName);

        $fields = [];
        $casts = [];

        foreach ($columns as $column) {
            $fieldName = $column['name'];

            // Skip auto-generated fields
            if (in_array($fieldName, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                if ($fieldName !== 'id') {
                    continue;
                }
            }

            $field = $this->mapColumnToField($column);
            $fields[$fieldName] = $field;

            if ($field['cast']) {
                $casts[$fieldName] = $field['cast'];
            }
        }

        $this->entities[$modelName] = [
            'name' => $modelName,
            'table' => $tableName,
            'fields' => $fields,
            'relationships' => [],
            'casts' => $casts,
            'parent' => null,
            'hasUserId' => isset($fields['user_id']),
            'indexes' => $this->processIndexes($indexes),
        ];
    }

    /**
     * Map database column to field definition.
     */
    private function mapColumnToField(array $column): array
    {
        $type = $column['type_name'];
        $mapping = $this->getTypeMapping($type);

        return [
            'name' => $column['name'],
            'value' => $column['default'],
            'phpType' => $mapping['phpType'],
            'migration' => [
                'type' => $mapping['migrationType'],
                'nullable' => $column['nullable'],
            ],
            'cast' => $mapping['cast'],
            'nullable' => $column['nullable'],
        ];
    }

    /**
     * Get type mapping for database types.
     */
    private function getTypeMapping(string $type): array
    {
        $type = strtolower($type);

        return match (true) {
            in_array($type, ['bigint', 'bigserial']) => ['phpType' => 'int', 'migrationType' => 'bigInteger', 'cast' => null],
            in_array($type, ['int', 'integer', 'serial', 'int4']) => ['phpType' => 'int', 'migrationType' => 'integer', 'cast' => null],
            in_array($type, ['smallint', 'int2']) => ['phpType' => 'int', 'migrationType' => 'smallInteger', 'cast' => null],
            in_array($type, ['tinyint', 'int1']) => ['phpType' => 'int', 'migrationType' => 'tinyInteger', 'cast' => null],
            default => $this->mapComplexType($type),
        };
    }

    /**
     * Map complex database types.
     */
    private function mapComplexType(string $type): array
    {
        return match (true) {
            str_contains($type, 'decimal') || str_contains($type, 'numeric') => ['phpType' => 'float', 'migrationType' => 'decimal', 'cast' => 'float'],
            in_array($type, ['float', 'real', 'float4']) => ['phpType' => 'float', 'migrationType' => 'float', 'cast' => 'float'],
            in_array($type, ['double', 'double precision', 'float8']) => ['phpType' => 'float', 'migrationType' => 'double', 'cast' => 'double'],
            in_array($type, ['bool', 'boolean']) => ['phpType' => 'bool', 'migrationType' => 'boolean', 'cast' => 'boolean'],
            $type === 'date' => ['phpType' => 'string', 'migrationType' => 'date', 'cast' => 'date'],
            in_array($type, ['datetime', 'timestamp', 'timestamptz']) => ['phpType' => 'string', 'migrationType' => 'dateTime', 'cast' => 'datetime'],
            $type === 'time' => ['phpType' => 'string', 'migrationType' => 'time', 'cast' => null],
            in_array($type, ['text', 'longtext', 'mediumtext']) => ['phpType' => 'string', 'migrationType' => 'text', 'cast' => null],
            in_array($type, ['json', 'jsonb']) => ['phpType' => 'array', 'migrationType' => 'json', 'cast' => 'array'],
            $type === 'uuid' => ['phpType' => 'string', 'migrationType' => 'uuid', 'cast' => null],
            in_array($type, ['blob', 'binary', 'varbinary', 'bytea']) => ['phpType' => 'string', 'migrationType' => 'binary', 'cast' => null],
            default => ['phpType' => 'string', 'migrationType' => 'string', 'cast' => null],
        };
    }

    /**
     * Process table indexes.
     */
    private function processIndexes(array $indexes): array
    {
        $processed = [];

        foreach ($indexes as $index) {
            $processed[] = [
                'name' => $index['name'],
                'columns' => $index['columns'],
                'unique' => $index['unique'],
                'primary' => $index['primary'] ?? false,
            ];
        }

        return $processed;
    }

    /**
     * Detect relationships between tables based on foreign keys.
     */
    private function detectRelationships(): void
    {
        foreach ($this->entities as $modelName => &$entity) {
            foreach ($entity['fields'] as $fieldName => $_field) {
                // Check for foreign key pattern (*_id)
                if (str_ends_with($fieldName, '_id') && $fieldName !== 'id') {
                    $relatedTable = str_replace('_id', '', $fieldName);
                    $relatedModelName = $this->relationshipDetector->toPascalCase(
                        $this->relationshipDetector->singularize($relatedTable)
                    );

                    // Add belongsTo relationship
                    $entity['relationships'][$relatedTable] = [
                        'type' => 'belongsTo',
                        'related' => $relatedModelName,
                        'method' => $this->relationshipDetector->toCamelCase($relatedTable),
                        'foreignKey' => $fieldName,
                        'ownerKey' => 'id',
                    ];

                    // Add hasMany relationship to related entity if exists
                    if (isset($this->entities[$relatedModelName])) {
                        $pluralMethod = $this->relationshipDetector->pluralize(
                            $this->relationshipDetector->toCamelCase(
                                $this->relationshipDetector->toSnakeCase($modelName)
                            )
                        );

                        $this->entities[$relatedModelName]['relationships'][$pluralMethod] = [
                            'type' => 'hasMany',
                            'related' => $modelName,
                            'method' => $pluralMethod,
                            'foreignKey' => $fieldName,
                            'localKey' => 'id',
                        ];
                    }
                }
            }
        }
    }

    /**
     * Parse specific tables only.
     */
    public function parseTables(array $tables): array
    {
        return $this->parse(implode(',', $tables));
    }

    /**
     * Get parsed entities.
     */
    public function getEntities(): array
    {
        return $this->entities;
    }
}
