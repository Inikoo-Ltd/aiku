<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        // Need to use DB Statement for GIN Indexing. Cannot use Schema. Needed for UpdateWebBlockToWebsiteAndChild, otherwise it would be hell to query it
        DB::statement("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS webpages_published_layout_gin
            ON webpages
            USING GIN (published_layout)
        ");

    }


    public function down(): void
    {
        DB::statement("DROP INDEX CONCURRENTLY IF EXISTS webpages_published_layout_gin");
    }
};
