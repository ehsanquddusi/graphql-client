<?php

namespace EhsanQ\GraphQL\Transport;

use EhsanQ\GraphQL\Exceptions\GraphQLHttpException;
use EhsanQ\GraphQL\Request\GraphQLRequest;
use EhsanQ\GraphQL\Response\GraphQLResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;

class LaravelHttpTransport implements Transport
{
    public function __construct(
        protected Factory $http,
    ) {}

    public function send(GraphQLRequest $request): GraphQLResponse
    {
        $pending = $this->prepare($request);

        try {
            $response = $pending->post($request->endpoint, $request->payload());
        } catch (ConnectionException $exception) {
            throw new GraphQLHttpException(
                'GraphQL HTTP connection failed: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        return GraphQLResponse::fromHttp($response);
    }

    protected function prepare(GraphQLRequest $request): PendingRequest
    {
        $pending = $this->http
            ->withHeaders($request->headers)
            ->acceptJson()
            ->asJson()
            ->timeout($request->timeout ?? 30)
            ->connectTimeout($request->connectTimeout ?? 10);

        if ($request->token !== null && $request->token !== '') {
            $pending = $pending->withToken($request->token);
        }

        if ($request->basicAuth !== null) {
            $pending = $pending->withBasicAuth($request->basicAuth[0], $request->basicAuth[1]);
        }

        if ($request->retryTimes > 0) {
            $pending = $pending->retry(
                $request->retryTimes,
                $request->retrySleep,
                $request->retryWhen,
                false,
            );
        }

        if ($request->withoutVerifying) {
            $pending = $pending->withoutVerifying();
        }

        return $pending;
    }
}
