<?php

namespace EhsanQ\GraphQL\Tests\Feature;

use EhsanQ\GraphQL\GraphQLFacade as GraphQL;
use EhsanQ\GraphQL\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class AuthenticationTest extends TestCase
{
    public function test_with_token_sends_bearer_authorization(): void
    {
        Http::fake(['*' => Http::response(['data' => ['ok' => true]], 200)]);

        GraphQL::withToken('abc')->query('ok')->get();

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer abc'));
    }

    public function test_bearer_token_is_an_alias_of_with_token(): void
    {
        Http::fake(['*' => Http::response(['data' => ['ok' => true]], 200)]);

        GraphQL::bearerToken('xyz')->query('ok')->get();

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer xyz'));
    }

    public function test_basic_auth_is_sent(): void
    {
        Http::fake(['*' => Http::response(['data' => ['ok' => true]], 200)]);

        GraphQL::withBasicAuth('user', 'pass')->query('ok')->get();

        Http::assertSent(function (Request $request) {
            $header = $request->header('Authorization')[0] ?? '';

            return str_starts_with($header, 'Basic ')
                && base64_decode(substr($header, 6)) === 'user:pass';
        });
    }

    public function test_api_key_header_is_sent(): void
    {
        Http::fake(['*' => Http::response(['data' => ['ok' => true]], 200)]);

        GraphQL::withApiKey('X-API-Key', 'key-1')->query('ok')->get();

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-API-Key', 'key-1'));
    }

    public function test_config_bearer_auth_is_applied_by_default(): void
    {
        config()->set('graphql.auth.type', 'bearer');
        config()->set('graphql.auth.token', 'from-config');

        Http::fake(['*' => Http::response(['data' => ['ok' => true]], 200)]);

        GraphQL::query('ok')->get();

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer from-config'));
    }
}
