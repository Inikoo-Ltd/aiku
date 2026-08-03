<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $columns = [
            'master_shops' => ['cost_price_ratio', 'price_rrp_ratio', 'price_rrp_warning_ratio'],
            'master_assets' => ['cost_price_ratio'],
            'master_product_categories' => ['cost_price_ratio'],
            'shops' => ['cost_price_ratio', 'price_rrp_ratio'],
            'product_categories' => ['cost_price_ratio'],
            'products' => ['cost_price_ratio'],
        ];

        foreach ($columns as $tableName => $tableColumns) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            $columnsToDrop = array_filter($tableColumns, fn (string $column): bool => Schema::hasColumn($tableName, $column));

            if ($columnsToDrop !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($columnsToDrop): void {
                    $table->dropColumn($columnsToDrop);
                });
            }
        }
    }

    public function down(): void
    {
        $columns = [
            'master_shops' => [
                'cost_price_ratio' => function (Blueprint $table): void {
                    $table->decimal('cost_price_ratio', 16, 3)->default(2);
                },
                'price_rrp_ratio' => function (Blueprint $table): void {
                    $table->decimal('price_rrp_ratio', 16, 3)->default(4);
                },
                'price_rrp_warning_ratio' => function (Blueprint $table): void {
                    $table->float('price_rrp_warning_ratio')->default(0);
                },
            ],
            'master_assets' => [
                'cost_price_ratio' => function (Blueprint $table): void {
                    $table->decimal('cost_price_ratio', 16, 3)->nullable();
                },
            ],
            'master_product_categories' => [
                'cost_price_ratio' => function (Blueprint $table): void {
                    $table->decimal('cost_price_ratio', 16, 3)->nullable();
                },
            ],
            'shops' => [
                'cost_price_ratio' => function (Blueprint $table): void {
                    $table->decimal('cost_price_ratio', 16, 3)->default(2);
                },
                'price_rrp_ratio' => function (Blueprint $table): void {
                    $table->decimal('price_rrp_ratio', 16, 3)->default(4);
                },
            ],
            'product_categories' => [
                'cost_price_ratio' => function (Blueprint $table): void {
                    $table->decimal('cost_price_ratio', 16, 3)->nullable();
                },
            ],
            'products' => [
                'cost_price_ratio' => function (Blueprint $table): void {
                    $table->decimal('cost_price_ratio', 16, 3)->nullable();
                },
            ],
        ];

        foreach ($columns as $tableName => $tableColumns) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            $columnsToAdd = array_filter(
                $tableColumns,
                fn (string $column): bool => !Schema::hasColumn($tableName, $column),
                ARRAY_FILTER_USE_KEY
            );

            if ($columnsToAdd !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($columnsToAdd): void {
                    foreach ($columnsToAdd as $addColumn) {
                        $addColumn($table);
                    }
                });
            }
        }
    }
};
