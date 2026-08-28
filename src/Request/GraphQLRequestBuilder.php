<?php

namespace EhsanQ\GraphQL\Request;

use EhsanQ\GraphQL\Concerns\ConfiguresClient;
use EhsanQ\GraphQL\GraphQLClient;
use EhsanQ\GraphQL\Response\GraphQLResponse;

class GraphQLRequestBuilder
{
    use ConfiguresClient;

    /**
     * @param  array<string, mixed>  $variables
     */
    public function __construct(
        GraphQLClient $client,
        protected string $query,
        protected array $variables = [],
        protected ?string $operationName = null,
        protected string $operationType = 'raw',
    ) {
        $this->client = $client;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function variables(array $variables): static
    {
        $this->variables = array_merge($this->variables, $variables);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function with(array $variables): static
    {
        return $this->variables($variables);
    }

    public function operationName(string $name): static
    {
        $this->operationName = $name;

        return $this;
    }

    public function toGraphQL(): string
    {
        return $this->query;
    }

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    public function toRequest(): GraphQLRequest
    {
        $type = $this->operationType;

        if ($type === 'raw' && preg_match('/^\s*mutation\b/i', $this->query)) {
            $type = 'mutation';
        } elseif ($type === 'raw' && preg_match('/^\s*query\b/i', $this->query)) {
            $type = 'query';
        }

        return $this->client->toRequest(
            $this->query,
            $this->variables,
            $this->operationName,
            $type,
        );
    }

    public function execute(): GraphQLResponse
    {
        return $this->client->execute($this->toRequest());
    }
}
