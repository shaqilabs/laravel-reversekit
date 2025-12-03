<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Support;

class TypeInferrer
{
    /**
     * ISO 8601 date/time pattern.
     */
    private const ISO8601_PATTERN = '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})?)?$/';

    /**
     * Date-only pattern.
     */
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    /**
     * UUID pattern.
     */
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Email pattern.
     */
    private const EMAIL_PATTERN = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';

    /**
     * Infer the PHP type from a JSON value.
     */
    public function inferPhpType(mixed $value): string
    {
        if (is_null($value)) {
            return 'string';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_array($value)) {
            if ($this->isAssociativeArray($value)) {
                return 'object';
            }
            return 'array';
        }

        if (is_string($value)) {
            return $this->inferStringType($value);
        }

        return 'string';
    }

    /**
     * Infer the migration column type from a JSON value.
     */
    public function inferMigrationType(mixed $value, string $fieldName = ''): array
    {
        if (is_null($value)) {
            return ['type' => 'string', 'nullable' => true];
        }

        if (is_bool($value)) {
            return ['type' => 'boolean', 'nullable' => false];
        }

        if (is_int($value)) {
            if (str_ends_with($fieldName, '_id') || $fieldName === 'id') {
                return ['type' => 'unsignedBigInteger', 'nullable' => false];
            }
            return ['type' => 'integer', 'nullable' => false];
        }

        if (is_float($value)) {
            return ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'nullable' => false];
        }

        if (is_string($value)) {
            return $this->inferStringMigrationType($value, $fieldName);
        }

        if (is_array($value)) {
            return ['type' => 'json', 'nullable' => false];
        }

        return ['type' => 'string', 'nullable' => false];
    }

    /**
     * Infer string subtype.
     */
    private function inferStringType(string $value): string
    {
        if (preg_match(self::ISO8601_PATTERN, $value)) {
            return 'datetime';
        }

        if (preg_match(self::DATE_PATTERN, $value)) {
            return 'date';
        }

        return 'string';
    }

    /**
     * Infer migration type for string values.
     */
    private function inferStringMigrationType(string $value, string $fieldName): array
    {
        // Check for timestamps
        if (preg_match(self::ISO8601_PATTERN, $value) && str_contains($value, 'T')) {
            return ['type' => 'timestamp', 'nullable' => true];
        }

        // Check for dates
        if (preg_match(self::DATE_PATTERN, $value)) {
            return ['type' => 'date', 'nullable' => false];
        }

        // Check for UUIDs
        if (preg_match(self::UUID_PATTERN, $value)) {
            return ['type' => 'uuid', 'nullable' => false];
        }

        // Check for emails
        if (preg_match(self::EMAIL_PATTERN, $value) || $fieldName === 'email') {
            return ['type' => 'string', 'nullable' => false];
        }

        // Check for long text (body, content, description)
        $longTextFields = ['body', 'content', 'description', 'text', 'bio', 'summary'];
        if (in_array($fieldName, $longTextFields) || strlen($value) > 255) {
            return ['type' => 'text', 'nullable' => false];
        }

        return ['type' => 'string', 'nullable' => false];
    }

    /**
     * Get the cast type for a field.
     */
    public function getCastType(mixed $value, string $fieldName = ''): ?string
    {
        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value) && $fieldName !== 'id' && !str_ends_with($fieldName, '_id')) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'decimal:2';
        }

        if (is_string($value)) {
            if (preg_match(self::ISO8601_PATTERN, $value) && str_contains($value, 'T')) {
                return 'datetime';
            }
            if (preg_match(self::DATE_PATTERN, $value)) {
                return 'date';
            }
        }

        if (is_array($value) && !$this->isAssociativeArray($value)) {
            return 'array';
        }

        return null;
    }

    /**
     * Check if array is associative (object-like).
     */
    public function isAssociativeArray(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
