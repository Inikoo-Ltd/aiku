<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashboard_time_series_aggregates', function (Blueprint $table) {
            $table->id();
            $table->string('cache_hash')->unique();
            $table->string('table_name');
            $table->string('foreign_key');
            $table->string('time_series_ids_hash');
            $table->string('metrics_hash');
            $table->string('additional_where_hash');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->jsonb('payload');
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(['table_name', 'foreign_key'], 'dashboard_ts_aggregates_scope_index');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('dashboard_time_series_aggregates');
    }
};
