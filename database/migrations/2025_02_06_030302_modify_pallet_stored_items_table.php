<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('pallet_stored_items', function (Blueprint $table) {
            $table->boolean('in_process')->default(true)->index();
        });
    }


    public function down(): void
    {
        Schema::table('pallet_stored_items', function (Blueprint $table) {
            $table->dropColumn('in_process');
        });
    }
};
