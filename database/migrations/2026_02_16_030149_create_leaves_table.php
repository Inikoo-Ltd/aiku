<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        if (!Schema::hasTable('leaves')) {
            Schema::create('leaves', function (Blueprint $table) {
                $table->increments('id');
                $table = $this->groupOrgRelationship($table);
                $table->unsignedMediumInteger('employee_id')->index();
                $table->string('employee_name')->index();
                $table->enum('type', ['annual', 'medical', 'unpaid'])->default('annual');
                $table->date('start_date');
                $table->date('end_date');
                $table->unsignedSmallInteger('duration_days')->default(1);
                $table->text('reason')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->unsignedMediumInteger('approved_by')->nullable();
                $table->timestampTz('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->json('data')->nullable();
                $table->timestampsTz();
                $table->softDeletesTz();

                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->index(['start_date', 'end_date']);
            });
        }

        if (!Schema::hasTable('employee_leave_balances')) {
            Schema::create('employee_leave_balances', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedMediumInteger('employee_id')->index();
                $table->year('year');
                $table->unsignedSmallInteger('annual_days')->default(0);
                $table->unsignedSmallInteger('annual_used')->default(0);
                $table->unsignedSmallInteger('medical_days')->default(0);
                $table->unsignedSmallInteger('medical_used')->default(0);
                $table->unsignedSmallInteger('unpaid_days')->default(0);
                $table->unsignedSmallInteger('unpaid_used')->default(0);
                $table->timestampsTz();

                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->unique(['employee_id', 'year']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leave_balances');
        Schema::dropIfExists('leaves');
    }
};
