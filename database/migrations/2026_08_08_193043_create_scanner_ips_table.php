<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('scanner_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->jsonb('campaign_refs')->default('[]');
            $table->dateTimeTz('last_burst_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanner_ips');
    }
};
