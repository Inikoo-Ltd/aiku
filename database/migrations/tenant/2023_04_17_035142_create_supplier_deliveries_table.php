<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::create('supplier_deliveries', function (Blueprint $table) {
            $table->increments('id');
            $table->string('slug')->unique();
            $table->unsignedInteger('provider_id')->index();
            $table->string('provider_type');
            $table->string('number');
            $table->jsonb('data');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unsignedInteger('source_id')->nullable()->unique();
            $table->index(['provider_id', 'provider_type']);

        });
    }


    public function down()
    {
        Schema::dropIfExists('supplier_deliveries');
    }
};
