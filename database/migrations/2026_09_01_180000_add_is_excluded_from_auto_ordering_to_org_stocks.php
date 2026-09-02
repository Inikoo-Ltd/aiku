<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('org_stocks', 'is_excluded_from_auto_ordering')) {
                $table->boolean('is_excluded_from_auto_ordering')->default(false)->index();
            }
        });

        foreach (['organisation_inventory_stats', 'group_inventory_stats'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'number_org_stocks_excluded_from_auto_ordering')) {
                    $table->unsignedInteger('number_org_stocks_excluded_from_auto_ordering')->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            if (Schema::hasColumn('org_stocks', 'is_excluded_from_auto_ordering')) {
                $table->dropColumn('is_excluded_from_auto_ordering');
            }
        });

        foreach (['organisation_inventory_stats', 'group_inventory_stats'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'number_org_stocks_excluded_from_auto_ordering')) {
                    $table->dropColumn('number_org_stocks_excluded_from_auto_ordering');
                }
            });
        }
    }
};
