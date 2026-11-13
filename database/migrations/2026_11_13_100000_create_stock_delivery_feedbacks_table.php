<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('stock_delivery_feedbacks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedInteger('stock_delivery_id')->index();
            $table->foreign('stock_delivery_id')->references('id')->on('stock_deliveries')->cascadeOnDelete();
            $table->unsignedInteger('supplier_id')->nullable()->index();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->unsignedSmallInteger('agent_id')->nullable()->index();
            $table->foreign('agent_id')->references('id')->on('agents')->nullOnDelete();
            $table->unsignedSmallInteger('org_partner_id')->nullable()->index();
            $table->foreign('org_partner_id')->references('id')->on('org_partners')->nullOnDelete();
            $table->unsignedSmallInteger('buyer_id')->nullable()->index();
            $table->foreign('buyer_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedSmallInteger('author_id')->nullable()->index();
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedSmallInteger('rating');
            $table->jsonb('topics')->default('[]');
            $table->text('comment')->nullable();
            $table->unsignedInteger('procurement_note_id')->nullable();
            $table->foreign('procurement_note_id')->references('id')->on('procurement_notes')->nullOnDelete();
            $table->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX stock_delivery_feedbacks_delivery_supplier_unique ON stock_delivery_feedbacks (stock_delivery_id, coalesce(supplier_id, 0))');
        DB::statement('ALTER TABLE stock_delivery_feedbacks ADD CONSTRAINT stock_delivery_feedbacks_rating_check CHECK (rating BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_delivery_feedbacks');
    }
};
