<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('appointment_types', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('meeting_mode')->index();
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(45);
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->unsignedSmallInteger('min_notice_hours')->default(24);
            $table->unsignedSmallInteger('booking_window_days')->default(60);
            $table->unsignedSmallInteger('capacity_per_slot')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->jsonb('weekly_hours');
            $table->jsonb('data');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('appointment_type_dates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('appointment_type_id')->index();
            $table->foreign('appointment_type_id')->references('id')->on('appointment_types')->cascadeOnDelete();
            $table->date('date');
            $table->jsonb('hours');
            $table->timestampsTz();
            $table->unique(['appointment_type_id', 'date']);
        });

        Schema::create('appointment_type_user', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('appointment_type_id')->index();
            $table->foreign('appointment_type_id')->references('id')->on('appointment_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestampsTz();
            $table->unique(['appointment_type_id', 'user_id']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('appointment_type_user');
        Schema::dropIfExists('appointment_type_dates');
        Schema::dropIfExists('appointment_types');
    }
};
