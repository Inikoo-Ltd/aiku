<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('luna_clients', function (Blueprint $table) {
            $table->id();
            $table->timestampsTz();
        });
    }

    public function down()
    {
        Schema::dropIfExists('luna_clients');
    }
};
