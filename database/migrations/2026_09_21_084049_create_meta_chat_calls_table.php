<?php

use App\Enums\CRM\Livechat\MetaChatCallStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('meta_chat_calls', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedSmallInteger('meta_channel_id')->index();
            $table->foreign('meta_channel_id')->references('id')->on('meta_channels')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedInteger('meta_chat_session_id')->index();
            $table->foreign('meta_chat_session_id')->references('id')->on('meta_chat_sessions')->cascadeOnDelete();

            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops')->onUpdate('cascade');

            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();

            // The agent who answered. Null while a call is still ringing for everybody.
            $table->unsignedSmallInteger('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('wa_call_id')->unique();
            $table->string('direction')->index();
            $table->string('status')->index()->default(MetaChatCallStatusEnum::RINGING->value);
            $table->string('phone_number', 50)->nullable();

            $table->timestampTz('ringing_at')->nullable();
            $table->timestampTz('answered_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('termination_reason')->nullable();

            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['meta_chat_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_chat_calls');
    }
};
