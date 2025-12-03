<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit\Generators;

class PolicyGenerator extends BaseGenerator
{
    /**
     * Generate a policy file.
     */
    public function generate(array $entity, bool $force = false): string
    {
        $policyName = $entity['name'] . 'Policy';
        $path = $this->getBasePath() . '/Policies/' . $policyName . '.php';

        if (! $force && $this->fileExists($path)) {
            return "skipped:{$path}";
        }

        $content = $this->generateContent($entity);
        $this->writeFile($path, $content, $force);

        return $path;
    }

    /**
     * Generate policy content.
     */
    private function generateContent(array $entity): string
    {
        $modelName = $entity['name'];
        $policyName = $modelName . 'Policy';
        $namespace = $this->getNamespace('Policies');
        $modelNamespace = $this->getNamespace('Models') . '\\' . $modelName;
        $modelVar = lcfirst($modelName);

        $hasUserId = $entity['hasUserId'] ?? false;
        $ownershipCheck = $hasUserId ? $this->generateOwnershipCheck($modelVar) : 'true';

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use {$modelNamespace};
use App\\Models\\User;
use Illuminate\\Auth\\Access\\HandlesAuthorization;

class {$policyName}
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User \$user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User \$user, {$modelName} \${$modelVar}): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User \$user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User \$user, {$modelName} \${$modelVar}): bool
    {
        return {$ownershipCheck};
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User \$user, {$modelName} \${$modelVar}): bool
    {
        return {$ownershipCheck};
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User \$user, {$modelName} \${$modelVar}): bool
    {
        return {$ownershipCheck};
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User \$user, {$modelName} \${$modelVar}): bool
    {
        return {$ownershipCheck};
    }
}
PHP;
    }

    /**
     * Generate ownership check code.
     */
    private function generateOwnershipCheck(string $modelVar): string
    {
        return "\$user->id === \${$modelVar}->user_id";
    }
}
