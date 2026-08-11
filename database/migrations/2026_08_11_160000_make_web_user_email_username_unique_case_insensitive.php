<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS web_users_website_id_email_unique');
        DB::statement('CREATE UNIQUE INDEX web_users_website_id_email_unique ON web_users (website_id, lower(email))');

        DB::statement('DROP INDEX IF EXISTS web_users_website_id_username_unique');
        DB::statement('CREATE UNIQUE INDEX web_users_website_id_username_unique ON web_users (website_id, lower(username))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS web_users_website_id_email_unique');
        DB::statement('CREATE UNIQUE INDEX web_users_website_id_email_unique ON web_users (website_id, email)');

        DB::statement('DROP INDEX IF EXISTS web_users_website_id_username_unique');
        DB::statement('CREATE UNIQUE INDEX web_users_website_id_username_unique ON web_users (website_id, username)');
    }
};
