<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Created: 2026-06-11
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('employee_id')->index();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('annual_leave_days', 5, 1)->default(10.0);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};
