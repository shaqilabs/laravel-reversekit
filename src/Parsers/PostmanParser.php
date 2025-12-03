<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Parsers;

use InvalidArgumentException;
use Shaqi\ReverseKit\Contracts\ParserInterface;
use Shaqi\ReverseKit\Support\RelationshipDetector;
use Shaqi\ReverseKit\Support\TypeInferrer;

class PostmanParser implements ParserInterface
{
    private array $entities = [];
    private array $collection = [];

    public function __construct(
        private TypeInferrer $typeInferrer,
        private RelationshipDetector $relationshipDetector
    ) {
    }

    /**
     * Parse Postman collection file.
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

        return $this->parseContent($content);
    }

    /**
     * Parse Postman collection JSON content.
     *
     * @throws InvalidArgumentException
     */
    public function parseContent(string $content): array
    {
        $this->collection = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        $this->validateCollection();
        $this->extractEntities();

        return $this->entities;
    }

    /**
     * Validate Postman collection structure.
     */
    private function validateCollection(): void
    {
        if (!isset($this->collection['info']) || !isset($this->collection['item'])) {
            throw new InvalidArgumentException(
                'Invalid Postman collection format. Missing "info" or "item" field.'
            );
        }
    }

    /**
     * Extract entities from Postman collection requests.
     */
    private function extractEntities(): void
    {
        $this->entities = [];
        $this->processItems($this->collection['item']);
    }

    /**
     * Process collection items recursively.
     */
    private function processItems(array $items): void
    {
        foreach ($items as $item) {
            // If item has nested items, it's a folder
            if (isset($item['item'])) {
                $this->processItems($item['item']);
                continue;
            }

            // Process request
            if (isset($item['request'])) {
                $this->processRequest($item);
            }
        }
    }

    /**
     * Process a single request and extract entity info.
     */
    private function processRequest(array $item): void
    {
        $request = $item['request'];
        $response = $item['response'][0] ?? null;

        // Try to get entity name from URL
        $entityName = $this->extractEntityFromUrl($request['url'] ?? '');
        if (!$entityName) {
            return;
        }

        $modelName = $this->relationshipDetector->toPascalCase(
            $this->relationshipDetector->singularize($entityName)
        );

        // Skip if already processed
        if (isset($this->entities[$modelName])) {
            // Merge any new fields from response
            if ($response) {
                $this->mergeFieldsFromResponse($modelName, $response);
            }
            return;
        }

        // Extract fields from request body and response
        $fields = [];
        $casts = [];

        // From request body
        if (isset($request['body'])) {
            $bodyFields = $this->extractFieldsFromBody($request['body']);
            $fields = array_merge($fields, $bodyFields);
        }

        // From response
        if ($response) {
            $responseFields = $this->extractFieldsFromResponse($response);
            $fields = array_merge($responseFields, $fields); // Response fields take priority
        }

        if (empty($fields)) {
            return;
        }

        foreach ($fields as $fieldName => $field) {
            if ($field['cast']) {
                $casts[$fieldName] = $field['cast'];
            }
        }

        $tableName = $this->relationshipDetector->pluralize(
            $this->relationshipDetector->toSnakeCase($entityName)
        );

        $this->entities[$modelName] = [
            'name' => $modelName,
            'table' => $tableName,
            'fields' => $fields,
            'relationships' => [],
            'casts' => $casts,
            'parent' => null,
            'hasUserId' => isset($fields['user_id']),
        ];
    }

    /**
     * Extract entity name from URL.
     */
    private function extractEntityFromUrl(string|array $url): ?string
    {
        if (is_array($url)) {
            $path = $url['path'] ?? [];
            $urlString = implode('/', $path);
        } else {
            $urlString = $url;
        }

        // Match patterns like /api/users, /users/:id, /v1/posts
        if (preg_match('/\/(?:api\/)?(?:v\d+\/)?(\w+)(?:\/|$)/', $urlString, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract fields from request body.
     */
    private function extractFieldsFromBody(array $body): array
    {
        $fields = [];
        $mode = $body['mode'] ?? 'raw';

        if ($mode === 'raw' && isset($body['raw'])) {
            $data = json_decode($body['raw'], true);
            if (is_array($data)) {
                $fields = $this->processDataToFields($data);
            }
        } elseif ($mode === 'urlencoded' && isset($body['urlencoded'])) {
            foreach ($body['urlencoded'] as $param) {
                $fields[$param['key']] = $this->createFieldFromValue(
                    $param['key'],
                    $param['value'] ?? ''
                );
            }
        } elseif ($mode === 'formdata' && isset($body['formdata'])) {
            foreach ($body['formdata'] as $param) {
                if ($param['type'] === 'file') {
                    continue; // Skip file fields
                }
                $fields[$param['key']] = $this->createFieldFromValue(
                    $param['key'],
                    $param['value'] ?? ''
                );
            }
        }

        return $fields;
    }

    /**
     * Extract fields from response.
     */
    private function extractFieldsFromResponse(array $response): array
    {
        $body = $response['body'] ?? '';
        $data = json_decode($body, true);

        if (!is_array($data)) {
            return [];
        }

        // Handle wrapped responses like { "data": {...} }
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        // Handle array responses - take first item
        if (isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        return $this->processDataToFields($data);
    }

    /**
     * Process JSON data into fields array.
     */
    private function processDataToFields(array $data): array
    {
        $fields = [];

        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                continue;
            }

            // Skip nested arrays/objects for now (they become relationships)
            if (is_array($value) && ($this->relationshipDetector->isArrayOfObjects($value) || $this->typeInferrer->isAssociativeArray($value))) {
                continue;
            }

            $fields[$key] = $this->createFieldFromValue($key, $value);
        }

        return $fields;
    }

    /**
     * Create field definition from value.
     */
    private function createFieldFromValue(string $fieldName, mixed $value): array
    {
        $phpType = $this->typeInferrer->inferPhpType($value);
        $migration = $this->typeInferrer->inferMigrationType($value, $fieldName);
        $cast = $this->typeInferrer->getCastType($value, $fieldName);

        return [
            'name' => $fieldName,
            'value' => $value,
            'phpType' => $phpType,
            'migration' => $migration,
            'cast' => $cast,
            'nullable' => is_null($value),
        ];
    }

    /**
     * Merge new fields from response into existing entity.
     */
    private function mergeFieldsFromResponse(string $modelName, array $response): void
    {
        $newFields = $this->extractFieldsFromResponse($response);

        foreach ($newFields as $fieldName => $field) {
            if (!isset($this->entities[$modelName]['fields'][$fieldName])) {
                $this->entities[$modelName]['fields'][$fieldName] = $field;
                if ($field['cast']) {
                    $this->entities[$modelName]['casts'][$fieldName] = $field['cast'];
                }
            }
        }
    }

    /**
     * Get parsed entities.
     */
    public function getEntities(): array
    {
        return $this->entities;
    }
}
