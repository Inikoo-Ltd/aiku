<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('websites', function ($table) {
            $table->unsignedBigInteger('login_page_id')->nullable();
            $table->foreign('login_page_id')->references('id')->on('webpages');
            $table->unsignedBigInteger('register_page_id')->nullable();
            $table->foreign('register_page_id')->references('id')->on('webpages');
            $table->unsignedBigInteger('forgot_password_page_id')->nullable();
            $table->foreign('forgot_password_page_id')->references('id')->on('webpages');
        });
    }


    public function down(): void
    {
        Schema::table('websites', function ($table) {
            $table->dropColumn([
                'login_page_id',
                'register_page_id',
                'forgot_password_page_id'
            ]);
        });
    }
};
