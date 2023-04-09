<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::create('media_agent', function (Blueprint $table) {
            $table->unsignedInteger('agent_id')->index();
            $table->string('type')->index();
            $table->foreign('agent_id')->references('id')->on('agents');
            $table->unsignedBigInteger('media_id')->index();
            $table->foreign('media_id')->references('id')->on('media');
            $table->unique(['agent_id', 'media_id']);
            $table->string('owner_type')->index();
            $table->unsignedInteger('owner_id');
            $table->boolean('public')->default(false)->index();

            $table->timestampsTz();
            $table->index(['owner_type', 'owner_id']);
        });
    }


    public function down()
    {
        Schema::dropIfExists('media_agent');
    }
};
