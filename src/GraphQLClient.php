<?php

namespace EhsanQ\GraphQL;

use EhsanQ\GraphQL\Auth\Authentication;
use EhsanQ\GraphQL\Builder\MutationBuilder;
use EhsanQ\GraphQL\Builder\QueryBuilder;
use EhsanQ\GraphQL\Cache\GraphQLCache;
use EhsanQ\GraphQL\Exceptions\GraphQLException;
use EhsanQ\GraphQL\Request\GraphQLRequest;
use EhsanQ\GraphQL\Request\GraphQLRequestBuilder;
use EhsanQ\GraphQL\Response\GraphQLResponse;
use EhsanQ\GraphQL\Transport\Transport;

class GraphQLClient
{
    /**
     * @param  array<string, string>  $headers
     * @param  array{0: string, 1: string}|null  $basicAuth
     */
    public function __construct(
        protected Transport $transport,
        protected ?GraphQLCache $cache = null,
        protected ?string $endpoint = null,
        protected array $headers = [],
        protected ?string $token = null,
        protected ?array $basicAuth = null,
        protected ?int $timeout = 30,
        protected ?int $connectTimeout = 10,
        protected int $retryTimes = 0,
        protected int $retrySleep = 100,
        protected mixed $retryWhen = null,
        protected bool $withoutVerifying = false,
        protected ?int $cacheTtl = null,
        protected ?string $cacheKey = null,
        protected ?string $cacheStore = null,
        protected bool $noCache = false,
        protected ?string $queryPath = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config, Transport $transport, ?GraphQLCache $cache = null): self
    {
        $retry = $config['retry'] ?? [];
        $cacheConfig = $config['cache'] ?? [];

        $client = new self(
            transport: $transport,
            cache: $cache,
            endpoint: ($config['endpoint'] ?? null) ?: null,
            headers: $config['headers'] ?? [],
            timeout: $config['timeout'] ?? 30,
            connectTimeout: $config['connect_timeout'] ?? 10,
            retryTimes: (int) ($retry['times'] ?? 0),
            retrySleep: (int) ($retry['sleep'] ?? 100),
            queryPath: $config['query_path'] ?? null,
        );

        $client = Authentication::apply($client, $config['auth'] ?? []);

        if ($cacheConfig['enabled'] ?? false) {
            $client = $client
                ->cache((int) ($cacheConfig['ttl'] ?? 300))
                ->cacheStore($cacheConfig['store'] ?? null);
        }

        return $client;
    }

    public function endpoint(string $url): static
    {
        $clone = clone $this;
        $clone->endpoint = $url;

        return $clone;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): static
    {
        $clone = clone $this;
        $clone->headers = array_merge($clone->headers, $headers);

        return $clone;
    }

    public function header(string $name, string $value): static
    {
        return $this->withHeaders([$name => $value]);
    }

    public function withToken(?string $token, string $type = 'Bearer'): static
    {
        $clone = clone $this;
        $clone->token = $token;

        if ($token !== null && $token !== '') {
            $clone->headers['Authorization'] = trim($type.' '.$token);
        } else {
            unset($clone->headers['Authorization']);
        }

        return $clone;
    }

    public function bearerToken(?string $token): static
    {
        return $this->withToken($token);
    }

    public function withBasicAuth(string $username, string $password): static
    {
        $clone = clone $this;
        $clone->basicAuth = [$username, $password];
        $clone->token = null;
        unset($clone->headers['Authorization']);

        return $clone;
    }

    public function withApiKey(string $header, string $key): static
    {
        return $this->withHeaders([$header => $key]);
    }

    public function retry(int $times, int $sleepMilliseconds = 100, mixed $when = null): static
    {
        $clone = clone $this;
        $clone->retryTimes = $times;
        $clone->retrySleep = $sleepMilliseconds;
        $clone->retryWhen = $when;

        return $clone;
    }

    public function timeout(int|float $seconds): static
    {
        $clone = clone $this;
        $clone->timeout = (int) $seconds;

        return $clone;
    }

    public function connectTimeout(int|float $seconds): static
    {
        $clone = clone $this;
        $clone->connectTimeout = (int) $seconds;

        return $clone;
    }

    public function withoutVerifying(): static
    {
        $clone = clone $this;
        $clone->withoutVerifying = true;

        return $clone;
    }

    public function cache(?int $ttl = 300): static
    {
        $clone = clone $this;
        $clone->cacheTtl = $ttl ?? 300;
        $clone->noCache = false;

        return $clone;
    }

    public function cacheKey(string $key): static
    {
        $clone = clone $this;
        $clone->cacheKey = $key;

        return $clone;
    }

    public function cacheStore(?string $store): static
    {
        $clone = clone $this;
        $clone->cacheStore = $store;

        return $clone;
    }

    public function noCache(): static
    {
        $clone = clone $this;
        $clone->noCache = true;
        $clone->cacheTtl = null;

        return $clone;
    }

    public function query(string $field): QueryBuilder
    {
        return new QueryBuilder(clone $this, $field);
    }

    public function mutation(string $field): MutationBuilder
    {
        return new MutationBuilder(clone $this, $field);
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function raw(string $query, array $variables = []): GraphQLRequestBuilder
    {
        return new GraphQLRequestBuilder(clone $this, $query, $variables);
    }

    public function file(string $path): GraphQLRequestBuilder
    {
        return $this->raw($this->readQueryFile($path));
    }

    public function execute(GraphQLRequest $request): GraphQLResponse
    {
        if ($this->shouldCache($request)) {
            $key = $this->cache->keyFor($request, $this->cacheKey);
            $hit = $this->cache->get($this->cacheStore, $key);

            if ($hit !== null) {
                return $hit;
            }
        }

        $response = $this->transport->send($request);

        if ($this->shouldCache($request) && $response->successful()) {
            $this->cache->put(
                $this->cacheStore,
                $this->cache->keyFor($request, $this->cacheKey),
                $response,
                (int) $this->cacheTtl,
            );
        }

        return $response;
    }

    public function toRequest(
        string $query,
        array $variables = [],
        ?string $operationName = null,
        string $operationType = 'query',
    ): GraphQLRequest {
        $endpoint = $this->endpoint;

        if ($endpoint === null || $endpoint === '') {
            throw new GraphQLException('No GraphQL endpoint configured. Call endpoint() or set GRAPHQL_ENDPOINT.');
        }

        $headers = $this->headers;

        if ($this->token !== null && $this->token !== '') {
            unset($headers['Authorization']);
        }

        return new GraphQLRequest(
            endpoint: $endpoint,
            query: $query,
            variables: $variables,
            operationName: $operationName,
            headers: $headers,
            token: $this->token,
            basicAuth: $this->basicAuth,
            timeout: $this->timeout,
            connectTimeout: $this->connectTimeout,
            retryTimes: $this->retryTimes,
            retrySleep: $this->retrySleep,
            retryWhen: $this->retryWhen,
            withoutVerifying: $this->withoutVerifying,
            operationType: $operationType,
        );
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public function getBasicAuth(): ?array
    {
        return $this->basicAuth;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function getConnectTimeout(): ?int
    {
        return $this->connectTimeout;
    }

    public function getRetryTimes(): int
    {
        return $this->retryTimes;
    }

    public function getRetrySleep(): int
    {
        return $this->retrySleep;
    }

    public function getWithoutVerifying(): bool
    {
        return $this->withoutVerifying;
    }

    public function getCacheTtl(): ?int
    {
        return $this->cacheTtl;
    }

    public function getCacheKey(): ?string
    {
        return $this->cacheKey;
    }

    public function getCacheStore(): ?string
    {
        return $this->cacheStore;
    }

    protected function shouldCache(GraphQLRequest $request): bool
    {
        return ! $this->noCache
            && $this->cacheTtl !== null
            && $this->cacheTtl > 0
            && $this->cache !== null
            && $request->isCacheable();
    }

    protected function readQueryFile(string $path): string
    {
        $resolved = $this->resolveQueryFile($path);

        $contents = file_get_contents($resolved);

        if ($contents === false) {
            throw new GraphQLException("Unable to read GraphQL file [{$resolved}].");
        }

        return $contents;
    }

    protected function resolveQueryFile(string $path): string
    {
        if (is_file($path)) {
            return $path;
        }

        $candidates = array_filter([
            $this->queryPath ? rtrim($this->queryPath, '/\\').DIRECTORY_SEPARATOR.$path : null,
            function_exists('resource_path') ? resource_path($path) : null,
            function_exists('resource_path') ? resource_path('graphql/'.$path) : null,
            function_exists('base_path') ? base_path($path) : null,
            getcwd() !== false ? getcwd().DIRECTORY_SEPARATOR.$path : null,
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new GraphQLException("GraphQL file [{$path}] was not found.");
    }
}
