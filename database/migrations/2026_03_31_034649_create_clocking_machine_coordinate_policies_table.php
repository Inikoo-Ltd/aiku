<?php

use App\Enums\HumanResources\ClockingMachine\ClockingPolicyModeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('clocking_machine_coordinate_policies', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->string('scope_type')->index();
            $table->unsignedSmallInteger('scope_id')->index();
            $table->unsignedSmallInteger('clocking_machine_id')->nullable()->index();
            $table->foreign('clocking_machine_id')->references('id')->on('clocking_machines')->nullOnDelete();
            $table->string('mode')->default(ClockingPolicyModeEnum::ONSITE->value)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->dateTimeTz('start_at')->nullable()->index();
            $table->dateTimeTz('end_at')->nullable()->index();
            $table->text('reason')->nullable();
            $table->timestampsTz();
        });

        Schema::create('clocking_machine_coordinate_policy_rules', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('clocking_machine_coordinate_policy_id')->index();
            $table->foreign('clocking_machine_coordinate_policy_id')
                ->references('id')
                ->on('clocking_machine_coordinate_policies')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')->nullable()->index();
            $table->string('mode_override')->default(ClockingPolicyModeEnum::ONSITE->value)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('clocking_machine_coordinate_policy_rules');
        Schema::dropIfExists('clocking_machine_coordinate_policies');
    }
};
