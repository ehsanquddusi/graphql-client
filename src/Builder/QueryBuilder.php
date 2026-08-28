<?php

namespace EhsanQ\GraphQL\Builder;

use EhsanQ\GraphQL\GraphQLClient;

class QueryBuilder extends OperationBuilder
{
    public function __construct(GraphQLClient $client, string $field)
    {
        parent::__construct($client, $field, 'query');
    }
}
