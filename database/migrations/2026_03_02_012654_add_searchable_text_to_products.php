<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table
                ->text('searchable_text')
                ->nullable()
                ->comment('Normalized search cache for ILIKE queries');
        });

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement(
            'CREATE INDEX products_searchable_text_trgm_idx
             ON products
             USING gin (searchable_text gin_trgm_ops)'
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('searchable_text');
        });

        DB::statement('DROP INDEX IF EXISTS products_searchable_text_trgm_idx');
    }
};
