<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('employee_analytics', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedMediumInteger('employee_id')->index();
            $table->date('period_start');
            $table->date('period_end');

            $table->unsignedSmallInteger('working_days')->default(0);
            $table->unsignedSmallInteger('present_days')->default(0);
            $table->unsignedSmallInteger('absent_days')->default(0);
            $table->unsignedSmallInteger('late_clockins')->default(0);
            $table->unsignedSmallInteger('early_clockouts')->default(0);
            $table->decimal('total_working_hours', 8, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);

            $table->unsignedSmallInteger('total_leave_days')->default(0);
            $table->jsonb('leave_breakdown')->nullable();

            $table->decimal('attendance_percentage', 5, 2)->default(0);
            $table->decimal('avg_daily_hours', 5, 2)->default(0);
            $table->decimal('overtime_ratio', 5, 2)->default(0);

            $table->jsonb('data')->nullable();
            $table->timestampsTz();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->unique(['employee_id', 'period_start', 'period_end']);
            $table->index(['organisation_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_analytics');
    }
};
