<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('qr_scan_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('organisation_id')->nullable();
            $table->foreign('organisation_id')->references('id')->on('organisations')->nullOnDelete();
            $table->unsignedSmallInteger('workplace_id')->nullable();
            $table->foreign('workplace_id')->references('id')->on('workplaces')->nullOnDelete();
            $table->unsignedSmallInteger('clocking_machine_id')->nullable();
            $table->foreign('clocking_machine_id')->references('id')->on('clocking_machines')->nullOnDelete();
            $table->unsignedSmallInteger('employee_id')->nullable();
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();

            $table->string('qr_token')->nullable();
            $table->timestampTz('scanned_at')->useCurrent();

            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->string('status', 30)->index()->nullable();

            $table->string('reason', 255)->nullable();

            $table->timestampsTz();

            $table->index(['clocking_machine_id', 'scanned_at'], 'idx_qr_scan_logs_machine_time');
            $table->index(['employee_id', 'scanned_at'], 'idx_qr_scan_logs_employee_time');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('qr_scan_logs');
    }
};
