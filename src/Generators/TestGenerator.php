<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Generators;

class TestGenerator extends BaseGenerator
{
    /**
     * Generate a feature test file.
     */
    public function generate(array $entity, bool $force = false): string
    {
        $testName = $entity['name'] . 'Test';
        $path = base_path('tests/Feature/' . $testName . '.php');

        if (! $force && $this->fileExists($path)) {
            return "skipped:{$path}";
        }

        $content = $this->generateContent($entity);
        $this->writeFile($path, $content, $force);

        return $path;
    }

    /**
     * Generate test content.
     */
    private function generateContent(array $entity): string
    {
        $modelName = $entity['name'];
        $testName = $modelName . 'Test';
        $modelNamespace = $this->getNamespace('Models') . '\\' . $modelName;
        $modelVar = lcfirst($modelName);
        $routeBase = $this->relationshipDetector->pluralize(
            $this->relationshipDetector->toSnakeCase($modelName)
        );

        $factoryData = $this->generateFactoryData($entity['fields']);
        $updateData = $this->generateUpdateData($entity['fields']);
        $assertFields = $this->generateAssertFields($entity['fields']);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\\Feature;

use {$modelNamespace};
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class {$testName} extends TestCase
{
    use RefreshDatabase;

    /**
     * Test listing {$modelVar}s.
     */
    public function test_can_list_{$routeBase}(): void
    {
        \$user = User::factory()->create();
        {$modelName}::factory()->count(3)->create();

        \$response = \$this->actingAs(\$user)
            ->getJson('/api/{$routeBase}');

        \$response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [{$assertFields}],
                ],
            ]);
    }

    /**
     * Test creating a {$modelVar}.
     */
    public function test_can_create_{$modelVar}(): void
    {
        \$user = User::factory()->create();

        \$data = {$factoryData};

        \$response = \$this->actingAs(\$user)
            ->postJson('/api/{$routeBase}', \$data);

        \$response->assertCreated()
            ->assertJsonFragment(\$data);
    }

    /**
     * Test showing a {$modelVar}.
     */
    public function test_can_show_{$modelVar}(): void
    {
        \$user = User::factory()->create();
        \${$modelVar} = {$modelName}::factory()->create();

        \$response = \$this->actingAs(\$user)
            ->getJson("/api/{$routeBase}/{\${$modelVar}->id}");

        \$response->assertOk()
            ->assertJsonStructure([
                'data' => [{$assertFields}],
            ]);
    }

    /**
     * Test updating a {$modelVar}.
     */
    public function test_can_update_{$modelVar}(): void
    {
        \$user = User::factory()->create();
        \${$modelVar} = {$modelName}::factory()->create();

        \$data = {$updateData};

        \$response = \$this->actingAs(\$user)
            ->putJson("/api/{$routeBase}/{\${$modelVar}->id}", \$data);

        \$response->assertOk()
            ->assertJsonFragment(\$data);
    }

    /**
     * Test deleting a {$modelVar}.
     */
    public function test_can_delete_{$modelVar}(): void
    {
        \$user = User::factory()->create();
        \${$modelVar} = {$modelName}::factory()->create();

        \$response = \$this->actingAs(\$user)
            ->deleteJson("/api/{$routeBase}/{\${$modelVar}->id}");

        \$response->assertNoContent();
        \$this->assertDatabaseMissing('{$entity['table']}', ['id' => \${$modelVar}->id]);
    }
}
PHP;
    }

    /**
     * Generate factory data array.
     */
    private function generateFactoryData(array $fields): string
    {
        $data = [];

        foreach ($fields as $fieldName => $field) {
            if (in_array($fieldName, ['id', 'created_at', 'updated_at'])) {
                continue;
            }

            $value = $this->getSampleValue($fieldName, $field);
            $data[] = "            '{$fieldName}' => {$value},";
        }

        if (empty($data)) {
            return '[]';
        }

        return "[\n" . implode("\n", $data) . "\n        ]";
    }

    /**
     * Generate update data array.
     */
    private function generateUpdateData(array $fields): string
    {
        $data = [];

        foreach ($fields as $fieldName => $field) {
            if (in_array($fieldName, ['id', 'created_at', 'updated_at'])) {
                continue;
            }
            if (str_ends_with($fieldName, '_id')) {
                continue;
            }

            $value = $this->getUpdatedValue($fieldName, $field);
            $data[] = "            '{$fieldName}' => {$value},";

            if (count($data) >= 2) {
                break;
            }
        }

        if (empty($data)) {
            return '[]';
        }

        return "[\n" . implode("\n", $data) . "\n        ]";
    }

    /**
     * Generate assertion fields list.
     */
    private function generateAssertFields(array $fields): string
    {
        $fieldNames = array_filter(
            array_keys($fields),
            fn ($name) => !in_array($name, ['created_at', 'updated_at'])
        );

        return "'" . implode("', '", $fieldNames) . "'";
    }

    /**
     * Get sample value for field.
     */
    private function getSampleValue(string $fieldName, array $field): string
    {
        $type = $field['migration']['type'];

        return match ($type) {
            'boolean' => 'true',
            'integer', 'bigInteger' => '1',
            'unsignedBigInteger' => '1',
            'decimal', 'float' => '10.50',
            default => "'Test {$fieldName}'",
        };
    }

    /**
     * Get updated value for field.
     */
    private function getUpdatedValue(string $fieldName, array $field): string
    {
        $type = $field['migration']['type'];

        return match ($type) {
            'boolean' => 'false',
            'integer', 'bigInteger' => '2',
            'decimal', 'float' => '20.00',
            default => "'Updated {$fieldName}'",
        };
    }
}
