<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('group_stock_histories', function (Blueprint $table) {
            $table->index(
                ['group_id', 'is_week', 'is_month', 'is_year', 'date'],
                'group_stock_histories_group_flags_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('group_stock_histories', function (Blueprint $table) {
            $table->dropIndex('group_stock_histories_group_flags_date_index');
        });
    }
};
