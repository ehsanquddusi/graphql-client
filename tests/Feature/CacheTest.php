<?php

namespace EhsanQ\GraphQL\Tests\Feature;

use EhsanQ\GraphQL\GraphQLFacade as GraphQL;
use EhsanQ\GraphQL\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class CacheTest extends TestCase
{
    public function test_query_cache_hit_skips_http_and_returns_graphql_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => ['countries' => [['name' => 'India']]],
            ], 200),
        ]);

        $first = GraphQL::cache(300)
            ->query('countries')
            ->select('name')
            ->get();

        $second = GraphQL::cache(300)
            ->query('countries')
            ->select('name')
            ->get();

        $this->assertFalse($first->fromCache());
        $this->assertTrue($second->fromCache());
        $this->assertSame($first->data(), $second->data());
        $this->assertSame('India', $second->data('countries.0.name'));
        $this->assertTrue($second->successful());
        $this->assertCount(1, Http::recorded());
    }

    public function test_custom_cache_key_is_used(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['data' => ['n' => 1]], 200)
                ->push(['data' => ['n' => 2]], 200),
        ]);

        GraphQL::cache(300)->cacheKey('countries')->query('countries')->select('name')->get();
        GraphQL::cache(300)->cacheKey('countries')->query('countries')->select('code')->get();

        $this->assertCount(1, Http::recorded());
    }

    public function test_mutations_are_never_cached(): void
    {
        Http::fake([
            '*' => Http::response(['data' => ['createProduct' => ['id' => '1']]], 200),
        ]);

        GraphQL::cache(300)->mutation('createProduct')->args(['name' => 'A'])->select('id')->send();
        GraphQL::cache(300)->mutation('createProduct')->args(['name' => 'A'])->select('id')->send();

        $this->assertCount(2, Http::recorded());
    }

    public function test_no_cache_bypasses_store(): void
    {
        Http::fake([
            '*' => Http::response(['data' => ['countries' => []]], 200),
        ]);

        GraphQL::cache(300)->query('countries')->select('name')->get();
        GraphQL::noCache()->query('countries')->select('name')->get();

        $this->assertCount(2, Http::recorded());
    }

    public function test_cache_is_isolated_by_endpoint(): void
    {
        Http::fake([
            'https://a.test/graphql' => Http::response(['data' => ['source' => 'a']], 200),
            'https://b.test/graphql' => Http::response(['data' => ['source' => 'b']], 200),
        ]);

        $a = GraphQL::endpoint('https://a.test/graphql')->cache(300)->query('source')->get();
        $b = GraphQL::endpoint('https://b.test/graphql')->cache(300)->query('source')->get();

        $this->assertSame('a', $a->data('source'));
        $this->assertSame('b', $b->data('source'));
        $this->assertCount(2, Http::recorded());
    }

    public function test_cache_is_isolated_by_authentication(): void
    {
        Http::fake(function (Request $request) {
            $token = str_replace('Bearer ', '', $request->header('Authorization')[0] ?? '');

            return Http::response(['data' => ['me' => $token]], 200);
        });

        $alice = GraphQL::withToken('alice')->cache(300)->query('me')->get();
        $bob = GraphQL::withToken('bob')->cache(300)->query('me')->get();

        $this->assertSame('alice', $alice->data('me'));
        $this->assertSame('bob', $bob->data('me'));
        $this->assertCount(2, Http::recorded());
    }

    public function test_raw_authorization_header_is_not_used_as_cache_key_material(): void
    {
        $cache = $this->app->make(\EhsanQ\GraphQL\Cache\GraphQLCache::class);

        $key = $cache->key(
            'https://graphql.test/graphql',
            'query { me }',
            [],
            'super-secret-token',
            null,
        );

        $this->assertStringNotContainsString('super-secret-token', $key);
        $this->assertStringNotContainsString('Authorization', $key);
    }
}
