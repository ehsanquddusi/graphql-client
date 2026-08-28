<?php

namespace EhsanQ\GraphQL\Tests\Unit;

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
