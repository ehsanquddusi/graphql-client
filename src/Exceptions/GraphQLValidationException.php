<?php

namespace EhsanQ\GraphQL\Exceptions;

use EhsanQ\GraphQL\Response\GraphQLResponse;
use Throwable;

class GraphQLValidationException extends GraphQLException
{
    public function __construct(
        string $message = 'GraphQL request failed schema validation.',
        ?GraphQLResponse $response = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $response, $code, $previous);
    }
}
