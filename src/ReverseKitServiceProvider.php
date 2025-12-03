<?php

declare(strict_types=1);

namespace Shaqi\ReverseKit;

use Illuminate\Support\ServiceProvider;
use Shaqi\ReverseKit\Commands\ReverseGenerateCommand;
use Shaqi\ReverseKit\Commands\ReverseInteractiveCommand;
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
use Shaqi\ReverseKit\Parsers\ApiUrlParser;
use Shaqi\ReverseKit\Parsers\DatabaseParser;
use Shaqi\ReverseKit\Parsers\JsonParser;
use Shaqi\ReverseKit\Parsers\OpenApiParser;
use Shaqi\ReverseKit\Parsers\PostmanParser;
use Shaqi\ReverseKit\Support\RelationshipDetector;
use Shaqi\ReverseKit\Support\TypeInferrer;

class ReverseKitServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/reversekit.php', 'reversekit');

        $this->app->singleton(TypeInferrer::class, function () {
            return new TypeInferrer();
        });

        $this->app->singleton(RelationshipDetector::class, function ($app) {
            return new RelationshipDetector($app->make(TypeInferrer::class));
        });

        // Register all parsers
        $this->app->singleton(JsonParser::class, function ($app) {
            return new JsonParser(
                $app->make(TypeInferrer::class),
                $app->make(RelationshipDetector::class)
            );
        });

        $this->app->singleton(ApiUrlParser::class, function ($app) {
            return new ApiUrlParser($app->make(JsonParser::class));
        });

        $this->app->singleton(OpenApiParser::class, function ($app) {
            return new OpenApiParser(
                $app->make(TypeInferrer::class),
                $app->make(RelationshipDetector::class)
            );
        });

        $this->app->singleton(PostmanParser::class, function ($app) {
            return new PostmanParser(
                $app->make(TypeInferrer::class),
                $app->make(RelationshipDetector::class)
            );
        });

        $this->app->singleton(DatabaseParser::class, function ($app) {
            return new DatabaseParser($app->make(RelationshipDetector::class));
        });

        // Register all generators
        $this->app->singleton(ModelGenerator::class);
        $this->app->singleton(MigrationGenerator::class);
        $this->app->singleton(ControllerGenerator::class);
        $this->app->singleton(ResourceGenerator::class);
        $this->app->singleton(FormRequestGenerator::class);
        $this->app->singleton(PolicyGenerator::class);
        $this->app->singleton(FactoryGenerator::class);
        $this->app->singleton(SeederGenerator::class);
        $this->app->singleton(TestGenerator::class);
        $this->app->singleton(RouteGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ReverseGenerateCommand::class,
                ReverseInteractiveCommand::class,
            ]);

            // Publish config
            $this->publishes([
                __DIR__ . '/../config/reversekit.php' => config_path('reversekit.php'),
            ], 'reversekit-config');

            // Publish stubs
            $this->publishes([
                __DIR__ . '/../stubs' => resource_path('stubs/reversekit'),
            ], 'reversekit-stubs');
        }
    }
}
