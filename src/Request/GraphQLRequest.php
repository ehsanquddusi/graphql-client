<?php

namespace EhsanQ\GraphQL\Request;

class GraphQLRequest
{
    /**
     * @param  array<string, mixed>  $variables
     * @param  array<string, string>  $headers
     * @param  array{0: string, 1: string}|null  $basicAuth
     */
    public function __construct(
        public readonly string $endpoint,
        public readonly string $query,
        public readonly array $variables = [],
        public readonly ?string $operationName = null,
        public readonly array $headers = [],
        public readonly ?string $token = null,
        public readonly ?array $basicAuth = null,
        public readonly ?int $timeout = null,
        public readonly ?int $connectTimeout = null,
        public readonly int $retryTimes = 0,
        public readonly int $retrySleep = 100,
        public readonly mixed $retryWhen = null,
        public readonly bool $withoutVerifying = false,
        public readonly string $operationType = 'query',
    ) {}

    public function isCacheable(): bool
    {
        if ($this->operationType === 'mutation') {
            return false;
        }

        return ! preg_match('/^\s*mutation\b/i', $this->query);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $payload = [
            'query' => $this->query,
            'variables' => $this->variables === [] ? (object) [] : $this->variables,
        ];

        if ($this->operationName !== null && $this->operationName !== '') {
            $payload['operationName'] = $this->operationName;
        }

        return $payload;
    }
}
