<?php

namespace EhsanQ\GraphQL\Builder;

class InlineFragmentNode
{
    use HasSelection;

    public function __construct(
        public string $type,
    ) {}
}
