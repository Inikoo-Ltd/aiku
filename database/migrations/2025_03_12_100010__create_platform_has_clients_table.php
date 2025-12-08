<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use \App\Stubs\Migrations\HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('platform_has_clients', function (Blueprint $table) {
            $table->increments('id');

            /** @var Blueprint $table */
            $table = $this->groupOrgRelationship($table);

            $table->unsignedBigInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');

            $table->morphs('userable');
            $table->unsignedBigInteger('customer_client_id');
            $table->foreign('customer_client_id')->references('id')->on('customer_clients');
            $table->unsignedBigInteger('platform_customer_client_id')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_has_clients');
    }
};
