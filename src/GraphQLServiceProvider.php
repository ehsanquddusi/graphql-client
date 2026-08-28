<?php

namespace EhsanQ\GraphQL;

use EhsanQ\GraphQL\Cache\GraphQLCache;
use EhsanQ\GraphQL\Transport\LaravelHttpTransport;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class GraphQLServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/graphql.php', 'graphql');

        $this->app->singleton(LaravelHttpTransport::class, function ($app) {
            return new LaravelHttpTransport($app->make(HttpFactory::class));
        });

        $this->app->singleton(GraphQLCache::class, function ($app) {
            return new GraphQLCache($app->make(CacheFactory::class));
        });

        $this->app->bind(GraphQLClient::class, function ($app) {
            return GraphQLClient::fromConfig(
                $app['config']->get('graphql', []),
                $app->make(LaravelHttpTransport::class),
                $app->make(GraphQLCache::class),
            );
        });

        $this->app->singleton(GraphQL::class, function ($app) {
            return new GraphQL(fn () => $app->make(GraphQLClient::class));
        });

        $this->app->alias(GraphQL::class, 'graphql');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/graphql.php' => config_path('graphql.php'),
            ], 'graphql-config');
        }
    }
}
