<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_portfolio_customer_sales_channel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_sales_channel_id')->constrained('customer_sales_channels')->cascadeOnDelete();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('download_url')->nullable();
            $table->float('file_size')->nullable();
            $table->string('size_unit')->nullable();
            $table->timestampTz('file_start_create_at')->nullable();
            $table->timestampTz('file_completed_create_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_portfolio_customer_sales_channel');
    }
};
