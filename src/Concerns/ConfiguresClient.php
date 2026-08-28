<?php

namespace EhsanQ\GraphQL\Concerns;

use EhsanQ\GraphQL\GraphQLClient;
use EhsanQ\GraphQL\Response\GraphQLResponse;

trait ConfiguresClient
{
    protected GraphQLClient $client;

    public function endpoint(string $url): static
    {
        $this->client = $this->client->endpoint($url);

        return $this;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): static
    {
        $this->client = $this->client->withHeaders($headers);

        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->client = $this->client->header($name, $value);

        return $this;
    }

    public function withToken(?string $token, string $type = 'Bearer'): static
    {
        $this->client = $this->client->withToken($token, $type);

        return $this;
    }

    public function bearerToken(?string $token): static
    {
        $this->client = $this->client->bearerToken($token);

        return $this;
    }

    public function withBasicAuth(string $username, string $password): static
    {
        $this->client = $this->client->withBasicAuth($username, $password);

        return $this;
    }

    public function withApiKey(string $header, string $key): static
    {
        $this->client = $this->client->withApiKey($header, $key);

        return $this;
    }

    public function retry(int $times, int $sleepMilliseconds = 100, mixed $when = null): static
    {
        $this->client = $this->client->retry($times, $sleepMilliseconds, $when);

        return $this;
    }

    public function timeout(int|float $seconds): static
    {
        $this->client = $this->client->timeout($seconds);

        return $this;
    }

    public function connectTimeout(int|float $seconds): static
    {
        $this->client = $this->client->connectTimeout($seconds);

        return $this;
    }

    public function withoutVerifying(): static
    {
        $this->client = $this->client->withoutVerifying();

        return $this;
    }

    public function cache(?int $ttl = 300): static
    {
        $this->client = $this->client->cache($ttl);

        return $this;
    }

    public function cacheKey(string $key): static
    {
        $this->client = $this->client->cacheKey($key);

        return $this;
    }

    public function cacheStore(?string $store): static
    {
        $this->client = $this->client->cacheStore($store);

        return $this;
    }

    public function noCache(): static
    {
        $this->client = $this->client->noCache();

        return $this;
    }

    public function get(): GraphQLResponse
    {
        return $this->execute();
    }

    public function send(): GraphQLResponse
    {
        return $this->execute();
    }

    abstract public function execute(): GraphQLResponse;
}
