<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Dec 2025 12:56:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


use App\Enums\Dispatching\WaitingItem\WaitingItemStateEnum;
use App\Enums\Dispatching\WaitingItem\WaitingItemStatusEnum;
use App\Enums\Dispatching\WaitingItem\WaitingItemTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // delivery_note_stats
        if (Schema::hasTable('delivery_note_stats')) {
            Schema::table('delivery_note_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('delivery_note_stats', 'number_waiting_items')) {
                    $table->unsignedSmallInteger('number_waiting_items')
                        ->default(0)
                        ->comment('current number of waiting items');
                }

                foreach (WaitingItemTypeEnum::cases() as $case) {
                    $column = 'number_waiting_items_type_'.$case->snake();
                    if (!Schema::hasColumn('delivery_note_stats', $column)) {
                        $table->unsignedSmallInteger($column)->default(0);
                    }
                }

                foreach (WaitingItemStateEnum::cases() as $case) {
                    $column = 'number_waiting_items_state_'.$case->snake();
                    if (!Schema::hasColumn('delivery_note_stats', $column)) {
                        $table->unsignedSmallInteger($column)->default(0);
                    }
                }

                foreach (WaitingItemStatusEnum::cases() as $case) {
                    $column = 'number_waiting_items_status_'.$case->snake();
                    if (!Schema::hasColumn('delivery_note_stats', $column)) {
                        $table->unsignedSmallInteger($column)->default(0);
                    }
                }

                foreach (WaitingItemTypeEnum::cases() as $case) {
                    foreach (WaitingItemStateEnum::cases() as $case2) {
                        $column = 'number_waiting_items_type_'.$case->snake().'_state_'.$case2->snake();
                        if (!Schema::hasColumn('delivery_note_stats', $column)) {
                            $table->unsignedSmallInteger($column)->default(0);
                        }
                    }

                    foreach (WaitingItemStatusEnum::cases() as $case2) {
                        $column = 'number_waiting_items_type_'.$case->snake().'_status_'.$case2->snake();
                        if (!Schema::hasColumn('delivery_note_stats', $column)) {
                            $table->unsignedSmallInteger($column)->default(0);
                        }
                    }
                }
            });
        }

        // warehouse_stats
        if (Schema::hasTable('warehouse_stats')) {
            Schema::table('warehouse_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('warehouse_stats', 'number_waiting_items')) {
                    $table->unsignedSmallInteger('number_waiting_items')
                        ->default(0)
                        ->comment('current number of waiting items');
                }

                foreach (WaitingItemTypeEnum::cases() as $case) {
                    $column = 'number_waiting_items_type_'.$case->snake();
                    if (!Schema::hasColumn('warehouse_stats', $column)) {
                        $table->unsignedSmallInteger($column)->default(0);
                    }
                }

                foreach (WaitingItemStateEnum::cases() as $case) {
                    $column = 'number_waiting_items_state_'.$case->snake();
                    if (!Schema::hasColumn('warehouse_stats', $column)) {
                        $table->unsignedSmallInteger($column)->default(0);
                    }
                }

                foreach (WaitingItemStatusEnum::cases() as $case) {
                    $column = 'number_waiting_items_status_'.$case->snake();
                    if (!Schema::hasColumn('warehouse_stats', $column)) {
                        $table->unsignedSmallInteger($column)->default(0);
                    }
                }

                foreach (WaitingItemTypeEnum::cases() as $case) {
                    foreach (WaitingItemStateEnum::cases() as $case2) {
                        $column = 'number_waiting_items_type_'.$case->snake().'_state_'.$case2->snake();
                        if (!Schema::hasColumn('warehouse_stats', $column)) {
                            $table->unsignedSmallInteger($column)->default(0);
                        }
                    }

                    foreach (WaitingItemStatusEnum::cases() as $case2) {
                        $column = 'number_waiting_items_type_'.$case->snake().'_status_'.$case2->snake();
                        if (!Schema::hasColumn('warehouse_stats', $column)) {
                            $table->unsignedSmallInteger($column)->default(0);
                        }
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // delivery_note_stats
        if (Schema::hasTable('delivery_note_stats')) {
            Schema::table('delivery_note_stats', function (Blueprint $table) {
                if (Schema::hasColumn('delivery_note_stats', 'number_waiting_items')) {
                    $table->dropColumn('number_waiting_items');
                }

                foreach (WaitingItemTypeEnum::cases() as $case) {
                    $column = 'number_waiting_items_type_'.$case->snake();
                    if (Schema::hasColumn('delivery_note_stats', $column)) {
                        $table->dropColumn($column);
                    }
                }

                foreach (WaitingItemStateEnum::cases() as $case) {
                    $column = 'number_waiting_items_state_'.$case->snake();
                    if (Schema::hasColumn('delivery_note_stats', $column)) {
                        $table->dropColumn($column);
                    }
                }

                foreach (WaitingItemStatusEnum::cases() as $case) {
                    $column = 'number_waiting_items_status_'.$case->snake();
                    if (Schema::hasColumn('delivery_note_stats', $column)) {
                        $table->dropColumn($column);
                    }
                }

                foreach (WaitingItemTypeEnum::cases() as $case) {
                    foreach (WaitingItemStateEnum::cases() as $case2) {
                        $column = 'number_waiting_items_type_'.$case->snake().'_state_'.$case2->snake();
                        if (Schema::hasColumn('delivery_note_stats', $column)) {
                            $table->dropColumn($column);
                        }
                    }

                    foreach (WaitingItemStatusEnum::cases() as $case2) {
                        $column = 'number_waiting_items_type_'.$case->snake().'_status_'.$case2->snake();
                        if (Schema::hasColumn('delivery_note_stats', $column)) {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }

        // warehouse_stats
        if (Schema::hasTable('warehouse_stats')) {
            Schema::table('warehouse_stats', function (Blueprint $table) {
                if (Schema::hasColumn('warehouse_stats', 'number_waiting_items')) {
                    $table->dropColumn('number_waiting_items');
                }

                foreach (WaitingItemTypeEnum::cases() as $case) {
                    $column = 'number_waiting_items_type_'.$case->snake();
                    if (Schema::hasColumn('warehouse_stats', $column)) {
                        $table->dropColumn($column);
                    }
                }

                foreach (WaitingItemStateEnum::cases() as $case) {
                    $column = 'number_waiting_items_state_'.$case->snake();
                    if (Schema::hasColumn('warehouse_stats', $column)) {
                        $table->dropColumn($column);
                    }
                }

                foreach (WaitingItemStatusEnum::cases() as $case) {
                    $column = 'number_waiting_items_status_'.$case->snake();
                    if (Schema::hasColumn('warehouse_stats', $column)) {
                        $table->dropColumn($column);
                    }
                }

                foreach (WaitingItemTypeEnum::cases() as $case) {
                    foreach (WaitingItemStateEnum::cases() as $case2) {
                        $column = 'number_waiting_items_type_'.$case->snake().'_state_'.$case2->snake();
                        if (Schema::hasColumn('warehouse_stats', $column)) {
                            $table->dropColumn($column);
                        }
                    }

                    foreach (WaitingItemStatusEnum::cases() as $case2) {
                        $column = 'number_waiting_items_type_'.$case->snake().'_status_'.$case2->snake();
                        if (Schema::hasColumn('warehouse_stats', $column)) {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }
    }
};
