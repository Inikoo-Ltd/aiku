<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private array $statsColumns = [
        'website_stats'          => 'number_webpages_sub_type_',
        'group_web_stats'        => 'number_webpages_sub_type_',
        'organisation_web_stats' => 'number_webpages_sub_type_',
        'webpage_stats'          => 'number_child_webpages_sub_type_',
    ];

    public function up(): void
    {
        DB::table('webpages')->where('sub_type', 'david_aw_news')->update(['sub_type' => 'newsletters']);

        DB::statement("update web_blocks set layout = replace(layout::text, '\"david_aw_news\"', '\"newsletters\"')::jsonb where layout::text like '%\"david_aw_news\"%'");
        DB::statement("update webpages set published_layout = replace(published_layout::text, '\"david_aw_news\"', '\"newsletters\"')::jsonb where published_layout::text like '%\"david_aw_news\"%'");
        DB::statement("update snapshots set layout = replace(layout::text, '\"david_aw_news\"', '\"newsletters\"')::jsonb where layout::text like '%\"david_aw_news\"%'");
        DB::statement("update web_block_types set data = replace(replace(data::text, '\"davids_travel_blog\"', '\"newsletters\"'), 'Read The Blog: David''s AW News', 'Read The Blog: Newsletters')::jsonb where data::text like '%david%'");

        foreach ($this->statsColumns as $table => $prefix) {
            if (Schema::hasColumn($table, $prefix.'david_aw_news')) {
                Schema::table($table, function ($blueprint) use ($prefix) {
                    $blueprint->renameColumn($prefix.'david_aw_news', $prefix.'newsletters');
                });
            }
        }
    }

    public function down(): void
    {
        DB::table('webpages')->where('sub_type', 'newsletters')->update(['sub_type' => 'david_aw_news']);

        DB::statement("update web_blocks set layout = replace(layout::text, '\"newsletters\"', '\"david_aw_news\"')::jsonb where layout::text like '%\"newsletters\"%'");
        DB::statement("update webpages set published_layout = replace(published_layout::text, '\"newsletters\"', '\"david_aw_news\"')::jsonb where published_layout::text like '%\"newsletters\"%'");
        DB::statement("update snapshots set layout = replace(layout::text, '\"newsletters\"', '\"david_aw_news\"')::jsonb where layout::text like '%\"newsletters\"%'");

        foreach ($this->statsColumns as $table => $prefix) {
            if (Schema::hasColumn($table, $prefix.'newsletters')) {
                Schema::table($table, function ($blueprint) use ($prefix) {
                    $blueprint->renameColumn($prefix.'newsletters', $prefix.'david_aw_news');
                });
            }
        }
    }
};
