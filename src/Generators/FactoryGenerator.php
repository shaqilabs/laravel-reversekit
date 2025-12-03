<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Generators;

class FactoryGenerator extends BaseGenerator
{
    /**
     * Generate a model factory.
     *
     * @param array<string, mixed> $entity
     */
    public function generate(array $entity, bool $force = false): string
    {
        $name = $entity['name'];
        $className = "{$name}Factory";
        $path = $this->getPath($className);

        if (! $force && $this->filesystem->exists($path)) {
            return "skipped:{$path}";
        }

        $stub = $this->getStub('factory');
        $content = $this->buildContent($stub, $entity, $className);

        $this->writeFile($path, $content, $force);

        return $path;
    }

    /**
     * Build the factory content.
     */
    protected function buildContent(string $stub, array $entity, string $className): string
    {
        $name = $entity['name'];
        $modelNamespace = $this->getNamespace('Models');
        $definitions = $this->buildDefinitions($entity);
        $imports = $this->buildImports($entity);
        $states = $this->buildStates($entity);

        return str_replace(
            [
                '{{ namespace }}',
                '{{ modelNamespace }}',
                '{{ model }}',
                '{{ class }}',
                '{{ definitions }}',
                '{{ imports }}',
                '{{ states }}',
            ],
            [
                'Database\\Factories',
                $modelNamespace,
                $name,
                $className,
                $definitions,
                $imports,
                $states,
            ],
            $stub
        );
    }

    /**
     * Build factory definitions.
     */
    protected function buildDefinitions(array $entity): string
    {
        $definitions = [];

        foreach ($entity['fields'] as $fieldName => $field) {
            if ($fieldName === 'id') {
                continue;
            }

            $faker = $this->getFakerMethod($fieldName, $field);
            $definitions[] = "            '{$fieldName}' => {$faker},";
        }

        return implode("\n", $definitions);
    }

    /**
     * Get appropriate Faker method for a field.
     */
    protected function getFakerMethod(string $fieldName, array $field): string
    {
        // Check for foreign keys
        if (str_ends_with($fieldName, '_id')) {
            $relatedModel = ucfirst($this->relationshipDetector->singularize(str_replace('_id', '', $fieldName)));
            return "\\{$this->getNamespace('Models')}\\{$relatedModel}::factory()";
        }

        // Check field name patterns
        if (str_contains($fieldName, 'email')) {
            return 'fake()->unique()->safeEmail()';
        }

        if (str_contains($fieldName, 'name') || $fieldName === 'name') {
            return 'fake()->name()';
        }

        if (str_contains($fieldName, 'title')) {
            return 'fake()->sentence()';
        }

        if (str_contains($fieldName, 'body') || str_contains($fieldName, 'content') || str_contains($fieldName, 'description')) {
            return 'fake()->paragraphs(3, true)';
        }

        if (str_contains($fieldName, 'url') || str_contains($fieldName, 'link')) {
            return 'fake()->url()';
        }

        if (str_contains($fieldName, 'phone')) {
            return 'fake()->phoneNumber()';
        }

        if (str_contains($fieldName, 'address')) {
            return 'fake()->address()';
        }

        if (str_contains($fieldName, 'city')) {
            return 'fake()->city()';
        }

        if (str_contains($fieldName, 'country')) {
            return 'fake()->country()';
        }

        if (str_contains($fieldName, 'zip') || str_contains($fieldName, 'postal')) {
            return 'fake()->postcode()';
        }

        if (str_contains($fieldName, 'image') || str_contains($fieldName, 'avatar') || str_contains($fieldName, 'photo')) {
            return 'fake()->imageUrl()';
        }

        if (str_contains($fieldName, 'password')) {
            return 'bcrypt(\'password\')';
        }

        // Fallback to type-based methods
        $phpType = $field['phpType'] ?? $field['php_type'] ?? 'string';
        return match ($phpType) {
            'int', 'integer' => 'fake()->numberBetween(1, 1000)',
            'float' => 'fake()->randomFloat(2, 1, 1000)',
            'bool', 'boolean' => 'fake()->boolean()',
            'array' => '[]',
            default => 'fake()->word()',
        };
    }

    /**
     * Build imports for factory.
     */
    protected function buildImports(array $entity): string
    {
        return '';
    }

    /**
     * Build state methods.
     */
    protected function buildStates(array $entity): string
    {
        $states = [];

        // Add common states based on field names
        foreach ($entity['fields'] as $fieldName => $field) {
            if ($fieldName === 'published' || $fieldName === 'is_published') {
                $states[] = $this->buildPublishedState($fieldName);
            }

            if ($fieldName === 'active' || $fieldName === 'is_active') {
                $states[] = $this->buildActiveState($fieldName);
            }
        }

        return implode("\n", $states);
    }

    /**
     * Build published state method.
     */
    protected function buildPublishedState(string $fieldName = 'published'): string
    {
        return <<<PHP

    /**
     * Indicate that the model is published.
     */
    public function published(): static
    {
        return \$this->state(fn (array \$attributes) => [
            '{$fieldName}' => true,
        ]);
    }

    /**
     * Indicate that the model is unpublished.
     */
    public function unpublished(): static
    {
        return \$this->state(fn (array \$attributes) => [
            '{$fieldName}' => false,
        ]);
    }
PHP;
    }

    /**
     * Build active state method.
     */
    protected function buildActiveState(string $fieldName = 'active'): string
    {
        return <<<PHP

    /**
     * Indicate that the model is active.
     */
    public function active(): static
    {
        return \$this->state(fn (array \$attributes) => [
            '{$fieldName}' => true,
        ]);
    }

    /**
     * Indicate that the model is inactive.
     */
    public function inactive(): static
    {
        return \$this->state(fn (array \$attributes) => [
            '{$fieldName}' => false,
        ]);
    }
PHP;
    }

    /**
     * Get the path for the factory class.
     */
    protected function getPath(string $className): string
    {
        return database_path('factories/' . $className . '.php');
    }
}
