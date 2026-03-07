<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'probation_period_days')) {
                $table->unsignedSmallInteger('probation_period_days')->default(90)->nullable();
            }
            if (!Schema::hasColumn('employees', 'bank_account_name')) {
                $table->string('bank_account_name', 50)->nullable();
            }
            if (!Schema::hasColumn('employees', 'bank_account_number')) {
                $table->string('bank_account_number', 50)->nullable();
            }
            if (!Schema::hasColumn('employees', 'insurance_number')) {
                $table->string('insurance_number', 50)->nullable();
            }
            if (!Schema::hasColumn('employees', 'religion')) {
                $table->string('religion', 50)->nullable();
            }
            if (!Schema::hasColumn('employees', 'contract_start_date')) {
                $table->date('contract_start_date')->nullable();
            }
            if (!Schema::hasColumn('employees', 'contract_end_date')) {
                $table->date('contract_end_date')->nullable();
            }
            if (!Schema::hasColumn('employees', 'identity_document_issued_by')) {
                $table->string('identity_document_issued_by', 255)->nullable();
            }
        });

        Schema::table('leaves', function (Blueprint $table) {
            if (!Schema::hasColumn('leaves', 'is_half_day')) {
                $table->boolean('is_half_day')->default(false)->after('duration_days');
            }
            if (!Schema::hasColumn('leaves', 'session')) {
                $table->enum('session', ['Morning', 'Afternoon', 'Full'])->default('Full')->after('is_half_day');
            }
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
            if (Schema::hasColumn('leaves', 'is_half_day')) {
                $table->dropColumn(['is_half_day', 'session']);
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'probation_period_days')) {
                $table->dropColumn('probation_period_days');
            }
            if (Schema::hasColumn('employees', 'bank_account_name')) {
                $table->dropColumn('bank_account_name');
            }
            if (Schema::hasColumn('employees', 'bank_account_number')) {
                $table->dropColumn('bank_account_number');
            }
            if (Schema::hasColumn('employees', 'insurance_number')) {
                $table->dropColumn('insurance_number');
            }
            if (Schema::hasColumn('employees', 'region')) {
                $table->dropColumn('region');
            }
            if (Schema::hasColumn('employees', 'contract_start_date')) {
                $table->dropColumn('contract_start_date');
            }
            if (Schema::hasColumn('employees', 'contract_end_date')) {
                $table->dropColumn('contract_end_date');
            }
            if (Schema::hasColumn('employees', 'identity_document_issued_by')) {
                $table->dropColumn('identity_document_issued_by');
            }
            if (Schema::hasColumn('employees', 'religion')) {
                $table->dropColumn('religion');
            }
        });
    }
};
