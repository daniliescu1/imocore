<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class StrictSearch
{
    public static function trim(string $search): string
    {
        return trim($search);
    }

    /**
     * Collapse whitespace and lowercase for literal substring comparison.
     */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return (string) preg_replace('/\s+/u', '', $value);
    }

    public static function contains(string $haystack, string $needle): bool
    {
        $normalizedNeedle = self::normalize($needle);

        if ($normalizedNeedle === '') {
            return true;
        }

        return str_contains(self::normalize($haystack), $normalizedNeedle);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function whereColumnContains(Builder $query, string $column, string $search): void
    {
        $normalized = self::normalize($search);

        if ($normalized === '') {
            return;
        }

        $query->whereRaw(
            self::sqlNormalizedExpression($column).' LIKE ?',
            ['%'.$normalized.'%']
        );
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function orWhereColumnContains(Builder $query, string $column, string $search): void
    {
        $normalized = self::normalize($search);

        if ($normalized === '') {
            return;
        }

        $query->orWhereRaw(
            self::sqlNormalizedExpression($column).' LIKE ?',
            ['%'.$normalized.'%']
        );
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function whereSpatiuIdentificator(Builder $query, string $search, string $column = 'identificator'): void
    {
        self::whereColumnContains($query, $column, $search);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function whereSpatiuListMatch(Builder $query, string $search): void
    {
        if (self::normalize($search) === '') {
            return;
        }

        $query->where(function (Builder $query) use ($search): void {
            self::whereColumnContains($query, 'identificator', $search);
            self::orWhereColumnContains($query, 'chirias', $search);
            self::orWhereColumnContains($query, 'locator', $search);
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function whereTextFieldsMatch(Builder $query, string $search, array $columns): void
    {
        if (self::normalize($search) === '') {
            return;
        }

        $query->where(function (Builder $query) use ($search, $columns): void {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    self::whereColumnContains($query, $column, $search);
                } else {
                    self::orWhereColumnContains($query, $column, $search);
                }
            }
        });
    }

    private static function sqlNormalizedExpression(string $column): string
    {
        $stripped = "REPLACE(REPLACE(REPLACE({$column}, ' ', ''), char(9), ''), char(10), '')";

        return 'LOWER('.$stripped.')';
    }
}
