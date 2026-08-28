<?php

namespace EhsanQ\GraphQL\Response;

use EhsanQ\GraphQL\Exceptions\GraphQLException;
use EhsanQ\GraphQL\Exceptions\GraphQLHttpException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;

class GraphQLResponse
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        protected ?Response $http,
        protected array $payload = [],
        protected bool $cached = false,
    ) {
        if ($this->payload === [] && $http !== null) {
            $decoded = $http->json();
            $this->payload = is_array($decoded) ? $decoded : [];
        }
    }

    public static function fromHttp(Response $response): self
    {
        $decoded = $response->json();

        return new self($response, is_array($decoded) ? $decoded : []);
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    public static function hydrate(array $cached, Response $http): self
    {
        return new self(
            $http,
            $cached['payload'] ?? [],
            true,
        );
    }

    public function data(mixed $key = null, mixed $default = null): mixed
    {
        $data = $this->payload['data'] ?? null;

        if ($key === null) {
            return $data;
        }

        return data_get($data, $key, $default);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function errors(): array
    {
        $errors = $this->payload['errors'] ?? [];

        return is_array($errors) ? $errors : [];
    }

    public function hasErrors(): bool
    {
        return $this->errors() !== [];
    }

    public function extensions(): mixed
    {
        return $this->payload['extensions'] ?? null;
    }

    public function json(mixed $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->payload;
        }

        return data_get($this->payload, $key, $default);
    }

    public function status(): int
    {
        return $this->http?->status() ?? 0;
    }

    public function successful(): bool
    {
        return $this->http?->successful() ?? false;
    }

    public function failed(): bool
    {
        return $this->http?->failed() ?? true;
    }

    public function clientError(): bool
    {
        return $this->http?->clientError() ?? false;
    }

    public function serverError(): bool
    {
        return $this->http?->serverError() ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function headers(): array
    {
        return $this->http?->headers() ?? [];
    }

    public function header(string $header): ?string
    {
        if ($this->http === null) {
            return null;
        }

        $value = $this->http->header($header);

        return $value === '' ? null : $value;
    }

    public function body(): string
    {
        return $this->http?->body() ?? '';
    }

    public function http(): ?Response
    {
        return $this->http;
    }

    public function fromCache(): bool
    {
        return $this->cached;
    }

    /**
     * Throw when the HTTP request failed or the GraphQL payload contains errors.
     */
    public function throw(): static
    {
        if ($this->failed()) {
            throw new GraphQLHttpException(
                sprintf('GraphQL HTTP request failed with status %d.', $this->status()),
                $this,
                $this->status(),
            );
        }

        return $this->throwIfGraphQLErrors();
    }

    public function throwIfGraphQLErrors(): static
    {
        if (! $this->hasErrors()) {
            return $this;
        }

        $first = Arr::get($this->errors(), '0.message', 'GraphQL request returned errors.');

        throw new GraphQLException((string) $first, $this);
    }

    /**
     * @return array<string, mixed>
     */
    public function toCache(): array
    {
        return [
            'status' => $this->status(),
            'headers' => $this->headers(),
            'body' => $this->body(),
            'payload' => $this->payload,
        ];
    }
}
