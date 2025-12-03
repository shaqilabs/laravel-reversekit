<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Commands;

use Illuminate\Console\Command;
use Shaqi\ReverseKit\Generators\ControllerGenerator;
use Shaqi\ReverseKit\Generators\FactoryGenerator;
use Shaqi\ReverseKit\Generators\FormRequestGenerator;
use Shaqi\ReverseKit\Generators\MigrationGenerator;
use Shaqi\ReverseKit\Generators\ModelGenerator;
use Shaqi\ReverseKit\Generators\PolicyGenerator;
use Shaqi\ReverseKit\Generators\ResourceGenerator;
use Shaqi\ReverseKit\Generators\RouteGenerator;
use Shaqi\ReverseKit\Generators\SeederGenerator;
use Shaqi\ReverseKit\Generators\TestGenerator;
use Shaqi\ReverseKit\Support\RelationshipDetector;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class ReverseInteractiveCommand extends Command
{
    protected $signature = 'reverse:interactive
                            {--module= : Module/domain name prefix}
                            {--namespace=App : Custom namespace}
                            {--force : Overwrite existing files without confirmation}';

    protected $description = 'Interactively generate Laravel backend scaffolding';

    private array $entities = [];
    private array $fieldTypes = [
        'string' => ['migration' => 'string', 'cast' => null],
        'text' => ['migration' => 'text', 'cast' => null],
        'integer' => ['migration' => 'integer', 'cast' => null],
        'bigInteger' => ['migration' => 'bigInteger', 'cast' => null],
        'float' => ['migration' => 'float', 'cast' => 'float'],
        'decimal' => ['migration' => 'decimal', 'cast' => 'float'],
        'boolean' => ['migration' => 'boolean', 'cast' => 'boolean'],
        'date' => ['migration' => 'date', 'cast' => 'date'],
        'datetime' => ['migration' => 'dateTime', 'cast' => 'datetime'],
        'timestamp' => ['migration' => 'timestamp', 'cast' => 'datetime'],
        'json' => ['migration' => 'json', 'cast' => 'array'],
        'uuid' => ['migration' => 'uuid', 'cast' => null],
        'email' => ['migration' => 'string', 'cast' => null],
        'password' => ['migration' => 'string', 'cast' => null],
    ];

    public function handle(
        RelationshipDetector $relationshipDetector,
        ModelGenerator $modelGenerator,
        MigrationGenerator $migrationGenerator,
        ControllerGenerator $controllerGenerator,
        ResourceGenerator $resourceGenerator,
        FormRequestGenerator $formRequestGenerator,
        PolicyGenerator $policyGenerator,
        FactoryGenerator $factoryGenerator,
        SeederGenerator $seederGenerator,
        TestGenerator $testGenerator,
        RouteGenerator $routeGenerator
    ): int {
        $this->info('🚀 Laravel ReverseKit - Interactive Mode');
        $this->info('   by Shaqi Labs');
        $this->newLine();

        // Step 1: Collect entities
        $this->collectEntities($relationshipDetector);

        if (empty($this->entities)) {
            $this->warn('⚠️ No entities defined. Exiting.');
            return Command::FAILURE;
        }

        // Step 2: Define relationships
        $this->defineRelationships($relationshipDetector);

        // Step 3: Select what to generate
        $generators = $this->selectGenerators();

        // Step 4: Configure and generate
        $namespace = $this->option('namespace') ?? 'App';
        $module = $this->option('module') ?? '';
        $force = $this->option('force') ?? false;

        $allGenerators = [
            'model' => $modelGenerator,
            'migration' => $migrationGenerator,
            'controller' => $controllerGenerator,
            'resource' => $resourceGenerator,
            'request' => $formRequestGenerator,
            'policy' => $policyGenerator,
            'factory' => $factoryGenerator,
            'seeder' => $seederGenerator,
            'test' => $testGenerator,
        ];

        $selectedGenerators = array_intersect_key($allGenerators, array_flip($generators));

        foreach ($selectedGenerators as $generator) {
            $generator->setNamespace($namespace)
                ->setModule($module)
                ->setAllEntities($this->entities);
        }

        $routeGenerator->setNamespace($namespace)
            ->setModule($module)
            ->setAllEntities($this->entities);

        // Generate files
        $this->newLine();
        $this->info('📦 Generating scaffolding...');
        $this->newLine();

        $createdCount = 0;
        foreach ($this->entities as $entityName => $entity) {
            $this->info("📦 Generating scaffolding for: {$entityName}");

            foreach ($selectedGenerators as $type => $generator) {
                $result = $generator->generate($entity, $force);
                if ($result) {
                    $this->line("   ✓ {$type}: {$result}");
                    $createdCount++;
                }
            }
        }

        // Generate routes
        if (in_array('routes', $generators)) {
            $result = $routeGenerator->generate([], $force);
            if ($result) {
                $this->info("📦 Routes: {$result}");
                $createdCount++;
            }
        }

        $this->newLine();
        $this->info("✅ Generated {$createdCount} files successfully!");

        return Command::SUCCESS;
    }

    /**
     * Collect entities from user.
     */
    private function collectEntities(RelationshipDetector $relationshipDetector): void
    {
        $this->info('📋 Step 1: Define your entities (models)');
        $this->newLine();

        do {
            $entityName = text(
                label: 'Enter model name (e.g., User, BlogPost)',
                placeholder: 'User',
                required: true,
                validate: fn (string $value) => preg_match('/^[A-Z][a-zA-Z0-9]*$/', $value)
                    ? null
                    : 'Model name must be PascalCase (e.g., User, BlogPost)'
            );

            $tableName = $relationshipDetector->pluralize(
                $relationshipDetector->toSnakeCase($entityName)
            );

            $this->info("  → Table name will be: {$tableName}");
            $this->newLine();

            // Collect fields
            $fields = $this->collectFields($entityName);

            $this->entities[$entityName] = [
                'name' => $entityName,
                'table' => $tableName,
                'fields' => $fields,
                'relationships' => [],
                'casts' => $this->extractCasts($fields),
                'parent' => null,
                'hasUserId' => isset($fields['user_id']),
            ];

            $this->info("  ✓ Entity '{$entityName}' added with " . count($fields) . " fields");
            $this->newLine();

            $addMore = confirm(
                label: 'Add another entity?',
                default: false
            );
        } while ($addMore);
    }

    /**
     * Collect fields for an entity.
     */
    private function collectFields(string $entityName): array
    {
        $this->info("  📝 Define fields for {$entityName}:");
        $fields = [];

        // Always add id field
        $fields['id'] = [
            'name' => 'id',
            'value' => 1,
            'phpType' => 'int',
            'migration' => ['type' => 'id', 'nullable' => false],
            'cast' => null,
            'nullable' => false,
        ];

        do {
            $fieldName = text(
                label: 'Field name (snake_case)',
                placeholder: 'title',
                required: true,
                validate: fn (string $value) => preg_match('/^[a-z][a-z0-9_]*$/', $value)
                    ? null
                    : 'Field name must be snake_case (e.g., title, created_at)'
            );

            $fieldType = select(
                label: "Type for '{$fieldName}'",
                options: array_keys($this->fieldTypes),
                default: $this->guessFieldType($fieldName)
            );

            $nullable = confirm(
                label: 'Is this field nullable?',
                default: false
            );

            $typeInfo = $this->fieldTypes[$fieldType];
            $phpType = $this->getPhpType($fieldType);

            $fields[$fieldName] = [
                'name' => $fieldName,
                'value' => $this->getSampleValue($fieldType, $fieldName),
                'phpType' => $phpType,
                'migration' => [
                    'type' => $typeInfo['migration'],
                    'nullable' => $nullable,
                ],
                'cast' => $typeInfo['cast'],
                'nullable' => $nullable,
            ];

            $this->line("     + {$fieldName} ({$fieldType}" . ($nullable ? ', nullable' : '') . ')');

            $addMore = confirm(
                label: 'Add another field?',
                default: true
            );
        } while ($addMore);

        return $fields;
    }

    /**
     * Define relationships between entities.
     */
    private function defineRelationships(RelationshipDetector $relationshipDetector): void
    {
        if (count($this->entities) < 2) {
            return;
        }

        $this->newLine();
        $this->info('🔗 Step 2: Define relationships');
        $this->newLine();

        $addRelationships = confirm(
            label: 'Would you like to define relationships between entities?',
            default: true
        );

        if (!$addRelationships) {
            return;
        }

        $entityNames = array_keys($this->entities);

        do {
            $fromEntity = select(
                label: 'Select the parent entity (the "one" side)',
                options: $entityNames
            );

            $toEntity = select(
                label: 'Select the child entity (the "many" side)',
                options: array_diff($entityNames, [$fromEntity])
            );

            $relationshipType = select(
                label: 'Relationship type',
                options: [
                    'hasMany' => "One {$fromEntity} has many {$toEntity}s",
                    'hasOne' => "One {$fromEntity} has one {$toEntity}",
                    'belongsToMany' => "{$fromEntity} belongs to many {$toEntity}s (pivot table)",
                ],
                default: 'hasMany'
            );

            // Add relationship to parent entity
            $methodName = $relationshipDetector->pluralize(
                $relationshipDetector->toCamelCase(
                    $relationshipDetector->toSnakeCase($toEntity)
                )
            );

            if ($relationshipType === 'hasOne') {
                $methodName = $relationshipDetector->toCamelCase(
                    $relationshipDetector->toSnakeCase($toEntity)
                );
            }

            $foreignKey = $relationshipDetector->toSnakeCase($fromEntity) . '_id';

            $this->entities[$fromEntity]['relationships'][$methodName] = [
                'type' => $relationshipType,
                'related' => $toEntity,
                'method' => $methodName,
                'foreignKey' => $foreignKey,
                'localKey' => 'id',
            ];

            // Add belongsTo to child entity
            $belongsMethod = $relationshipDetector->toCamelCase(
                $relationshipDetector->toSnakeCase($fromEntity)
            );

            $this->entities[$toEntity]['relationships'][$belongsMethod] = [
                'type' => 'belongsTo',
                'related' => $fromEntity,
                'method' => $belongsMethod,
                'foreignKey' => $foreignKey,
                'ownerKey' => 'id',
            ];

            // Add foreign key field to child if not exists
            if (!isset($this->entities[$toEntity]['fields'][$foreignKey])) {
                $this->entities[$toEntity]['fields'][$foreignKey] = [
                    'name' => $foreignKey,
                    'value' => 1,
                    'phpType' => 'int',
                    'migration' => ['type' => 'foreignId', 'nullable' => false],
                    'cast' => null,
                    'nullable' => false,
                ];
            }

            $this->info("  ✓ {$fromEntity} {$relationshipType} {$toEntity}");

            $addMore = confirm(
                label: 'Add another relationship?',
                default: false
            );
        } while ($addMore);
    }

    /**
     * Select what to generate.
     */
    private function selectGenerators(): array
    {
        $this->newLine();
        $this->info('⚙️ Step 3: Select what to generate');
        $this->newLine();

        return multiselect(
            label: 'What would you like to generate?',
            options: [
                'model' => 'Models',
                'migration' => 'Migrations',
                'controller' => 'Controllers',
                'resource' => 'API Resources',
                'request' => 'Form Requests',
                'policy' => 'Policies',
                'factory' => 'Factories',
                'seeder' => 'Seeders',
                'test' => 'Feature Tests',
                'routes' => 'Routes',
            ],
            default: ['model', 'migration', 'controller', 'resource', 'request', 'factory', 'routes'],
            required: true
        );
    }

    /**
     * Guess field type from name.
     */
    private function guessFieldType(string $fieldName): string
    {
        return match (true) {
            str_ends_with($fieldName, '_id') => 'bigInteger',
            str_ends_with($fieldName, '_at') => 'datetime',
            str_contains($fieldName, 'email') => 'email',
            str_contains($fieldName, 'password') => 'password',
            str_contains($fieldName, 'is_') || str_contains($fieldName, 'has_') => 'boolean',
            str_contains($fieldName, 'price') || str_contains($fieldName, 'amount') => 'decimal',
            str_contains($fieldName, 'count') || str_contains($fieldName, 'views') => 'integer',
            in_array($fieldName, ['body', 'content', 'description', 'bio']) => 'text',
            str_contains($fieldName, 'date') => 'date',
            in_array($fieldName, ['data', 'meta', 'settings', 'options']) => 'json',
            str_contains($fieldName, 'uuid') => 'uuid',
            default => 'string',
        };
    }

    /**
     * Get PHP type from field type.
     */
    private function getPhpType(string $fieldType): string
    {
        return match ($fieldType) {
            'integer', 'bigInteger' => 'int',
            'float', 'decimal' => 'float',
            'boolean' => 'bool',
            'json' => 'array',
            default => 'string',
        };
    }

    /**
     * Get sample value for field type.
     */
    private function getSampleValue(string $fieldType, string $fieldName): mixed
    {
        return match ($fieldType) {
            'integer', 'bigInteger' => 1,
            'float', 'decimal' => 19.99,
            'boolean' => true,
            'json' => [],
            'email' => 'user@example.com',
            'password' => 'password123',
            'date' => '2024-01-01',
            'datetime', 'timestamp' => '2024-01-01T00:00:00Z',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'text' => 'Lorem ipsum dolor sit amet...',
            default => ucfirst(str_replace('_', ' ', $fieldName)),
        };
    }

    /**
     * Extract casts from fields.
     */
    private function extractCasts(array $fields): array
    {
        $casts = [];
        foreach ($fields as $fieldName => $field) {
            if (!empty($field['cast'])) {
                $casts[$fieldName] = $field['cast'];
            }
        }
        return $casts;
    }
}
