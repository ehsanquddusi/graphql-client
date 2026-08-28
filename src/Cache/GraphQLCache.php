<?php

namespace EhsanQ\GraphQL\Cache;

use EhsanQ\GraphQL\Request\GraphQLRequest;
use EhsanQ\GraphQL\Response\GraphQLResponse;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Response;

class GraphQLCache
{
    public function __construct(
        protected ?CacheFactory $cache = null,
    ) {}

    public function get(?string $store, string $key): ?GraphQLResponse
    {
        $cached = $this->store($store)->get($key);

        if (! is_array($cached) || ! isset($cached['body'])) {
            return null;
        }

        return GraphQLResponse::hydrate($cached, $this->restoreHttp($cached));
    }

    public function put(?string $store, string $key, GraphQLResponse $response, int $ttl): void
    {
        $this->store($store)->put($key, $response->toCache(), $ttl);
    }

    /**
     * Build a cache key that isolates endpoints and authenticated identities
     * without storing raw Authorization values.
     *
     * @param  array<string, mixed>  $variables
     */
    public function key(
        string $endpoint,
        string $query,
        array $variables,
        ?string $token,
        ?array $basicAuth,
        ?string $customKey = null,
    ): string {
        $identity = $this->identityFingerprint($token, $basicAuth);

        $payload = $customKey ?? hash('sha256', $query.'|'.json_encode($variables));

        return 'graphql:'.hash('sha256', $endpoint.'|'.$identity.'|'.$payload);
    }

    public function keyFor(GraphQLRequest $request, ?string $customKey = null): string
    {
        return $this->key(
            $request->endpoint,
            $request->query,
            $request->variables,
            $request->token,
            $request->basicAuth,
            $customKey,
        );
    }

    protected function store(?string $name): Repository
    {
        if ($this->cache === null) {
            throw new \RuntimeException('GraphQL cache is not configured.');
        }

        return $name ? $this->cache->store($name) : $this->cache->store();
    }

    /**
     * @param  array{0: string, 1: string}|null  $basicAuth
     */
    protected function identityFingerprint(?string $token, ?array $basicAuth): string
    {
        if ($token) {
            return 'token:'.hash('sha256', $token);
        }

        if ($basicAuth) {
            return 'basic:'.hash('sha256', $basicAuth[0].':'.$basicAuth[1]);
        }

        return 'anon';
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    protected function restoreHttp(array $cached): Response
    {
        $headers = [];

        foreach ($cached['headers'] ?? [] as $name => $values) {
            $headers[$name] = is_array($values) ? $values : [$values];
        }

        return new Response(new Psr7Response(
            (int) ($cached['status'] ?? 200),
            $headers,
            (string) ($cached['body'] ?? ''),
        ));
    }
}
