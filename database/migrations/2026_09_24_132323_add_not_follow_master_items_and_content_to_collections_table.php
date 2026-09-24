<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->boolean('not_follow_master_items')->default(false);
            $table->boolean('not_follow_master_content')->default(false);
        });
    }


    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn([
                'not_follow_master_items',
                'not_follow_master_content'
            ]);
        });
    }
};
