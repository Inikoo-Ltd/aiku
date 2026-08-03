<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table) {
            $table->dropColumn(['year', 'annual_days']);
        });
    }

    public function down(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_leave_balances', 'year')) {
                $table->unsignedSmallInteger('year')->nullable();
            }
            if (!Schema::hasColumn('employee_leave_balances', 'annual_days')) {
                $table->unsignedSmallInteger('annual_days')->default(0);
            }
        });
    }
};
