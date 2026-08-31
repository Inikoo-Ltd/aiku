<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_recipients', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('whatsapp_campaign_id')->index();
            $table->foreign('whatsapp_campaign_id')->references('id')->on('whatsapp_campaigns')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedBigInteger('meta_chat_message_id')->nullable()->index();
            $table->foreign('meta_chat_message_id')->references('id')->on('meta_chat_messages')->onUpdate('cascade')->nullOnDelete();

            $table->unsignedBigInteger('whatsapp_delivery_channel_id')->nullable()->index();
            $table->foreign('whatsapp_delivery_channel_id')->references('id')->on('whatsapp_delivery_channels')->onUpdate('cascade')->nullOnDelete();

            $table->string('recipient_type')->comment('Customer, MetaChatSession');
            $table->unsignedInteger('recipient_id');
            $table->string('recipient_name')->nullable();
            $table->string('phone')->index()->comment('normalised, digits only');

            $table->timestampsTz();

            $table->index(['recipient_type', 'recipient_id', 'whatsapp_campaign_id'], 'whatsapp_recipients_recipient_idx');
            $table->unique(['whatsapp_campaign_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_recipients');
    }
};
