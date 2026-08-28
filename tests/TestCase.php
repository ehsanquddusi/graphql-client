<?php

namespace EhsanQ\GraphQL\Tests;

use EhsanQ\GraphQL\GraphQLFacade;
use EhsanQ\GraphQL\GraphQLServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            GraphQLServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'GraphQL' => GraphQLFacade::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('graphql.endpoint', 'https://graphql.test/graphql');
        $app['config']->set('graphql.auth.type', 'none');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', [
            'driver' => 'array',
        ]);
    }
}
