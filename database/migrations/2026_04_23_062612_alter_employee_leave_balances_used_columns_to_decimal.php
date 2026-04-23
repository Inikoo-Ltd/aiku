<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table) {
            $table->decimal('annual_used', 8, 2)->default(0)->change();
            $table->decimal('medical_used', 8, 2)->default(0)->change();
            $table->decimal('unpaid_used', 8, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table) {
            $table->unsignedSmallInteger('annual_used')->default(0)->change();
            $table->unsignedSmallInteger('medical_used')->default(0)->change();
            $table->unsignedSmallInteger('unpaid_used')->default(0)->change();
        });
    }
};
