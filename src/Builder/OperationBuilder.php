<?php

namespace EhsanQ\GraphQL\Builder;

use Closure;
use EhsanQ\GraphQL\Concerns\ConfiguresClient;
use EhsanQ\GraphQL\GraphQLClient;
use EhsanQ\GraphQL\Request\GraphQLRequest;
use EhsanQ\GraphQL\Response\GraphQLResponse;

abstract class OperationBuilder
{
    use ConfiguresClient;

    protected FieldNode $root;

    /**
     * @var array<string, mixed>
     */
    protected array $variables = [];

    /**
     * @var array<string, string>
     */
    protected array $variableTypes = [];

    protected ?string $operationName = null;

    public function __construct(
        GraphQLClient $client,
        string $field,
        protected string $operation = 'query',
    ) {
        $this->client = $client;
        $this->root = new FieldNode($field);
    }

    /**
     * @param  string|array<int|string, string>  ...$fields
     */
    public function select(mixed ...$fields): static
    {
        SelectionSet::apply($this->root, ...$fields);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function args(array $args): static
    {
        $this->root->args = array_merge($this->root->args, $args);

        return $this;
    }

    public function where(string $name, mixed $value): static
    {
        $this->root->args[$name] = $value;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function variables(array $variables): static
    {
        $this->variables = array_merge($this->variables, $variables);
        $this->root->args = array_merge($this->root->args, $variables);

        return $this;
    }

    /**
     * @param  array<string, string>  $types
     */
    public function variableTypes(array $types): static
    {
        $this->variableTypes = array_merge($this->variableTypes, $types);

        return $this;
    }

    public function operationName(string $name): static
    {
        $this->operationName = $name;

        return $this;
    }

    public function as(string $name): static
    {
        return $this->operationName($name);
    }

    public function expand(string $field, ?callable $callback = null): static|FieldBuilder
    {
        $nested = $this->field()->expand($field, $callback);

        return $callback ? $this : $nested;
    }

    public function field(): FieldBuilder
    {
        return new FieldBuilder($this, $this->root);
    }

    public function toGraphQL(): string
    {
        return $this->compile()['query'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->compile()['variables'];
    }

    public function toRequest(): GraphQLRequest
    {
        $compiled = $this->compile();

        return $this->client->toRequest(
            $compiled['query'],
            $compiled['variables'],
            $this->operationName,
            $this->operation,
        );
    }

    public function execute(): GraphQLResponse
    {
        return $this->client->execute($this->toRequest());
    }

    public function __call(string $method, array $parameters): mixed
    {
        if (isset($parameters[0]) && $parameters[0] instanceof Closure) {
            return $this->expand($method, $parameters[0]);
        }

        if ($parameters === []) {
            $this->select($method);

            return $this;
        }

        $this->args(isset($parameters[0]) && is_array($parameters[0]) && ! array_is_list($parameters[0])
            ? $parameters[0]
            : [$method => $parameters[0] ?? $parameters]);

        return $this;
    }

    /**
     * @return array{query: string, variables: array<string, mixed>}
     */
    protected function compile(): array
    {
        return (new DocumentCompiler($this->variableTypes))
            ->compile($this->operation, $this->root, $this->operationName);
    }
}
