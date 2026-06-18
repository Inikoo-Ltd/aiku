<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_contract_id')->nullable()->after('employee_id');
            $table->date('period_start')->nullable()->after('year');
            $table->date('period_end')->nullable()->after('period_start');

            $table->foreign('employee_contract_id')->references('id')->on('employee_contracts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('employee_leave_balances', function (Blueprint $table) {
            $table->dropForeign(['employee_contract_id']);
            $table->dropColumn(['employee_contract_id', 'period_start', 'period_end']);
        });
    }
};
