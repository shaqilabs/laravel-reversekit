<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Generators;

class ModelGenerator extends BaseGenerator
{
    /**
     * Generate a model file.
     */
    public function generate(array $entity, bool $force = false): string
    {
        $modelName = $entity['name'];
        $path = $this->getBasePath() . '/Models/' . $modelName . '.php';

        if (! $force && $this->fileExists($path)) {
            return "skipped:{$path}";
        }

        $content = $this->generateContent($entity);
        $this->writeFile($path, $content, $force);

        return $path;
    }

    /**
     * Generate model content.
     */
    private function generateContent(array $entity): string
    {
        $modelName = $entity['name'];
        $namespace = $this->getNamespace('Models');

        $fillable = $this->generateFillable($entity['fields']);
        $casts = $this->generateCasts($entity['casts'] ?? []);
        $relationships = $this->generateRelationships($entity['relationships'] ?? []);

        $uses = [
            'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
            'Illuminate\\Database\\Eloquent\\Model',
        ];

        // Add relationship model imports
        foreach ($entity['relationships'] ?? [] as $relation) {
            $relatedClass = $this->getNamespace('Models') . '\\' . $relation['related'];
            if (!in_array($relatedClass, $uses)) {
                $uses[] = $relatedClass;
            }
        }

        // Check if we need Relations imports
        $hasRelations = !empty($entity['relationships']);
        if ($hasRelations) {
            $uses[] = 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo';
            $uses[] = 'Illuminate\\Database\\Eloquent\\Relations\\HasMany';
        }

        sort($uses);
        $useStatements = implode("\n", array_map(fn ($use) => "use {$use};", $uses));

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

{$useStatements}

class {$modelName} extends Model
{
    use HasFactory;

{$fillable}

{$casts}
{$relationships}
}
PHP;

        return $content;
    }

    /**
     * Generate fillable array.
     */
    private function generateFillable(array $fields): string
    {
        $fillableFields = array_filter(
            array_keys($fields),
            fn ($name) => $name !== 'id' && !in_array($name, ['created_at', 'updated_at'])
        );

        if (empty($fillableFields)) {
            return "    protected \$fillable = [];";
        }

        $items = array_map(fn ($field) => "        '{$field}',", $fillableFields);
        $itemsString = implode("\n", $items);

        return <<<PHP
    protected \$fillable = [
{$itemsString}
    ];
PHP;
    }

    /**
     * Generate casts array.
     */
    private function generateCasts(array $casts): string
    {
        if (empty($casts)) {
            return "    protected \$casts = [];";
        }

        $items = [];
        foreach ($casts as $field => $cast) {
            $items[] = "        '{$field}' => '{$cast}',";
        }
        $itemsString = implode("\n", $items);

        return <<<PHP
    protected \$casts = [
{$itemsString}
    ];
PHP;
    }

    /**
     * Generate relationship methods.
     */
    private function generateRelationships(array $relationships): string
    {
        if (empty($relationships)) {
            return '';
        }

        $methods = [];

        foreach ($relationships as $name => $relation) {
            $methodName = $relation['method'];
            $relatedModel = $relation['related'];
            $type = $relation['type'];

            if ($type === 'hasMany') {
                $methods[] = $this->generateHasMany($methodName, $relatedModel, $relation);
            } elseif ($type === 'belongsTo') {
                $methods[] = $this->generateBelongsTo($methodName, $relatedModel, $relation);
            }
        }

        return "\n" . implode("\n\n", $methods);
    }

    /**
     * Generate hasMany relationship method.
     */
    private function generateHasMany(string $method, string $related, array $relation): string
    {
        $foreignKey = $relation['foreignKey'];

        return <<<PHP
    /**
     * Get the {$method} for this model.
     */
    public function {$method}(): HasMany
    {
        return \$this->hasMany({$related}::class, '{$foreignKey}');
    }
PHP;
    }

    /**
     * Generate belongsTo relationship method.
     */
    private function generateBelongsTo(string $method, string $related, array $relation): string
    {
        $foreignKey = $relation['foreignKey'];

        return <<<PHP
    /**
     * Get the {$method} that owns this model.
     */
    public function {$method}(): BelongsTo
    {
        return \$this->belongsTo({$related}::class, '{$foreignKey}');
    }
PHP;
    }
}
