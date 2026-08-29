<?php

namespace EhsanQ\GraphQL\Builder;

use EhsanQ\GraphQL\Exceptions\GraphQLException;

trait HasSelection
{
    /**
     * @var array<string, FieldNode>
     */
    public array $children = [];

    /**
     * @var array<string, InlineFragmentNode>
     */
    public array $inlineFragments = [];

    public function child(string $name, ?string $alias = null): FieldNode
    {
        $key = $alias ? $alias.':'.$name : $name;

        if (! isset($this->children[$key])) {
            $this->children[$key] = new FieldNode($name, $alias);
        }

        return $this->children[$key];
    }

    public function addPath(string $path, ?string $alias = null): FieldNode
    {
        $segments = explode('.', $path);
        $node = $this;

        foreach ($segments as $index => $segment) {
            $isFirst = $index === 0;
            $node = $node->child($segment, $isFirst ? $alias : null);
        }

        return $node;
    }

    public function inlineFragment(string $type): InlineFragmentNode
    {
        $type = trim($type);

        if ($type === '') {
            throw new GraphQLException('Inline fragment type must not be empty.');
        }

        if (! isset($this->inlineFragments[$type])) {
            $this->inlineFragments[$type] = new InlineFragmentNode($type);
        }

        return $this->inlineFragments[$type];
    }

    public function hasSelections(): bool
    {
        return $this->children !== [] || $this->inlineFragments !== [];
    }
}
