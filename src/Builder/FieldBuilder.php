<?php

namespace EhsanQ\GraphQL\Builder;

use Closure;
use EhsanQ\GraphQL\Exceptions\GraphQLException;
use EhsanQ\GraphQL\Response\GraphQLResponse;

class FieldBuilder
{
    public function __construct(
        protected OperationBuilder $operation,
        protected FieldNode|InlineFragmentNode $node,
    ) {}

    /**
     * @param  string|array<int|string, string>  ...$fields
     */
    public function select(mixed ...$fields): static
    {
        SelectionSet::apply($this->node, ...$fields);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, string>  $types
     */
    public function args(array $args, array $types = []): static
    {
        $this->fieldNode()->args = array_merge($this->fieldNode()->args, $args);

        if ($types !== []) {
            $this->operation->variableTypes($types);
        }

        return $this;
    }

    public function where(string $name, mixed $value, ?string $type = null): static
    {
        $this->fieldNode()->args[$name] = $value;

        if ($type !== null && $type !== '') {
            $this->operation->variableTypes([$name => $type]);
        }

        return $this;
    }

    public function alias(string $alias): static
    {
        $this->fieldNode()->alias = $alias;

        return $this;
    }

    public function expand(string $field, ?callable $callback = null): static|self
    {
        $child = $this->node->child($field);
        $builder = new self($this->operation, $child);

        if ($callback) {
            $callback($builder);

            return $this;
        }

        return $builder;
    }

    public function on(string $type, ?callable $callback = null): static|self
    {
        $builder = new self($this->operation, $this->node->inlineFragment($type));

        if ($callback) {
            $callback($builder);

            return $this;
        }

        return $builder;
    }

    public function get(): GraphQLResponse
    {
        return $this->operation->get();
    }

    public function send(): GraphQLResponse
    {
        return $this->operation->send();
    }

    public function execute(): GraphQLResponse
    {
        return $this->operation->execute();
    }

    public function toGraphQL(): string
    {
        return $this->operation->toGraphQL();
    }

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->operation->getVariables();
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

        if (method_exists($this->operation, $method)) {
            $result = $this->operation->{$method}(...$parameters);

            return $result === $this->operation ? $this : $result;
        }

        $this->expand($method)->args($this->normalizeMagicArgs($parameters));

        return $this;
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

    protected function fieldNode(): FieldNode
    {
        if (! $this->node instanceof FieldNode) {
            throw new GraphQLException('Arguments and aliases cannot be applied to an inline fragment.');
        }

        return $this->node;
    }
}
