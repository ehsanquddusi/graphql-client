<?php

namespace EhsanQ\GraphQL\Tests\Feature;

use EhsanQ\GraphQL\Exceptions\GraphQLException;
use EhsanQ\GraphQL\GraphQLFacade as GraphQL;
use EhsanQ\GraphQL\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class ConfigAndHttpOptionsTest extends TestCase
{
    public function test_config_headers_are_sent(): void
    {
        config()->set('graphql.headers', [
            'Accept' => 'application/json',
            'X-App' => 'graphql-client',
        ]);

        Http::fake(['*' => Http::response(['data' => ['ok' => true]], 200)]);

        GraphQL::query('ok')->get();

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-App', 'graphql-client'));
    }

    public function test_missing_endpoint_throws(): void
    {
        config()->set('graphql.endpoint', null);

        $this->expectException(GraphQLException::class);
        $this->expectExceptionMessage('No GraphQL endpoint configured');

        GraphQL::query('ok')->get();
    }

    public function test_timeout_and_retry_are_applied_to_the_http_client(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push('error', 503)
                ->push('error', 503)
                ->push(['data' => ['ok' => true]], 200),
        ]);

        $response = GraphQL::retry(3, 1)
            ->timeout(15)
            ->connectTimeout(5)
            ->query('ok')
            ->get();

        $this->assertTrue($response->successful());
        $this->assertCount(3, Http::recorded());
    }

    public function test_runtime_overrides_replace_config_auth(): void
    {
        config()->set('graphql.auth.type', 'bearer');
        config()->set('graphql.auth.token', 'config-token');

        Http::fake(['*' => Http::response(['data' => ['ok' => true]], 200)]);

        GraphQL::withToken('runtime-token')->query('ok')->get();

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer runtime-token'));
        Http::assertNotSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer config-token'));
    }
}
