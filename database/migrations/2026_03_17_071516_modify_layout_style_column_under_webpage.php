<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->dropColumn(['layout_style']);

            $table->string('layout_style')->default('main_page');
        });
    }


    public function down(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->dropColumn(['layout_style']);

            $table->unsignedInteger('layout_style')->default(1);
        });
    }
};
