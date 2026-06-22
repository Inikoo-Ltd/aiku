<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table) {
            $table->dropColumn(['medical_days', 'unpaid_days']);
        });
    }

    public function down(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table) {
            $table->unsignedSmallInteger('medical_days')->default(0);
            $table->unsignedSmallInteger('unpaid_days')->default(0);
        });
    }
};
