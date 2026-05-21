<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('wati_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('wati_id');
            $table->string('wa_id');
            $table->string('phone');
            $table->string('name')->nullable();
            $table->string('contact_status')->default('valid');
            $table->string('source')->nullable();

            $table->boolean('opted_in')->default(false);
            $table->boolean('allow_broadcast')->default(true);
            $table->boolean('allow_sms')->default(true);

            $table->json('teams')->nullable();
            $table->json('segments')->nullable();
            $table->json('custom_params')->nullable();

            $table->timestamp('wati_created_at')->nullable();
            $table->timestamp('wati_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();

            $table->timestampsTz();

            $table->unique(['shop_id', 'wati_id']);
            $table->index(['shop_id', 'customer_id']);
            $table->index(['shop_id', 'wa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wati_contacts');
    }
};
