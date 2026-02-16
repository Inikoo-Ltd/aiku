<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);

            $table->unsignedSmallInteger('employee_id')->index();
            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unsignedInteger('overtime_type_id')->index();
            $table->foreign('overtime_type_id')
                ->references('id')
                ->on('overtime_types')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->string('source', 32)->default('employee');

            $table->date('requested_date');
            $table->dateTimeTz('requested_start_at')->nullable();
            $table->dateTimeTz('requested_end_at')->nullable();
            $table->unsignedInteger('requested_duration_minutes')->default(0);

            $table->text('reason')->nullable();

            $table->string('status', 32)->default('pending');
            $table->text('decision_note')->nullable();

            $table->unsignedSmallInteger('approved_by_employee_id')->nullable()->index();
            $table->foreign('approved_by_employee_id')
                ->references('id')
                ->on('employees')
                ->onUpdate('cascade')
                ->nullOnDelete();

            $table->dateTimeTz('approved_at')->nullable();
            $table->dateTimeTz('rejected_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();

            $table->dateTimeTz('recorded_start_at')->nullable();
            $table->dateTimeTz('recorded_end_at')->nullable();
            $table->unsignedInteger('recorded_duration_minutes')->nullable();

            $table->unsignedSmallInteger('recorded_by_employee_id')->nullable()->index();
            $table->foreign('recorded_by_employee_id')
                ->references('id')
                ->on('employees')
                ->onUpdate('cascade')
                ->nullOnDelete();

            $table->unsignedInteger('lieu_requested_minutes')->default(0);

            $table->unsignedSmallInteger('requested_by_employee_id')->nullable()->index();
            $table->foreign('requested_by_employee_id')
                ->references('id')
                ->on('employees')
                ->onUpdate('cascade')
                ->nullOnDelete();

            $table->timestampsTz();

            $table->index(['organisation_id', 'employee_id', 'requested_date']);
            $table->index(['organisation_id', 'status', 'requested_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
