<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('warehouse_id')->nullable()->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->unsignedSmallInteger('user_id')->index()->nullable()->comment('The user who created/inputted the feedback');
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('origin_source');
            $table->string('origin_type')->index();
            $table->unsignedInteger('origin_id');
            $table->boolean('blame_supplier')->default(false);
            $table->boolean('blame_picker')->default(false);
            $table->boolean('blame_packer')->default(false);
            $table->boolean('blame_warehouse')->default(false);
            $table->boolean('blame_courier')->default(false);
            $table->boolean('blame_marketing')->default(false);
            $table->boolean('blame_customer')->default(false);
            $table->boolean('blame_other')->default(false);
            $table->text('message')->nullable();
            $table->jsonb('data');
            $table->string('source_id')->nullable()->unique();
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->index(['origin_type', 'origin_id']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
