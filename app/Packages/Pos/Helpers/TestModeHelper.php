<?php

namespace App\Packages\Pos\Helpers;

use Illuminate\Support\Facades\DB;

class TestModeHelper
{
    public static function isTestMode(): bool
    {
        try {
            $config = DB::table('configs')->where('name', 'test_mode')->first();
            return $config && $config->value === '1';
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function applyFilter($query, string $table)
    {
        if (!self::isTestMode()) {
            $query->where("{$table}.test", 0);
        }
        return $query;
    }

    public static function setTestFlag(array $data): array
    {
        if (self::isTestMode()) {
            $data['test'] = 1;
        }
        return $data;
    }

    public static function purgeTestData(): array
    {
        $deleted = [
            'orders' => DB::table('orders')->where('test', 1)->delete(),
            'products' => DB::table('products')->where('test', 1)->delete(),
            'categories' => DB::table('categories')->where('test', 1)->delete(),
            'users' => DB::table('users')->where('test', 1)->delete(),
        ];

        return $deleted;
    }
}
