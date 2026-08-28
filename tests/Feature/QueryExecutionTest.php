<?php

namespace EhsanQ\GraphQL\Tests\Feature;

use EhsanQ\GraphQL\Exceptions\GraphQLException;
use EhsanQ\GraphQL\Exceptions\GraphQLHttpException;
use EhsanQ\GraphQL\GraphQLFacade as GraphQL;
use EhsanQ\GraphQL\Response\GraphQLResponse;
use EhsanQ\GraphQL\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class QueryExecutionTest extends TestCase
{
    public function test_query_returns_graphql_response_with_data(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'products' => [
                        ['id' => '1', 'name' => 'Apple', 'price' => 2],
                    ],
                ],
            ], 200),
        ]);

        $response = GraphQL::query('products')
            ->select('id', 'name', 'price')
            ->get();

        $this->assertInstanceOf(GraphQLResponse::class, $response);
        $this->assertTrue($response->successful());
        $this->assertFalse($response->hasErrors());
        $this->assertSame('Apple', $response->data('products.0.name'));
        $this->assertSame(200, $response->status());
        $this->assertNotNull($response->http());
        $this->assertIsArray($response->json('data'));

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return str_contains($body['query'], 'products')
                && str_contains($body['query'], 'id')
                && str_contains($body['query'], 'name');
        });
    }

    public function test_mutation_posts_a_mutation_document(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'createProduct' => ['id' => '9', 'name' => 'Pear'],
                ],
            ], 200),
        ]);

        $response = GraphQL::mutation('createProduct')
            ->args(['name' => 'Pear'])
            ->select('id', 'name')
            ->send();

        $this->assertSame('9', $response->data('createProduct.id'));

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return str_starts_with(trim($body['query']), 'mutation')
                && $body['variables']['name'] === 'Pear';
        });
    }

    public function test_http_success_can_still_contain_graphql_errors(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => null,
                'errors' => [
                    ['message' => 'Syntax Error: Expected {'],
                ],
            ], 200, ['X-Request-ID' => 'abc-123']),
        ]);

        $response = GraphQL::query('broken')->select('id')->get();

        $this->assertTrue($response->successful());
        $this->assertFalse($response->failed());
        $this->assertTrue($response->hasErrors());
        $this->assertSame('Syntax Error: Expected {', $response->errors()[0]['message']);
        $this->assertSame('abc-123', $response->header('X-Request-ID'));
        $this->assertStringContainsString('Syntax Error', $response->body());
    }

    public function test_throw_if_graphql_errors_raises_graphql_exception(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => null,
                'errors' => [
                    ['message' => 'Not found'],
                ],
            ], 200),
        ]);

        $this->expectException(GraphQLException::class);
        $this->expectExceptionMessage('Not found');

        GraphQL::query('product')->select('id')->get()->throwIfGraphQLErrors();
    }

    public function test_throw_raises_http_exception_on_failed_status(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'Nope'], 401),
        ]);

        $response = GraphQL::query('me')->select('id')->get();

        $this->assertTrue($response->failed());
        $this->assertSame(401, $response->status());

        $this->expectException(GraphQLHttpException::class);

        $response->throw();
    }

    public function test_raw_query_and_variables_are_sent_as_given(): void
    {
        Http::fake([
            '*' => Http::response(['data' => ['echo' => 'ok']], 200),
        ]);

        $document = 'query ($id: ID!) { product(id: $id) { id } }';

        GraphQL::raw($document, ['id' => '42'])->get();

        Http::assertSent(function (Request $request) use ($document) {
            $body = $request->data();

            return $body['query'] === $document
                && $body['variables']['id'] === '42';
        });
    }

    public function test_file_query_is_loaded_and_sent(): void
    {
        Http::fake([
            '*' => Http::response(['data' => ['products' => []]], 200),
        ]);

        $path = sys_get_temp_dir().'/products-'.uniqid().'.graphql';
        file_put_contents($path, 'query ($limit: Int) { products(limit: $limit) { id } }');

        try {
            GraphQL::file($path)->variables(['limit' => 5])->get();
        } finally {
            @unlink($path);
        }

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return str_contains($body['query'], 'products(limit: $limit)')
                && $body['variables']['limit'] === 5;
        });
    }

    public function test_north_star_chain_sends_expected_payload(): void
    {
        Http::fake([
            '*' => Http::response(['data' => ['products' => []]], 200, ['X-Request-ID' => 'req-1']),
        ]);

        $response = GraphQL::endpoint('https://graphql.test/graphql')
            ->withToken('secret')
            ->withHeaders(['X-Tenant' => 'acme'])
            ->retry(3, 100)
            ->timeout(30)
            ->query('products')
            ->where('category', 'apple')
            ->select('id', 'name', 'price')
            ->expand('supplier', fn ($q) => $q->select('id', 'name'))
            ->get();

        $this->assertSame(200, $response->status());
        $this->assertSame('req-1', $response->header('X-Request-ID'));
        $this->assertSame([], $response->errors());

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->hasHeader('Authorization', 'Bearer secret')
                && $request->hasHeader('X-Tenant', 'acme')
                && str_contains($body['query'], 'supplier { id name }')
                && $body['variables']['category'] === 'apple';
        });
    }
}
