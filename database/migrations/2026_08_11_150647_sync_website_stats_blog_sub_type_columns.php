<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private array $tables = [
        'website_stats'          => 'number_webpages_sub_type_',
        'webpage_stats'          => 'number_child_webpages_sub_type_',
        'organisation_web_stats' => 'number_webpages_sub_type_',
        'group_web_stats'        => 'number_webpages_sub_type_',
    ];

    private array $newSubTypes = ['david_aw_news', 'product_guides', 'business_tips', 'insight'];

    private array $staleSubTypes = ['davids_travel_blog', 'tips'];

    public function up(): void
    {
        foreach ($this->tables as $tableName => $prefix) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $prefix) {
                foreach ($this->newSubTypes as $subType) {
                    if (!Schema::hasColumn($tableName, $prefix.$subType)) {
                        $table->unsignedInteger($prefix.$subType)->default(0);
                    }
                }
            });
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $prefix) {
                foreach ($this->staleSubTypes as $subType) {
                    if (Schema::hasColumn($tableName, $prefix.$subType)) {
                        $table->dropColumn($prefix.$subType);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName => $prefix) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $prefix) {
                foreach ($this->newSubTypes as $subType) {
                    if (Schema::hasColumn($tableName, $prefix.$subType)) {
                        $table->dropColumn($prefix.$subType);
                    }
                }
            });
        }
    }
};
