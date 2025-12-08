<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->string('canonical_url')->unique()->nullable();
            $table->boolean('is_use_canonical_url')->default(false);
            $table->jsonb('seo_data')->default('{}');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->dropColumn(['canonical_url', 'is_use_canonical_url', 'seo_data']);
        });
    }
};
