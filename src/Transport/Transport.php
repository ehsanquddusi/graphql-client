<?php

namespace EhsanQ\GraphQL\Transport;

use EhsanQ\GraphQL\Request\GraphQLRequest;
use EhsanQ\GraphQL\Response\GraphQLResponse;

interface Transport
{
    public function send(GraphQLRequest $request): GraphQLResponse;
}
