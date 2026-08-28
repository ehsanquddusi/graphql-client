<?php

namespace EhsanQ\GraphQL\Builder;

use EhsanQ\GraphQL\Exceptions\GraphQLException;

class DocumentCompiler
{
    /**
     * @var array<string, mixed>
     */
    protected array $variables = [];

    /**
     * @var array<string, string>
     */
    protected array $variableTypes = [];

    /**
     * @param  array<string, string>  $typeOverrides
     */
    public function __construct(
        protected array $typeOverrides = [],
    ) {}

    /**
     * @return array{query: string, variables: array<string, mixed>}
     */
    public function compile(string $operation, FieldNode $root, ?string $operationName = null): array
    {
        $this->variables = [];
        $this->variableTypes = [];

        $selection = $this->compileNode($root);
        $definitions = $this->compileDefinitions();

        $name = $operationName ? ' '.$operationName : '';
        $defs = $definitions !== '' ? " ({$definitions})" : '';

        return [
            'query' => "{$operation}{$name}{$defs} { {$selection} }",
            'variables' => $this->variables,
        ];
    }

    protected function compileNode(FieldNode $node): string
    {
        $alias = $node->alias ? $node->alias.': ' : '';
        $args = $this->compileArgs($node->args);
        $out = $alias.$node->name.($args !== '' ? "({$args})" : '');

        if ($node->children === []) {
            return $out;
        }

        $children = [];

        foreach ($node->children as $child) {
            $children[] = $this->compileNode($child);
        }

        return $out.' { '.implode(' ', $children).' }';
    }

    /**
     * @param  array<string, mixed>  $args
     */
    protected function compileArgs(array $args): string
    {
        if ($args === []) {
            return '';
        }

        $parts = [];

        foreach ($args as $name => $value) {
            $variable = $this->variableName((string) $name);
            $this->variables[$variable] = $value;
            $this->variableTypes[$variable] = $this->typeOverrides[$name]
                ?? $this->typeOverrides[$variable]
                ?? $this->inferType($value);

            $parts[] = $name.': $'.$variable;
        }

        return implode(', ', $parts);
    }

    protected function compileDefinitions(): string
    {
        if ($this->variables === []) {
            return '';
        }

        $parts = [];

        foreach ($this->variables as $name => $value) {
            $parts[] = '$'.$name.': '.$this->variableTypes[$name];
        }

        return implode(', ', $parts);
    }

    protected function variableName(string $name): string
    {
        $base = preg_replace('/[^A-Za-z0-9_]/', '_', $name) ?: 'var';
        $candidate = $base;
        $i = 2;

        while (array_key_exists($candidate, $this->variables)) {
            $candidate = $base.'_'.$i;
            $i++;
        }

        return $candidate;
    }

    protected function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'Boolean',
            is_int($value) => 'Int',
            is_float($value) => 'Float',
            is_array($value) && array_is_list($value) => $this->inferListType($value),
            is_array($value) => throw new GraphQLException(
                'Cannot infer GraphQL type for an input object. Pass variableTypes() with the input type name.',
            ),
            default => 'String',
        };
    }

    /**
     * @param  array<int, mixed>  $value
     */
    protected function inferListType(array $value): string
    {
        $first = $value[0] ?? null;

        $inner = match (true) {
            $value === [], is_string($first), $first === null => 'String',
            is_bool($first) => 'Boolean',
            is_int($first) => 'Int',
            is_float($first) => 'Float',
            default => throw new GraphQLException(
                'Cannot infer GraphQL type for a list of input objects. Pass variableTypes() with the list type.',
            ),
        };

        return '['.$inner.']';
    }
}
