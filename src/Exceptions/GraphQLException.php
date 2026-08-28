<?php

namespace EhsanQ\GraphQL\Exceptions;

use EhsanQ\GraphQL\Response\GraphQLResponse;
use RuntimeException;
use Throwable;

class GraphQLException extends RuntimeException
{
    public function __construct(
        string $message = 'GraphQL request failed.',
        public readonly ?GraphQLResponse $response = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function errors(): array
    {
        return $this->response?->errors() ?? [];
    }

    public function data(mixed $key = null): mixed
    {
        return $this->response?->data($key);
    }
}
