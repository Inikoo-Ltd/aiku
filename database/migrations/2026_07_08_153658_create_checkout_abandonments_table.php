<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 08 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        if (! Schema::hasTable('checkout_abandonments')) {
            Schema::create('checkout_abandonments', function (Blueprint $table) {
                $table->id();

                $table = $this->groupOrgRelationship($table);

                $table->unsignedSmallInteger('shop_id')->index();
                $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');

                $table->unsignedInteger('order_id')->unique();
                $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');

                $table->unsignedInteger('customer_id')->index();
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');

                $table->timestampTz('checkout_visited_at')->index();

                $table->decimal('total_amount', 16)->default(0);

                $table->string('state')->default('abandoned')->index();
                $table->timestampTz('recovered_at')->nullable();

                $table->timestampsTz();

                $table->index(['shop_id', 'state', 'checkout_visited_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_abandonments');
    }
};
