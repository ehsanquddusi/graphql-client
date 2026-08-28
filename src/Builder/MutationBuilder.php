<?php

namespace EhsanQ\GraphQL\Builder;

use EhsanQ\GraphQL\GraphQLClient;

class MutationBuilder extends OperationBuilder
{
    public function __construct(GraphQLClient $client, string $field)
    {
        parent::__construct($client, $field, 'mutation');
    }
}
