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
        Schema::create('customer_interests', function (Blueprint $table) {
            $table->increments('id');

            $table = $this->groupOrgRelationship($table);

            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');

            $table->unsignedInteger('customer_id')->unique();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');

            $table->unsignedInteger('registration_product_id')->nullable()->index();
            $table->foreign('registration_product_id')->references('id')->on('products')->onDelete('set null');

            $table->jsonb('top_products')->default('[]');
            $table->timestampTz('top_products_computed_at')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_interests');
    }
};
