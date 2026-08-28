<?php

namespace EhsanQ\GraphQL\Exceptions;

use EhsanQ\GraphQL\Response\GraphQLResponse;
use Throwable;

class GraphQLHttpException extends GraphQLException
{
    public function __construct(
        string $message = 'GraphQL HTTP request failed.',
        ?GraphQLResponse $response = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $response, $code, $previous);
    }

    public function status(): ?int
    {
        return $this->response?->status();
    }
}
