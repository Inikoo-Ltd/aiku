<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS web_users_website_id_email_active_unique');
        DB::statement('CREATE UNIQUE INDEX CONCURRENTLY web_users_website_id_email_active_unique ON web_users (website_id, lower(email)) WHERE deleted_at IS NULL');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS web_users_website_id_email_unique');

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS web_users_website_id_username_active_unique');
        DB::statement('CREATE UNIQUE INDEX CONCURRENTLY web_users_website_id_username_active_unique ON web_users (website_id, lower(username)) WHERE deleted_at IS NULL');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS web_users_website_id_username_unique');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS web_users_website_id_email_unique');
        DB::statement('CREATE UNIQUE INDEX CONCURRENTLY web_users_website_id_email_unique ON web_users (website_id, lower(email))');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS web_users_website_id_email_active_unique');

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS web_users_website_id_username_unique');
        DB::statement('CREATE UNIQUE INDEX CONCURRENTLY web_users_website_id_username_unique ON web_users (website_id, lower(username))');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS web_users_website_id_username_active_unique');
    }
};
