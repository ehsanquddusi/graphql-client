<?php

namespace EhsanQ\GraphQL\Auth;

use EhsanQ\GraphQL\GraphQLClient;

class Authentication
{
    /**
     * @param  array{type?: string, token?: string|null, username?: string|null, password?: string|null}  $auth
     */
    public static function apply(GraphQLClient $client, array $auth): GraphQLClient
    {
        return match ($auth['type'] ?? 'none') {
            'bearer' => $client->withToken((string) ($auth['token'] ?? '')),
            'basic' => $client->withBasicAuth(
                (string) ($auth['username'] ?? ''),
                (string) ($auth['password'] ?? ''),
            ),
            default => $client,
        };
    }
}
