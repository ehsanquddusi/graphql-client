<?php

namespace EhsanQ\GraphQL\Builder;

class FieldNode
{
    use HasSelection;

    /**
     * @param  array<string, mixed>  $args
     */
    public function __construct(
        public string $name,
        public ?string $alias = null,
        public array $args = [],
    ) {}
}
