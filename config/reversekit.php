<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Namespace
    |--------------------------------------------------------------------------
    |
    | The default namespace for generated classes. This can be overridden
    | using the --namespace option in the command.
    |
    */
    'namespace' => 'App',

    /*
    |--------------------------------------------------------------------------
    | Output Paths
    |--------------------------------------------------------------------------
    |
    | Define where each type of file should be generated. Paths are relative
    | to the base path (usually your Laravel app root).
    |
    */
    'paths' => [
        'models' => 'app/Models',
        'controllers' => 'app/Http/Controllers',
        'resources' => 'app/Http/Resources',
        'requests' => 'app/Http/Requests',
        'policies' => 'app/Policies',
        'factories' => 'database/factories',
        'seeders' => 'database/seeders',
        'migrations' => 'database/migrations',
        'tests' => 'tests/Feature',
        'routes' => 'routes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Generators
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific generators. Set to false to skip generation.
    |
    */
    'generators' => [
        'model' => true,
        'migration' => true,
        'controller' => true,
        'resource' => true,
        'request' => true,
        'policy' => true,
        'factory' => true,
        'seeder' => true,
        'test' => true,
        'routes' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Stub Path
    |--------------------------------------------------------------------------
    |
    | The path to your custom stubs. If you publish the stubs, they will be
    | placed in this directory. The package will use custom stubs if they
    | exist, otherwise fall back to the default stubs.
    |
    */
    'stub_path' => resource_path('stubs/reversekit'),

    /*
    |--------------------------------------------------------------------------
    | Model Options
    |--------------------------------------------------------------------------
    |
    | Configuration options for model generation.
    |
    */
    'model' => [
        'use_soft_deletes' => false,
        'use_timestamps' => true,
        'use_uuid' => false,
        'implements' => [], // e.g., ['Illuminate\Contracts\Auth\Authenticatable']
        'traits' => [], // e.g., ['Laravel\Sanctum\HasApiTokens']
    ],

    /*
    |--------------------------------------------------------------------------
    | Controller Options
    |--------------------------------------------------------------------------
    |
    | Configuration options for controller generation.
    |
    */
    'controller' => [
        'use_form_requests' => true,
        'use_resources' => true,
        'use_policies' => true,
        'api_only' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Options
    |--------------------------------------------------------------------------
    |
    | Configuration options for migration generation.
    |
    */
    'migration' => [
        'use_foreign_keys' => true,
        'cascade_on_delete' => true,
        'use_soft_deletes' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Options
    |--------------------------------------------------------------------------
    |
    | Configuration options for test generation.
    |
    */
    'test' => [
        'framework' => 'phpunit', // 'phpunit' or 'pest'
        'use_factories' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Naming Conventions
    |--------------------------------------------------------------------------
    |
    | Customize naming patterns for generated files.
    |
    */
    'naming' => [
        'controller_suffix' => 'Controller',
        'resource_suffix' => 'Resource',
        'request_suffix' => 'Request',
        'policy_suffix' => 'Policy',
        'factory_suffix' => 'Factory',
        'seeder_suffix' => 'Seeder',
        'test_suffix' => 'Test',
    ],

];
