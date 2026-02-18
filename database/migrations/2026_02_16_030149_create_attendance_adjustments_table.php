<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('attendance_adjustments', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedMediumInteger('employee_id')->index();
            $table->string('employee_name')->index();
            $table->unsignedMediumInteger('timesheet_id')->nullable()->index();
            $table->date('date');
            $table->dateTimeTz('original_start_at')->nullable();
            $table->dateTimeTz('original_end_at')->nullable();
            $table->dateTimeTz('requested_start_at')->nullable();
            $table->dateTimeTz('requested_end_at')->nullable();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedMediumInteger('approved_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->text('approval_comment')->nullable();
            $table->json('data')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('timesheet_id')->references('id')->on('timesheets')->onDelete('set null');
            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_adjustments');
    }
};
