<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_collections', function (Blueprint $table) {
            $table->text('description_title')->nullable();
            $table->text('description_extra')->nullable();
            $table->jsonb('name_i8n')->nullable();
            $table->jsonb('description_i8n')->nullable();
            $table->jsonb('description_title_i8n')->nullable();
            $table->jsonb('description_extra_i8n')->nullable();
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->jsonb('name_i8n')->nullable();
            $table->jsonb('description_i8n')->nullable();
            $table->jsonb('description_title_i8n')->nullable();
            $table->jsonb('description_extra_i8n')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('master_collections', function (Blueprint $table) {
            $table->dropColumn([
                'description_title',
                'description_extra',
                'name_i8n',
                'description_i8n',
                'description_title_i8n',
                'description_extra_i8n',
            ]);
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn([
                'name_i8n',
                'description_i8n',
                'description_title_i8n',
                'description_extra_i8n',
            ]);
        });
    }
};
