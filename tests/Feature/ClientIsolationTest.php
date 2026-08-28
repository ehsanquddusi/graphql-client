<?php

namespace EhsanQ\GraphQL\Tests\Feature;

use EhsanQ\GraphQL\GraphQLFacade as GraphQL;
use EhsanQ\GraphQL\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class ClientIsolationTest extends TestCase
{
    public function test_independent_clients_do_not_leak_endpoint_or_auth(): void
    {
        Http::fake([
            'https://orchardly.test/graphql' => Http::response(['data' => ['me' => 'orchardly']], 200),
            'https://shopify.test/graphql' => Http::response(['data' => ['me' => 'shopify']], 200),
        ]);

        $orchardly = GraphQL::endpoint('https://orchardly.test/graphql')
            ->withToken('orchardly-token');

        $shopify = GraphQL::endpoint('https://shopify.test/graphql')
            ->withToken('shopify-token');

        $orchardlyResponse = $orchardly->query('me')->select('id')->get();
        $shopifyResponse = $shopify->query('me')->select('id')->get();

        $this->assertSame('orchardly', $orchardlyResponse->data('me'));
        $this->assertSame('shopify', $shopifyResponse->data('me'));

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://orchardly.test/graphql'
                && $request->hasHeader('Authorization', 'Bearer orchardly-token');
        });

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://shopify.test/graphql'
                && $request->hasHeader('Authorization', 'Bearer shopify-token');
        });

        $this->assertCount(2, Http::recorded());
    }

    public function test_runtime_endpoint_overrides_config_without_mutating_defaults(): void
    {
        Http::fake([
            'https://graphql.test/graphql' => Http::response(['data' => ['source' => 'default']], 200),
            'https://other.test/graphql' => Http::response(['data' => ['source' => 'other']], 200),
        ]);

        $other = GraphQL::endpoint('https://other.test/graphql')
            ->query('source')
            ->get();

        $default = GraphQL::query('source')->get();

        $this->assertSame('other', $other->data('source'));
        $this->assertSame('default', $default->data('source'));
    }

    public function test_headers_on_one_client_do_not_appear_on_another(): void
    {
        Http::fake(Http::response(['data' => ['ok' => true]], 200));

        $tenant = GraphQL::endpoint('https://a.test/graphql')
            ->withHeaders(['X-Tenant' => 'acme']);

        $plain = GraphQL::endpoint('https://b.test/graphql');

        $tenant->query('ok')->get();
        $plain->query('ok')->get();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://a.test/graphql'
            && $request->hasHeader('X-Tenant', 'acme'));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://b.test/graphql'
            && ! $request->hasHeader('X-Tenant'));
    }
}
