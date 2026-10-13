<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table) {
            $table->decimal('annual_adjustment', 8, 2)->default(0);
            $table->decimal('medical_adjustment', 8, 2)->default(0);
            $table->decimal('unpaid_adjustment', 8, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table) {
            $table->dropColumn(['annual_adjustment', 'medical_adjustment', 'unpaid_adjustment']);
        });
    }
};
