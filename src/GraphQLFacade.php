<?php

namespace EhsanQ\GraphQL;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \EhsanQ\GraphQL\GraphQLClient client()
 * @method static \EhsanQ\GraphQL\GraphQLClient endpoint(string $url)
 * @method static \EhsanQ\GraphQL\GraphQLClient withHeaders(array $headers)
 * @method static \EhsanQ\GraphQL\GraphQLClient header(string $name, string $value)
 * @method static \EhsanQ\GraphQL\GraphQLClient withToken(?string $token, string $type = 'Bearer')
 * @method static \EhsanQ\GraphQL\GraphQLClient bearerToken(?string $token)
 * @method static \EhsanQ\GraphQL\GraphQLClient withBasicAuth(string $username, string $password)
 * @method static \EhsanQ\GraphQL\GraphQLClient withApiKey(string $header, string $key)
 * @method static \EhsanQ\GraphQL\GraphQLClient retry(int $times, int $sleepMilliseconds = 100, mixed $when = null)
 * @method static \EhsanQ\GraphQL\GraphQLClient timeout(int|float $seconds)
 * @method static \EhsanQ\GraphQL\GraphQLClient connectTimeout(int|float $seconds)
 * @method static \EhsanQ\GraphQL\GraphQLClient withoutVerifying()
 * @method static \EhsanQ\GraphQL\GraphQLClient cache(?int $ttl = 300)
 * @method static \EhsanQ\GraphQL\GraphQLClient cacheKey(string $key)
 * @method static \EhsanQ\GraphQL\GraphQLClient cacheStore(?string $store)
 * @method static \EhsanQ\GraphQL\GraphQLClient noCache()
 * @method static \EhsanQ\GraphQL\Builder\QueryBuilder query(string $field)
 * @method static \EhsanQ\GraphQL\Builder\MutationBuilder mutation(string $field)
 * @method static \EhsanQ\GraphQL\Request\GraphQLRequestBuilder raw(string $query, array $variables = [])
 * @method static \EhsanQ\GraphQL\Request\GraphQLRequestBuilder file(string $path)
 *
 * @see \EhsanQ\GraphQL\GraphQL
 */
class GraphQLFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GraphQL::class;
    }
}
