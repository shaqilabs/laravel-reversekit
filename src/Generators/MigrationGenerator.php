<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Generators;

class MigrationGenerator extends BaseGenerator
{
    private static int $migrationOrder = 0;

    /**
     * Generate a migration file.
     */
    public function generate(array $entity, bool $force = false): string
    {
        self::$migrationOrder++;
        $tableName = $entity['table'];
        $timestamp = date('Y_m_d_His', time() + self::$migrationOrder);
        $filename = "{$timestamp}_create_{$tableName}_table.php";
        $path = database_path('migrations/' . $filename);

        $existed = $this->checkExistingMigration($tableName);

        if ($existed && ! $force) {
            return "skipped:{$path}";
        }

        if ($existed && $force) {
            $this->removeExistingMigration($tableName);
        }

        $content = $this->generateContent($entity);
        $this->writeFile($path, $content, true);

        return $path;
    }

    /**
     * Check if migration for table exists.
     */
    private function checkExistingMigration(string $tableName): bool
    {
        $pattern = database_path("migrations/*_create_{$tableName}_table.php");
        return !empty(glob($pattern));
    }

    /**
     * Remove existing migration for table.
     */
    private function removeExistingMigration(string $tableName): void
    {
        $pattern = database_path("migrations/*_create_{$tableName}_table.php");
        foreach (glob($pattern) as $file) {
            unlink($file);
        }
    }

    /**
     * Generate migration content.
     */
    private function generateContent(array $entity): string
    {
        $tableName = $entity['table'];
        $columns = $this->generateColumns($entity['fields']);

        return <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
{$columns}
            \$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
PHP;
    }

    /**
     * Generate column definitions.
     */
    private function generateColumns(array $fields): string
    {
        $columns = [];

        foreach ($fields as $fieldName => $field) {
            // Skip id, created_at, updated_at
            if (in_array($fieldName, ['id', 'created_at', 'updated_at'])) {
                continue;
            }

            $column = $this->generateColumn($fieldName, $field);
            if ($column) {
                $columns[] = $column;
            }
        }

        return implode("\n", $columns);
    }

    /**
     * Generate a single column definition.
     */
    private function generateColumn(string $fieldName, array $field): string
    {
        $migration = $field['migration'];
        $type = $migration['type'];
        $nullable = $migration['nullable'] ?? false;

        $columnDef = match ($type) {
            'string' => "\$table->string('{$fieldName}')",
            'text' => "\$table->text('{$fieldName}')",
            'integer' => "\$table->integer('{$fieldName}')",
            'unsignedBigInteger' => $this->generateForeignKey($fieldName),
            'bigInteger' => "\$table->bigInteger('{$fieldName}')",
            'boolean' => "\$table->boolean('{$fieldName}')",
            'decimal' => "\$table->decimal('{$fieldName}', " .
                ($migration['precision'] ?? 10) . ", " .
                ($migration['scale'] ?? 2) . ")",
            'float' => "\$table->float('{$fieldName}')",
            'date' => "\$table->date('{$fieldName}')",
            'datetime', 'timestamp' => "\$table->timestamp('{$fieldName}')",
            'time' => "\$table->time('{$fieldName}')",
            'json' => "\$table->json('{$fieldName}')",
            'uuid' => "\$table->uuid('{$fieldName}')",
            default => "\$table->string('{$fieldName}')",
        };

        // Add nullable modifier
        if ($nullable && $type !== 'unsignedBigInteger') {
            $columnDef .= '->nullable()';
        }

        // Add default for boolean
        if ($type === 'boolean' && isset($field['value'])) {
            $default = $field['value'] ? 'true' : 'false';
            $columnDef .= "->default({$default})";
        }

        return "            {$columnDef};";
    }

    /**
     * Generate foreign key column.
     */
    private function generateForeignKey(string $fieldName): string
    {
        // Extract table name from field (e.g., user_id -> users)
        $relatedTable = $this->relationshipDetector->pluralize(
            str_replace('_id', '', $fieldName)
        );

        return "\$table->foreignId('{$fieldName}')" .
            "->constrained('{$relatedTable}')" .
            "->cascadeOnDelete()";
    }

    /**
     * Reset migration order (for testing).
     */
    public static function resetOrder(): void
    {
        self::$migrationOrder = 0;
    }
}
