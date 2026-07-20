<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement("CREATE INDEX IF NOT EXISTS chat_knowledge_chunks_content_fts_idx ON chat_knowledge_chunks USING gin (to_tsvector('simple', content))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS chat_knowledge_chunks_content_fts_idx');
    }
};
