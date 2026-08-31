<?php

use App\Enums\Comms\WhatsappDeliveryChannel\WhatsappDeliveryChannelStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_delivery_channels', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('whatsapp_campaign_id')->index();
            $table->foreign('whatsapp_campaign_id')->references('id')->on('whatsapp_campaigns')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedInteger('number_messages');
            $table->string('state')->index()->default(WhatsappDeliveryChannelStateEnum::READY->value);

            $table->dateTimeTz('start_sending_at')->nullable();
            $table->dateTimeTz('sent_at')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_delivery_channels');
    }
};
