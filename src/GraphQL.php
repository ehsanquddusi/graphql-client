<?php

namespace EhsanQ\GraphQL;

use Closure;
use EhsanQ\GraphQL\Builder\MutationBuilder;
use EhsanQ\GraphQL\Builder\QueryBuilder;
use EhsanQ\GraphQL\Request\GraphQLRequestBuilder;

class GraphQL
{
    /**
     * @param  Closure(): GraphQLClient  $factory
     */
    public function __construct(
        protected Closure $factory,
    ) {}

    public function client(): GraphQLClient
    {
        return ($this->factory)();
    }

    public function endpoint(string $url): GraphQLClient
    {
        return $this->client()->endpoint($url);
    }

    public function withHeaders(array $headers): GraphQLClient
    {
        return $this->client()->withHeaders($headers);
    }

    public function header(string $name, string $value): GraphQLClient
    {
        return $this->client()->header($name, $value);
    }

    public function withToken(?string $token, string $type = 'Bearer'): GraphQLClient
    {
        return $this->client()->withToken($token, $type);
    }

    public function bearerToken(?string $token): GraphQLClient
    {
        return $this->client()->bearerToken($token);
    }

    public function withBasicAuth(string $username, string $password): GraphQLClient
    {
        return $this->client()->withBasicAuth($username, $password);
    }

    public function withApiKey(string $header, string $key): GraphQLClient
    {
        return $this->client()->withApiKey($header, $key);
    }

    public function retry(int $times, int $sleepMilliseconds = 100, mixed $when = null): GraphQLClient
    {
        return $this->client()->retry($times, $sleepMilliseconds, $when);
    }

    public function timeout(int|float $seconds): GraphQLClient
    {
        return $this->client()->timeout($seconds);
    }

    public function connectTimeout(int|float $seconds): GraphQLClient
    {
        return $this->client()->connectTimeout($seconds);
    }

    public function withoutVerifying(): GraphQLClient
    {
        return $this->client()->withoutVerifying();
    }

    public function cache(?int $ttl = 300): GraphQLClient
    {
        return $this->client()->cache($ttl);
    }

    public function cacheKey(string $key): GraphQLClient
    {
        return $this->client()->cacheKey($key);
    }

    public function cacheStore(?string $store): GraphQLClient
    {
        return $this->client()->cacheStore($store);
    }

    public function noCache(): GraphQLClient
    {
        return $this->client()->noCache();
    }

    public function query(string $field): QueryBuilder
    {
        return $this->client()->query($field);
    }

    public function mutation(string $field): MutationBuilder
    {
        return $this->client()->mutation($field);
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function raw(string $query, array $variables = []): GraphQLRequestBuilder
    {
        return $this->client()->raw($query, $variables);
    }

    public function file(string $path): GraphQLRequestBuilder
    {
        return $this->client()->file($path);
    }

    public function __call(string $method, array $parameters): mixed
    {
        $client = $this->client();

        if (method_exists($client, $method)) {
            return $client->{$method}(...$parameters);
        }

        $builder = $client->query($method);

        if ($parameters !== []) {
            $builder->args($this->normalizeMagicArgs($parameters));
        }

        return $builder;
    }

    /**
     * @param  array<int, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function normalizeMagicArgs(array $parameters): array
    {
        if (isset($parameters[0]) && is_array($parameters[0]) && ! array_is_list($parameters[0])) {
            return $parameters[0];
        }

        return $parameters;
    }
}
