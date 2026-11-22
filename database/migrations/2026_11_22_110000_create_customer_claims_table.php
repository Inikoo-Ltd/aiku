<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('customer_claims', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->unsignedSmallInteger('group_id')->index();
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->unsignedSmallInteger('shop_id')->index();
            $table->unsignedInteger('customer_id')->index();
            $table->unsignedInteger('order_id')->nullable()->index();
            $table->unsignedInteger('refund_id')->nullable()->index();
            $table->unsignedBigInteger('shopping_assistant_conversation_id')->nullable()->index();
            $table->string('reference')->unique();
            $table->string('kind')->default('items')->index();
            $table->string('source');
            $table->string('state')->index();
            $table->string('resolution');
            $table->decimal('amount', 16, 2)->default(0);
            $table->decimal('value', 16, 2)->default(0);
            $table->string('currency_code', 3)->nullable();
            $table->text('comment')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->text('rejection_note')->nullable();
            $table->jsonb('result')->nullable();
            $table->unsignedInteger('reviewed_by_user_id')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('customer_claim_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_claim_id')->index();
            $table->foreign('customer_claim_id')->references('id')->on('customer_claims')->cascadeOnDelete();
            $table->unsignedInteger('transaction_id')->index();
            $table->string('code');
            $table->string('name')->nullable();
            $table->decimal('quantity', 16, 3);
            $table->string('reason');
            $table->jsonb('delivery_note_items');
        });

        Schema::table('shopping_assistant_messages', function (Blueprint $table) {
            $table->unsignedInteger('claim_order_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shopping_assistant_messages', function (Blueprint $table) {
            $table->dropColumn('claim_order_id');
        });
        Schema::dropIfExists('customer_claim_lines');
        Schema::dropIfExists('customer_claims');
    }
};
