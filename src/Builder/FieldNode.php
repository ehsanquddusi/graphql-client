<?php

namespace EhsanQ\GraphQL\Builder;

class FieldNode
{
    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, FieldNode>  $children
     */
    public function __construct(
        public string $name,
        public ?string $alias = null,
        public array $args = [],
        public array $children = [],
    ) {}

    public function child(string $name, ?string $alias = null): self
    {
        $key = $alias ? $alias.':'.$name : $name;

        if (! isset($this->children[$key])) {
            $this->children[$key] = new self($name, $alias);
        }

        return $this->children[$key];
    }

    public function addPath(string $path, ?string $alias = null): self
    {
        $segments = explode('.', $path);
        $node = $this;

        foreach ($segments as $index => $segment) {
            $isFirst = $index === 0;
            $node = $node->child($segment, $isFirst ? $alias : null);
        }

        return $node;
    }
}
