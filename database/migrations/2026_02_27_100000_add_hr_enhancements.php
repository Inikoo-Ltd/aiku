<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedSmallInteger('probation_period_days')->default(90)->nullable();

            $table->string('bank_account_name', 50)->nullable();
            $table->string('bank_account_number', 50)->nullable();

            $table->string('insurance_number', 50)->nullable();
            $table->string('religion', 50)->nullable();

            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();

            $table->string('identity_document_issued_by', 255)->nullable();
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->boolean('is_half_day')->default(false)->after('duration_days');
            $table->enum('session', ['Morning', 'Afternoon', 'Full'])->default('Full')->after('is_half_day');
        });

        Schema::table('employee_leave_balances', function (Blueprint $table) {
            $table->unsignedSmallInteger('annual_days')->change();
            $table->unsignedSmallInteger('annual_used')->change();
        });

        if (!Schema::hasTable('hr_announcements')) {
            Schema::create('hr_announcements', function (Blueprint $table) {
                $table->id();
                $table->unsignedMediumInteger('organisation_id')->index();
                $table->unsignedMediumInteger('employee_id')->nullable()->index();
                $table->string('type')->index();
                $table->string('title');
                $table->text('message');
                $table->json('metadata')->nullable();
                $table->boolean('is_read')->default(false)->index();
                $table->timestampTz('created_at')->useCurrent();

                $table->foreign('organisation_id')->references('id')->on('organisations')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_announcements');

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn(['is_half_day', 'session']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'probation_period_days',
                'bank_account_name',
                'bank_account_number',
                'insurance_number',
                'region',
                'contract_start_date',
                'contract_end_date',
                'identity_document_issued_by',
                'religion',
            ]);
        });
    }
};
