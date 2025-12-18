<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 14:15:00 Makassar Time
 * Description: Add order return stats to warehouse, shop, and organisation stats tables
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('warehouse_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_returns')->default(0)->comment('Total order returns');
            $table->unsignedInteger('number_returns_state_waiting_to_receive')->default(0);
            $table->unsignedInteger('number_returns_state_received')->default(0);
            $table->unsignedInteger('number_returns_state_inspecting')->default(0);
            $table->unsignedInteger('number_returns_state_processed')->default(0);
            $table->unsignedInteger('number_returns_state_cancelled')->default(0);
            $table->unsignedInteger('number_return_items')->default(0);
        });

        Schema::table('shop_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_returns')->default(0)->comment('Total order returns');
            $table->unsignedInteger('number_returns_state_waiting_to_receive')->default(0);
            $table->unsignedInteger('number_returns_state_received')->default(0);
            $table->unsignedInteger('number_returns_state_inspecting')->default(0);
            $table->unsignedInteger('number_returns_state_processed')->default(0);
            $table->unsignedInteger('number_returns_state_cancelled')->default(0);
            $table->unsignedInteger('number_return_items')->default(0);
        });

        // Add return stats to organisation_stats
        Schema::table('organisation_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_returns')->default(0)->comment('Total order returns');
            $table->unsignedInteger('number_returns_state_waiting_to_receive')->default(0);
            $table->unsignedInteger('number_returns_state_received')->default(0);
            $table->unsignedInteger('number_returns_state_inspecting')->default(0);
            $table->unsignedInteger('number_returns_state_processed')->default(0);
            $table->unsignedInteger('number_returns_state_cancelled')->default(0);
            $table->unsignedInteger('number_return_items')->default(0);
        });

        // Add return stats to group_stats if exists
        if (Schema::hasTable('group_stats')) {
            Schema::table('group_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('group_stats', 'number_returns')) {
                    $table->unsignedInteger('number_returns')->default(0)->comment('Total order returns');
                    $table->unsignedInteger('number_returns_state_waiting_to_receive')->default(0);
                    $table->unsignedInteger('number_returns_state_received')->default(0);
                    $table->unsignedInteger('number_returns_state_inspecting')->default(0);
                    $table->unsignedInteger('number_returns_state_processed')->default(0);
                    $table->unsignedInteger('number_returns_state_cancelled')->default(0);
                    $table->unsignedInteger('number_return_items')->default(0);
                }
            });
        }

        // Add return stats to customer_stats
        if (Schema::hasTable('customer_stats')) {
            Schema::table('customer_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('customer_stats', 'number_returns')) {
                    $table->unsignedInteger('number_returns')->default(0)->comment('Total order returns');
                    $table->unsignedInteger('number_returns_state_waiting_to_receive')->default(0);
                    $table->unsignedInteger('number_returns_state_received')->default(0);
                    $table->unsignedInteger('number_returns_state_inspecting')->default(0);
                    $table->unsignedInteger('number_returns_state_processed')->default(0);
                    $table->unsignedInteger('number_returns_state_cancelled')->default(0);
                    $table->unsignedInteger('number_return_items')->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('warehouse_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_returns',
                'number_returns_state_waiting_to_receive',
                'number_returns_state_received',
                'number_returns_state_inspecting',
                'number_returns_state_processed',
                'number_returns_state_cancelled',
                'number_return_items',
            ]);
        });

        Schema::table('shop_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_returns',
                'number_returns_state_waiting_to_receive',
                'number_returns_state_received',
                'number_returns_state_inspecting',
                'number_returns_state_processed',
                'number_returns_state_cancelled',
                'number_return_items',
            ]);
        });

        Schema::table('organisation_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_returns',
                'number_returns_state_waiting_to_receive',
                'number_returns_state_received',
                'number_returns_state_inspecting',
                'number_returns_state_processed',
                'number_returns_state_cancelled',
                'number_return_items',
            ]);
        });

        if (Schema::hasTable('group_stats') && Schema::hasColumn('group_stats', 'number_returns')) {
            Schema::table('group_stats', function (Blueprint $table) {
                $table->dropColumn([
                    'number_returns',
                    'number_returns_state_waiting_to_receive',
                    'number_returns_state_received',
                    'number_returns_state_inspecting',
                    'number_returns_state_processed',
                    'number_returns_state_cancelled',
                    'number_return_items',
                ]);
            });
        }

        if (Schema::hasTable('customer_stats') && Schema::hasColumn('customer_stats', 'number_returns')) {
            Schema::table('customer_stats', function (Blueprint $table) {
                $table->dropColumn([
                    'number_returns',
                    'number_returns_state_waiting_to_receive',
                    'number_returns_state_received',
                    'number_returns_state_inspecting',
                    'number_returns_state_processed',
                    'number_returns_state_cancelled',
                    'number_return_items',
                ]);
            });
        }
    }
};
