<?php


use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        if (!Schema::hasTable('employee_overtime_allowances')) {
            Schema::create('employee_overtime_allowances', function (Blueprint $table) {
                $table->id();
                $table = $this->groupOrgRelationship($table);

                $table->unsignedSmallInteger('employee_id')->index();
                $table->foreign('employee_id')
                    ->references('id')
                    ->on('employees')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');

                $table->date('period_start_date');
                $table->date('period_end_date');

                $table->unsignedInteger('opening_minutes')->default(0);
                $table->unsignedInteger('booked_minutes')->default(0);
                $table->unsignedInteger('remaining_minutes')->default(0);

                $table->string('unit', 16)->default('minutes');

                $table->text('notes')->nullable();

                $table->timestampsTz();

                $table->unique([
                    'organisation_id',
                    'employee_id',
                    'period_start_date',
                    'period_end_date',
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_overtime_allowances');
    }
};
