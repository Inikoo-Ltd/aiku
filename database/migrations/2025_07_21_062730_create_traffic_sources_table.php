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
        Schema::create('traffic_sources', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->string('name');
            $table->jsonb('settings');
            $table->string('slug')->unique()->collation('und_ns');
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('traffic_sources');
    }
};
