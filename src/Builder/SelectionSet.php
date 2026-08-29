<?php

namespace EhsanQ\GraphQL\Builder;

class SelectionSet
{
    /**
     * @param  string|array<int|string, string>  ...$fields
     */
    public static function apply(FieldNode|InlineFragmentNode $node, mixed ...$fields): void
    {
        foreach (self::normalize($fields) as $alias => $field) {
            $node->addPath($field, is_string($alias) ? $alias : null);
        }
    }

    /**
     * @param  array<int, mixed>  $fields
     * @return array<int|string, string>
     */
    protected static function normalize(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            if (is_array($field)) {
                foreach ($field as $key => $value) {
                    if (is_int($key)) {
                        $normalized[] = $value;
                    } else {
                        $normalized[$key] = $value;
                    }
                }

                continue;
            }

            $normalized[] = $field;
        }

        return $normalized;
    }
}
