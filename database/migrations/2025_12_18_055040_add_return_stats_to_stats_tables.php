<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 18 Dec 2025 14:15:00 Makassar Time
 * Description: Add order return stats to warehouse, shop, and organisation stats tables
 */

use App\Enums\GoodsIn\Return\ReturnItemStateEnum;
use App\Enums\GoodsIn\Return\ReturnStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('warehouse_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_returns')->default(0)->comment('Total order returns');
            foreach (ReturnStateEnum::cases() as $case) {
                $table->unsignedSmallInteger('number_returns_state_'.$case->snake())->default(0);
            }
            $table->unsignedInteger('number_return_items')->default(0);
            foreach (ReturnItemStateEnum::cases() as $case) {
                $table->unsignedSmallInteger('number_return_items_state_'.$case->snake())->default(0);
            }
        });

        Schema::table('shop_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_returns')->default(0)->comment('Total order returns');
            foreach (ReturnStateEnum::cases() as $case) {
                $table->unsignedSmallInteger('number_returns_state_'.$case->snake())->default(0);
            }
            $table->unsignedInteger('number_return_items')->default(0);
            foreach (ReturnItemStateEnum::cases() as $case) {
                $table->unsignedSmallInteger('number_return_items_state_'.$case->snake())->default(0);
            }
        });

        // Add return stats to organisation_stats
        Schema::table('organisation_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_returns')->default(0)->comment('Total order returns');
            foreach (ReturnStateEnum::cases() as $case) {
                $table->unsignedSmallInteger('number_returns_state_'.$case->snake())->default(0);
            }
            $table->unsignedInteger('number_return_items')->default(0);
            foreach (ReturnItemStateEnum::cases() as $case) {
                $table->unsignedSmallInteger('number_return_items_state_'.$case->snake())->default(0);
            }
        });

        // Add return stats to group_stats if exists
        if (Schema::hasTable('group_stats')) {
            Schema::table('group_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('group_stats', 'number_returns')) {
                    $table->unsignedInteger('number_returns')->default(0)->comment('Total order returns');
                    foreach (ReturnStateEnum::cases() as $case) {
                        $table->unsignedSmallInteger('number_returns_state_'.$case->snake())->default(0);
                    }
                    $table->unsignedInteger('number_return_items')->default(0);
                    foreach (ReturnItemStateEnum::cases() as $case) {
                        $table->unsignedSmallInteger('number_return_items_state_'.$case->snake())->default(0);
                    }
                }
            });
        }

        // Add return stats to customer_stats
        if (Schema::hasTable('customer_stats')) {
            Schema::table('customer_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('customer_stats', 'number_returns')) {
                    $table->unsignedInteger('number_returns')->default(0)->comment('Total order returns');
                    foreach (ReturnStateEnum::cases() as $case) {
                        $table->unsignedSmallInteger('number_returns_state_'.$case->snake())->default(0);
                    }
                    $table->unsignedInteger('number_return_items')->default(0);
                    foreach (ReturnItemStateEnum::cases() as $case) {
                        $table->unsignedSmallInteger('number_return_items_state_'.$case->snake())->default(0);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Warehouse stats
        if (Schema::hasTable('warehouse_stats')) {
            Schema::table('warehouse_stats', function (Blueprint $table) {
                if (Schema::hasColumn('warehouse_stats', 'number_returns')) {
                    $table->dropColumn('number_returns');
                }
                foreach (ReturnStateEnum::cases() as $case) {
                    $col = 'number_returns_state_'.$case->snake();
                    if (Schema::hasColumn('warehouse_stats', $col)) {
                        $table->dropColumn($col);
                    }
                }
                if (Schema::hasColumn('warehouse_stats', 'number_return_items')) {
                    $table->dropColumn('number_return_items');
                }
                foreach (ReturnItemStateEnum::cases() as $case) {
                    $col = 'number_return_items_state_'.$case->snake();
                    if (Schema::hasColumn('warehouse_stats', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // Shop stats
        if (Schema::hasTable('shop_stats')) {
            Schema::table('shop_stats', function (Blueprint $table) {
                if (Schema::hasColumn('shop_stats', 'number_returns')) {
                    $table->dropColumn('number_returns');
                }
                foreach (ReturnStateEnum::cases() as $case) {
                    $col = 'number_returns_state_'.$case->snake();
                    if (Schema::hasColumn('shop_stats', $col)) {
                        $table->dropColumn($col);
                    }
                }
                if (Schema::hasColumn('shop_stats', 'number_return_items')) {
                    $table->dropColumn('number_return_items');
                }
                foreach (ReturnItemStateEnum::cases() as $case) {
                    $col = 'number_return_items_state_'.$case->snake();
                    if (Schema::hasColumn('shop_stats', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // Organisation stats
        if (Schema::hasTable('organisation_stats')) {
            Schema::table('organisation_stats', function (Blueprint $table) {
                if (Schema::hasColumn('organisation_stats', 'number_returns')) {
                    $table->dropColumn('number_returns');
                }
                foreach (ReturnStateEnum::cases() as $case) {
                    $col = 'number_returns_state_'.$case->snake();
                    if (Schema::hasColumn('organisation_stats', $col)) {
                        $table->dropColumn($col);
                    }
                }
                if (Schema::hasColumn('organisation_stats', 'number_return_items')) {
                    $table->dropColumn('number_return_items');
                }
                foreach (ReturnItemStateEnum::cases() as $case) {
                    $col = 'number_return_items_state_'.$case->snake();
                    if (Schema::hasColumn('organisation_stats', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // Group stats (optional table)
        if (Schema::hasTable('group_stats')) {
            Schema::table('group_stats', function (Blueprint $table) {
                if (Schema::hasColumn('group_stats', 'number_returns')) {
                    $table->dropColumn('number_returns');
                }
                foreach (ReturnStateEnum::cases() as $case) {
                    $col = 'number_returns_state_'.$case->snake();
                    if (Schema::hasColumn('group_stats', $col)) {
                        $table->dropColumn($col);
                    }
                }
                if (Schema::hasColumn('group_stats', 'number_return_items')) {
                    $table->dropColumn('number_return_items');
                }
                foreach (ReturnItemStateEnum::cases() as $case) {
                    $col = 'number_return_items_state_'.$case->snake();
                    if (Schema::hasColumn('group_stats', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // Customer stats (optional table)
        if (Schema::hasTable('customer_stats')) {
            Schema::table('customer_stats', function (Blueprint $table) {
                if (Schema::hasColumn('customer_stats', 'number_returns')) {
                    $table->dropColumn('number_returns');
                }
                foreach (ReturnStateEnum::cases() as $case) {
                    $col = 'number_returns_state_'.$case->snake();
                    if (Schema::hasColumn('customer_stats', $col)) {
                        $table->dropColumn($col);
                    }
                }
                if (Schema::hasColumn('customer_stats', 'number_return_items')) {
                    $table->dropColumn('number_return_items');
                }
                foreach (ReturnItemStateEnum::cases() as $case) {
                    $col = 'number_return_items_state_'.$case->snake();
                    if (Schema::hasColumn('customer_stats', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
