<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Generators;

class FormRequestGenerator extends BaseGenerator
{
    /**
     * Generate Store and Update Form Request classes.
     *
     * @param array<string, mixed> $entity
     * @return array<string, string>
     */
    public function generate(array $entity, bool $force = false): array
    {
        $results = [];

        $results['store'] = $this->generateRequest($entity, 'Store', $force);
        $results['update'] = $this->generateRequest($entity, 'Update', $force);

        return $results;
    }

    /**
     * Generate a specific request class.
     */
    protected function generateRequest(array $entity, string $type, bool $force): string
    {
        $name = $entity['name'];
        $className = "{$type}{$name}Request";
        $path = $this->getPath($className);

        if (! $force && $this->filesystem->exists($path)) {
            return "skipped:{$path}";
        }

        $stub = $this->getStub('request');
        $content = $this->buildContent($stub, $entity, $type, $className);

        $this->writeFile($path, $content, $force);

        return $path;
    }

    /**
     * Build the request content.
     */
    protected function buildContent(string $stub, array $entity, string $type, string $className): string
    {
        $namespace = $this->getNamespace('Http\\Requests');
        $rules = $this->buildRules($entity, $type);
        $attributes = $this->buildAttributes($entity);

        return str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ rules }}', '{{ attributes }}'],
            [$namespace, $className, $rules, $attributes],
            $stub
        );
    }

    /**
     * Build validation rules.
     */
    protected function buildRules(array $entity, string $type): string
    {
        $rules = [];
        $isUpdate = $type === 'Update';

        foreach ($entity['fields'] as $fieldName => $field) {
            if ($fieldName === 'id') {
                continue;
            }

            $rule = $this->buildFieldRule($fieldName, $field, $isUpdate);
            $rules[] = "            '{$fieldName}' => '{$rule}',";
        }

        return implode("\n", $rules);
    }

    /**
     * Build rule for a single field.
     */
    protected function buildFieldRule(string $fieldName, array $field, bool $isUpdate): string
    {
        $parts = [];
        $phpType = $field['phpType'] ?? $field['php_type'] ?? 'string';

        // Required/Sometimes
        if ($isUpdate) {
            $parts[] = 'sometimes';
        } else {
            $parts[] = $field['nullable'] ?? false ? 'nullable' : 'required';
        }

        // Type rules
        $parts[] = match ($phpType) {
            'int', 'integer' => 'integer',
            'float' => 'numeric',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            default => 'string',
        };

        // Additional rules based on field name/type
        if (str_contains($fieldName, 'email')) {
            $parts[] = 'email';
        }

        if (str_contains($fieldName, 'url') || str_contains($fieldName, 'link')) {
            $parts[] = 'url';
        }

        if ($phpType === 'string' && ! str_contains($fieldName, 'body') && ! str_contains($fieldName, 'content') && ! str_contains($fieldName, 'description')) {
            $parts[] = 'max:255';
        }

        if (str_ends_with($fieldName, '_id')) {
            $parts[] = 'min:1';
        }

        return implode('|', $parts);
    }

    /**
     * Build attributes for custom error messages.
     */
    protected function buildAttributes(array $entity): string
    {
        $attributes = [];

        foreach ($entity['fields'] as $fieldName => $field) {
            if ($fieldName === 'id') {
                continue;
            }

            $label = ucwords(str_replace('_', ' ', $fieldName));
            $attributes[] = "            '{$fieldName}' => '{$label}',";
        }

        return implode("\n", $attributes);
    }

    /**
     * Get the path for the request class.
     */
    protected function getPath(string $className): string
    {
        return $this->getBasePath() . '/Http/Requests/' . $className . '.php';
    }
}
