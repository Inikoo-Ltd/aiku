<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('artefacts_manufacture_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('artefact_id')->index();
            $table->foreign('artefact_id')->references('id')->on('artefacts');
            $table->unsignedInteger('manufacture_task_id')->index();
            $table->foreign('manufacture_task_id')->references('id')->on('manufacture_tasks');
            $table->timestampsTz();
        });
    }

    public function down()
    {
        Schema::dropIfExists('artefact_manufacture_tasks');
    }
};
