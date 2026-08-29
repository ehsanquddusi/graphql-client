# GraphQL Client for Laravel

A modern GraphQL client that feels like Laravel's `Http` facade: immutable multi-endpoint clients, fluent query builders, and a response object that exposes both GraphQL data and the underlying HTTP response.

GraphQL:

```graphql
query {
  products {
    id
    name
    price
  }
}
```

Client:

```php
use EhsanQ\GraphQL\GraphQLFacade as GraphQL;

$response = GraphQL::query('products')
    ->select('id', 'name', 'price')
    ->get();

$products = $response->data('products');
```

Then inspect the HTTP layer when you need it:

```php
$response->status();
$response->headers();
$response->errors();
$response->http();
```

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12 (or `illuminate/http` + `illuminate/support` outside a full Laravel app)

## Installation

Require the package with Composer:

```bash
composer require ehsanquddusi/graphql-client
```

On Laravel, the service provider and `GraphQL` facade are auto-discovered. You do not need to register them by hand.

Publish the config file if you want to customize defaults:

```bash
php artisan vendor:publish --tag=graphql-config
```

Then set the endpoint (and auth) in `.env` or `config/graphql.php`. See [Configuration](#configuration).

## Configuration

```env
GRAPHQL_ENDPOINT=https://api.example.com/graphql
GRAPHQL_AUTH_TYPE=bearer
GRAPHQL_TOKEN=your-token
GRAPHQL_TIMEOUT=30
GRAPHQL_CONNECT_TIMEOUT=10
```

`config/graphql.php` also covers default headers, basic auth, retries, and query caching. Every setting is overridable at runtime.

## Authentication

GraphQL over HTTP typically sends a Bearer token:

```php
GraphQL::endpoint($endpoint)
    ->withToken($token)
    ->query('products')
    ->select('id', 'name')
    ->get();
```

Helpers:

```php
GraphQL::withToken($token);                 // Bearer (Laravel convention)
GraphQL::bearerToken($token);               // explicit alias
GraphQL::withBasicAuth($user, $password);
GraphQL::withApiKey('X-API-Key', $key);
GraphQL::withHeaders(['Authorization' => '...']);
```

Config `auth.type` can be `none`, `bearer`, or `basic`.

## Multiple APIs

Clients are immutable. One application's Orchardly, Shopify, and GitHub clients never leak endpoint, token, or headers into each other:

```php
$orchardly = GraphQL::endpoint($orchardlyUrl)->withToken($orchardlyToken);
$shopify   = GraphQL::endpoint($shopifyUrl)->withToken($shopifyToken);

$orchardly->query('products')->select('id')->get();
$shopify->query('products')->select('id')->get();
```

## Query builder

`where()` is argument sugar — GraphQL does not get SQL semantics.

### Simple selection

GraphQL:

```graphql
query {
  countries {
    id
    name
    code
  }
}
```

Client:

```php
GraphQL::query('countries')
    ->select('id', 'name', 'code')
    ->get();
```

### Arguments

GraphQL:

```graphql
query ($code: String) {
  country(code: $code) {
    name
    capital
  }
}
```

Client:

```php
GraphQL::query('country')
    ->where('code', 'IN')
    ->select('name', 'capital')
    ->get();
```

Pass a GraphQL type as the third argument when inference is not enough: `where('id', $id, 'ID!')`. You can also set types with `variableTypes(['id' => 'ID!'])`. See [Variables](#variables).

### Multiple arguments

GraphQL:

```graphql
query ($category: String, $limit: Int) {
  products(category: $category, limit: $limit) {
    id
    name
    price
  }
}
```

Client:

```php
GraphQL::query('products')
    ->where('category', 'apple')
    ->where('limit', 20)
    ->select('id', 'name', 'price')
    ->get();
```

Use `args([...])` when you want a full argument map.

### Nested fields

GraphQL:

```graphql
query {
  products {
    id
    name
    category {
      name
    }
    supplier {
      id
      name
    }
  }
}
```

Client:

```php
GraphQL::query('products')
    ->select('id', 'name', 'category.name')
    ->expand('supplier', fn ($q) => $q->select('id', 'name'))
    ->get();
```

`expand('supplier')` without a callback also works, then `select()` on the nested builder.

### Unions and interfaces

GraphQL unions and interfaces cannot be queried as plain fields. Use `on()` to compile an inline fragment (`... on Type`). The callback is the same shape as `expand()`.

GraphQL:

```graphql
query {
  me {
    ... on User {
      id
      name
      organization {
        slug
        id
        name
      }
    }
  }
}
```

Client:

```php
GraphQL::query('me')
    ->on('User', fn ($q) => $q
        ->select('id', 'name')
        ->expand('organization', fn ($o) => $o->select('slug', 'id', 'name'))
    )
    ->get();
```

Nested unions work the same way: `expand()` to the field, then `on()` for each possible type. Fields shared by every member (or on the interface) stay on `select()`; type-specific fields go inside `on()`. `__typename` is a normal field.

GraphQL:

```graphql
query ($id: ID!) {
  getOrder(id: $id) {
    id
    belongsTo {
      __typename
      ... on Organization {
        id
        name
      }
      ... on User {
        id
        email
      }
    }
  }
}
```

Client:

```php
GraphQL::query('getOrder')
    ->where('id', $id, 'ID!')
    ->select('id')
    ->expand('belongsTo', fn ($q) => $q
        ->select('__typename')
        ->on('Organization', fn ($o) => $o->select('id', 'name'))
        ->on('User', fn ($u) => $u->select('id', 'email'))
    )
    ->get();
```

`on('Organization')` without a callback returns a nested builder, matching `expand()`. Calling `on()` twice with the same type merges into one fragment.

`on()` is a builder method. If a schema field is named `on`, select it with `expand('on', ...)` or `select('on')`.

Named fragments, directives, and multi-root documents still use `raw()` or `file()`.

### Dynamic field helpers

GraphQL:

```graphql
query {
  countries {
    code
    name
    continent {
      name
    }
  }
}
```

Client:

```php
GraphQL::countries()
    ->code()
    ->name()
    ->continent(fn ($q) => $q->name())
    ->get();
```

The foundation remains `GraphQL::query('products')`. Magic methods are convenience, not the only API.

## Mutations

Same fluent interface. `get()`, `send()`, and `execute()` all run the operation.

GraphQL:

```graphql
mutation ($name: String) {
  createProduct(name: $name) {
    id
    name
  }
}
```

Client:

```php
GraphQL::mutation('createProduct')
    ->args(['name' => 'Apple'])
    ->select('id', 'name')
    ->send();
```

## Variables

Values are sent as GraphQL variables, not interpolated into the document.

GraphQL:

```graphql
query ($category: String, $limit: Int) {
  products(category: $category, limit: $limit) {
    id
    name
  }
}
```

Client:

```php
GraphQL::query('products')
    ->variables([
        'category' => 'apple',
        'limit' => 20,
    ])
    ->select('id', 'name')
    ->get();
```

Booleans, ints, floats, strings, and lists of scalars are inferred. Pass the GraphQL type as the third argument to `where()` when inference is wrong (`ID!`, `JSON!`, enums) or when the schema requires an input object.

GraphQL:

```graphql
query ($id: ID!) {
  product(id: $id) {
    id
    name
  }
}
```

Client:

```php
GraphQL::query('product')
    ->where('id', $id, 'ID!')
    ->select('id', 'name')
    ->get();
```

For a map of arguments, pass types as the second argument to `args()`, or set them with `variableTypes()`. Both are optional; use whichever is clearer.

GraphQL:

```graphql
mutation ($input: ProductInput!) {
  createProduct(input: $input) {
    id
  }
}
```

Client, types on `args()`:

```php
GraphQL::mutation('createProduct')
    ->args(['input' => $input], ['input' => 'ProductInput!'])
    ->select('id')
    ->send();
```

Client, `variableTypes()` as a separate step (same document):

```php
GraphQL::mutation('createProduct')
    ->args(['input' => $input])
    ->variableTypes(['input' => 'ProductInput!'])
    ->select('id')
    ->send();
```

`variableTypes()` can also override a type after `where()`, or set several types at once:

```php
GraphQL::query('getTest')
    ->where('id', $id)
    ->variableTypes(['id' => 'ID!'])
    ->select('id', 'crop')
    ->get();

GraphQL::mutation('createTestForOrder')
    ->args([
        'id' => $orderId,
        'test' => $test,
    ])
    ->variableTypes([
        'id' => 'ID!',
        'test' => 'TestInput!',
    ])
    ->select('id')
    ->send();
```

## Raw GraphQL and files

When a query is too specific for the builder, send the document as-is:

```graphql
query ($id: ID!) {
  product(id: $id) {
    id
    name
  }
}
```

```php
GraphQL::raw($query);
GraphQL::raw($query, ['id' => '42']);

GraphQL::file('queries/products.graphql')
    ->variables(['limit' => 20])
    ->get();
```

Absolute paths work. Relative paths are resolved against `GRAPHQL_QUERY_PATH`, `resource_path()`, `resource_path('graphql/')`, `base_path()`, then the current working directory.

## HTTP options

These map through to Laravel's HTTP client:

```php
GraphQL::retry(3, 100)
    ->timeout(30)
    ->connectTimeout(10)
    ->withoutVerifying()
    ->query('products')
    ->select('id')
    ->get();
```

## Caching

Opt-in for queries. Mutations are never cached.

```php
GraphQL::cache(300)
    ->query('countries')
    ->select('id', 'name')
    ->get();

GraphQL::cache(300)->cacheKey('countries')->cacheStore('redis');
GraphQL::noCache();
```

Cache keys include the endpoint, document, variables, and a hash of the auth identity — never the raw `Authorization` header. Hits still return `GraphQLResponse`.

## Working with the response

`get()`, `send()`, and `execute()` return a `GraphQLResponse`. GraphQL `data` is decoded JSON: nested **PHP arrays** by default. Convert that payload when you want objects or a collection.

```php
$response = GraphQL::query('products')
    ->select('id', 'name', 'price')
    ->get();

$response->throw();

$all = $response->data();
// ['products' => [['id' => '1', 'name' => 'Apple', 'price' => 2], ...]]

$products = $response->data('products');
$name = $response->data('products.0.name'); // 'Apple'
```

**PHP object** (`stdClass` tree, same shape as the JSON):

```php
$payload = $response->object();
$payload->products[0]->name; // 'Apple'

$products = $response->object('products');
$products[0]->name;
```

**Laravel collection** (lists and maps). Pass a key to collect a nested list:

```php
$products = $response->collect('products');

$products->pluck('name');
$products->firstWhere('id', '1');
$products->map(fn ($product) => $product['name']);
```

`collect()` without a key wraps the whole `data` object (keys like `products`). Scalar fields become a one-item collection.

**Full HTTP payload** (includes `data`, `errors`, and `extensions`) and the raw body:

```php
$response->json();                 // decoded body as array
$response->json('data.products');  // dotted path into the payload
$response->body();                 // raw JSON string
```

HTTP 200 can still contain GraphQL `errors`. That is not treated as a transport failure.

| Method | Meaning |
| --- | --- |
| `successful()` / `failed()` / `status()` | HTTP |
| `hasErrors()` / `errors()` | GraphQL payload |
| `data()` / `data('products')` | GraphQL `data` as arrays |
| `object()` / `object('products')` | GraphQL `data` as `stdClass` |
| `collect()` / `collect('products')` | GraphQL `data` as `Illuminate\Support\Collection` |
| `json()` / `body()` / `headers()` / `header()` | full payload / HTTP |
| `http()` | `Illuminate\Http\Client\Response` |
| `throw()` | HTTP failure, then GraphQL errors |
| `throwIfGraphQLErrors()` | GraphQL errors only |

```php
$response->throw();
$response->throwIfGraphQLErrors();
```

## Testing

The transport is Laravel's HTTP client, so fakes work:

```php
Http::fake([
    'https://api.example.com/graphql' => Http::response([
        'data' => ['products' => []],
    ], 200),
]);

$response = GraphQL::query('products')->select('id')->get();

Http::assertSent(fn ($request) => $request->url() === 'https://api.example.com/graphql');
```

## Standalone PHP

The core talks to a `Transport` interface. Construct a client with Laravel's HTTP factory (no full Laravel app required):

```php
use EhsanQ\GraphQL\GraphQL;
use EhsanQ\GraphQL\GraphQLClient;
use EhsanQ\GraphQL\Transport\LaravelHttpTransport;
use Illuminate\Http\Client\Factory;

$graphql = new GraphQL(fn () => GraphQLClient::fromConfig(
    require 'config/graphql.php',
    new LaravelHttpTransport(new Factory),
));
```

You can also pass any `Transport` implementation of your own.

## Contributing

Bug reports, tests, and pull requests are welcome.

1. Fork the repository and create a feature branch.
2. Install dependencies with `composer install`.
3. Run the suite with `composer test` (or `vendor/bin/phpunit`).
4. Keep changes focused. Match the existing code style and add tests for new behavior — especially client isolation, GraphQL vs HTTP errors, and `Http::fake()` coverage.
5. Open a pull request that explains the problem and how you verified the fix.

Please do not include `vendor/` or secrets. If the change affects the public API, update this README with a GraphQL document and the matching client example.

## License

MIT
