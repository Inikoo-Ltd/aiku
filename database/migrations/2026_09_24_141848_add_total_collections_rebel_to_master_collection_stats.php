<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_collection_stats', function (Blueprint $table) {
            $table->unsignedInteger('total_collections_rebel_items')->default(0);
            $table->unsignedInteger('total_collections_rebel_content')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('master_collection_stats', function (Blueprint $table) {
            $table->dropColumn([
                'total_collections_rebel_items',
                'total_collections_rebel_content'
            ]);
        });
    }
};
