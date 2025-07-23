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
        Schema::create('customer_traffic_ads', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->unsignedInteger('traffic_source_id')->index();
            $table->foreign('traffic_source_id')->references('id')->on('traffic_sources');


            $table->string('tracking_id');
            $table->string('full_url')->nullable();

            $table->dateTimeTz('captured_at')->index();
            $table->dateTimeTz('expires_at')->nullable()->index(); // When the tracking ID expires
            $table->integer('attribution_window_days'); // 90 for Google, 28 for Meta, etc.
            $table->boolean('is_active')->default(true)->index();

            $table->jsonb('data')->nullable(); // Additional platform-specific data
            $table->timestamps();

            $table->unique(['customer_id', 'traffic_source_id', 'tracking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_traffic_ads');
    }
};
