<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('webpages', function ($table) {
            $table->boolean('index_page')->default('true');
            $table->boolean('follow_link')->default('true');
        });
    }


    public function down(): void
    {
        Schema::table('webpages', function ($table) {
            $table->dropColumn([
                'index_page',
                'follow_link',
            ]);
        });
    }
};
