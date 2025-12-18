<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('request_response_logs', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('method', 10);
            $table->string('x_inertia')->nullable();
            $table->json('headers')->nullable();
            $table->json('request_body')->nullable();
            $table->longText('response_body')->nullable();
            $table->integer('status_code');
            $table->string('content_type')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['status_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_response_logs');
    }
};
