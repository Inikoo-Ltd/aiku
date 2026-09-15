<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('traffic_source_costs', function (Blueprint $table) {
            $table->string('fetched_via', 16)->nullable()->after('source_currency_id');
        });

        DB::table('traffic_source_costs')
            ->whereIn('traffic_source_id', function ($query) {
                $query->select('id')
                    ->from('traffic_sources')
                    ->whereIn('type', ['meta-ads', 'instagram-ads']);
            })
            ->update(['fetched_via' => 'api']);

        DB::table('traffic_source_costs')
            ->whereNull('fetched_via')
            ->whereIn('traffic_source_id', function ($query) {
                $query->select('id')
                    ->from('traffic_sources')
                    ->whereIn('type', ['google-ads', 'bing-ads']);
            })
            ->update(['fetched_via' => 'webhook']);
    }

    public function down(): void
    {
        Schema::table('traffic_source_costs', function (Blueprint $table) {
            $table->dropColumn('fetched_via');
        });
    }
};
