<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('appointment_type_id')->index();
            $table->foreign('appointment_type_id')->references('id')->on('appointment_types');
            $table->string('visitor_type')->nullable();
            $table->unsignedInteger('visitor_id')->nullable();
            $table->index(['visitor_type', 'visitor_id']);
            $table->unsignedSmallInteger('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedSmallInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('state')->index();
            $table->string('source')->index();
            $table->dateTimeTz('starts_at')->index();
            $table->dateTimeTz('ends_at');
            $table->string('contact_name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->unsignedSmallInteger('number_visitors')->default(1);
            $table->text('notes')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();
            $table->jsonb('data');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['shop_id', 'state', 'starts_at']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
