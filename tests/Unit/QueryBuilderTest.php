<?php

namespace EhsanQ\GraphQL\Tests\Unit;

use EhsanQ\GraphQL\Exceptions\GraphQLException;
use EhsanQ\GraphQL\GraphQLFacade as GraphQL;
use EhsanQ\GraphQL\Tests\TestCase;

class QueryBuilderTest extends TestCase
{
    public function test_select_compiles_a_simple_query(): void
    {
        $query = GraphQL::query('countries')
            ->select('id', 'name', 'code')
            ->toGraphQL();

        $this->assertSame(
            'query { countries { id name code } }',
            $query,
        );
    }

    public function test_where_becomes_graphql_arguments_and_variables(): void
    {
        $builder = GraphQL::query('country')
            ->where('code', 'IN')
            ->select('name', 'capital');

        $this->assertSame(
            'query ($code: String) { country(code: $code) { name capital } }',
            $builder->toGraphQL(),
        );

        $this->assertSame(['code' => 'IN'], $builder->getVariables());
    }

    public function test_multiple_where_and_args(): void
    {
        $builder = GraphQL::query('products')
            ->where('category', 'apple')
            ->where('limit', 20)
            ->select('id', 'name', 'price');

        $this->assertSame(
            'query ($category: String, $limit: Int) { products(category: $category, limit: $limit) { id name price } }',
            $builder->toGraphQL(),
        );

        $this->assertSame([
            'category' => 'apple',
            'limit' => 20,
        ], $builder->getVariables());
    }

    public function test_dotted_select_nests_fields(): void
    {
        $query = GraphQL::query('products')
            ->select('id', 'name', 'category.name')
            ->toGraphQL();

        $this->assertSame(
            'query { products { id name category { name } } }',
            $query,
        );
    }

    public function test_expand_callback_nests_fields(): void
    {
        $query = GraphQL::query('products')
            ->select('id', 'name')
            ->expand('supplier', fn ($q) => $q->select('id', 'name'))
            ->toGraphQL();

        $this->assertSame(
            'query { products { id name supplier { id name } } }',
            $query,
        );
    }

    public function test_on_compiles_an_inline_fragment_on_the_root_field(): void
    {
        $query = GraphQL::query('me')
            ->on('User', fn ($q) => $q
                ->select('id', 'name')
                ->expand('organization', fn ($o) => $o->select('slug', 'id', 'name'))
            )
            ->toGraphQL();

        $this->assertSame(
            'query { me { ... on User { id name organization { slug id name } } } }',
            $query,
        );
    }

    public function test_on_compiles_nested_inline_fragments(): void
    {
        $builder = GraphQL::query('getTestOrder')
            ->where('id', '42')
            ->variableTypes(['id' => 'ID!'])
            ->select('id')
            ->expand('belongsTo', fn ($q) => $q
                ->select('__typename')
                ->on('Organization', fn ($o) => $o->select('id', 'name'))
                ->on('User', fn ($u) => $u->select('id', 'email'))
            );

        $this->assertSame(
            'query ($id: ID!) { getTestOrder(id: $id) { id belongsTo { __typename ... on Organization { id name } ... on User { id email } } } }',
            $builder->toGraphQL(),
        );

        $this->assertSame(['id' => '42'], $builder->getVariables());
    }

    public function test_on_without_callback_returns_a_fragment_builder(): void
    {
        $query = GraphQL::query('me')
            ->on('User')
            ->select('id', 'name')
            ->toGraphQL();

        $this->assertSame(
            'query { me { ... on User { id name } } }',
            $query,
        );
    }

    public function test_on_merges_duplicate_types_into_one_fragment(): void
    {
        $query = GraphQL::query('me')
            ->on('User', fn ($q) => $q->select('id'))
            ->on('User', fn ($q) => $q->select('name'))
            ->toGraphQL();

        $this->assertSame(
            'query { me { ... on User { id name } } }',
            $query,
        );
    }

    public function test_empty_inline_fragment_throws(): void
    {
        $this->expectException(GraphQLException::class);
        $this->expectExceptionMessage('Inline fragment on [User] must select at least one field.');

        GraphQL::query('me')
            ->on('User', fn ($q) => $q)
            ->toGraphQL();
    }

    public function test_inline_fragment_rejects_field_arguments(): void
    {
        $this->expectException(GraphQLException::class);
        $this->expectExceptionMessage('Arguments and aliases cannot be applied to an inline fragment.');

        GraphQL::query('me')
            ->on('User')
            ->where('id', '1')
            ->toGraphQL();
    }

    public function test_expand_without_callback_returns_nested_builder(): void
    {
        $query = GraphQL::query('products')
            ->select('id', 'name')
            ->expand('category')
            ->select('id', 'name')
            ->toGraphQL();

        $this->assertSame(
            'query { products { id name category { id name } } }',
            $query,
        );
    }

    public function test_variables_become_field_arguments(): void
    {
        $builder = GraphQL::query('products')
            ->variables([
                'category' => 'apple',
                'limit' => 20,
            ])
            ->select('id', 'name');

        $this->assertSame(
            'query ($category: String, $limit: Int) { products(category: $category, limit: $limit) { id name } }',
            $builder->toGraphQL(),
        );
    }

    public function test_variable_type_overrides(): void
    {
        $builder = GraphQL::query('createProduct')
            ->args(['input' => ['name' => 'Apple']])
            ->variableTypes(['input' => 'ProductInput!'])
            ->select('id');

        $this->assertSame(
            'query ($input: ProductInput!) { createProduct(input: $input) { id } }',
            $builder->toGraphQL(),
        );
    }

    public function test_where_accepts_a_variable_type(): void
    {
        $builder = GraphQL::query('getTest')
            ->where('id', '42', 'ID!')
            ->select('id');

        $this->assertSame(
            'query ($id: ID!) { getTest(id: $id) { id } }',
            $builder->toGraphQL(),
        );
    }

    public function test_args_accepts_variable_types(): void
    {
        $builder = GraphQL::mutation('createProduct')
            ->args(['input' => ['name' => 'Apple']], ['input' => 'ProductInput!'])
            ->select('id');

        $this->assertSame(
            'mutation ($input: ProductInput!) { createProduct(input: $input) { id } }',
            $builder->toGraphQL(),
        );
    }

    public function test_nested_where_accepts_a_variable_type(): void
    {
        $query = GraphQL::query('farmer')
            ->select('id')
            ->expand('plot', fn ($q) => $q->where('id', '9', 'ID!')->select('location'))
            ->toGraphQL();

        $this->assertSame(
            'query ($id: ID!) { farmer { id plot(id: $id) { location } } }',
            $query,
        );
    }

    public function test_aliases_are_compiled(): void
    {
        $query = GraphQL::query('products')
            ->select(['productName' => 'name', 'id'])
            ->toGraphQL();

        $this->assertSame(
            'query { products { productName: name id } }',
            $query,
        );
    }

    public function test_dynamic_field_helpers_select_leaves(): void
    {
        $query = GraphQL::query('countries')
            ->code()
            ->name()
            ->toGraphQL();

        $this->assertSame(
            'query { countries { code name } }',
            $query,
        );
    }

    public function test_dynamic_field_helper_with_callback_nests(): void
    {
        $query = GraphQL::countries()
            ->name()
            ->continent(fn ($q) => $q->name())
            ->toGraphQL();

        $this->assertSame(
            'query { countries { name continent { name } } }',
            $query,
        );
    }

    public function test_boolean_and_list_types_are_inferred(): void
    {
        $builder = GraphQL::query('products')
            ->where('available', true)
            ->where('ids', [1, 2, 3])
            ->select('id');

        $this->assertSame(
            'query ($available: Boolean, $ids: [Int]) { products(available: $available, ids: $ids) { id } }',
            $builder->toGraphQL(),
        );
    }

    public function test_operation_name_is_compiled(): void
    {
        $query = GraphQL::query('products')
            ->as('GetProducts')
            ->select('id')
            ->toGraphQL();

        $this->assertSame(
            'query GetProducts { products { id } }',
            $query,
        );
    }

    public function test_mutation_builder_uses_mutation_keyword(): void
    {
        $builder = GraphQL::mutation('createProduct')
            ->args(['name' => 'Apple'])
            ->select('id', 'name');

        $this->assertSame(
            'mutation ($name: String) { createProduct(name: $name) { id name } }',
            $builder->toGraphQL(),
        );
    }
}
