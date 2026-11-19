<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('stock_delivery_feedbacks', function (Blueprint $table) {
            $table->unsignedSmallInteger('rating')->nullable()->change();
            $table->timestampTz('sent_at')->nullable()->index();
        });

        DB::statement('UPDATE stock_delivery_feedbacks SET sent_at = created_at');
        $this->mergeRoundsOfTheSameDelivery();
        DB::statement('
            UPDATE stock_delivery_feedbacks f SET supplier_id = NULL
            WHERE supplier_id IS NOT NULL AND (
                SELECT count(DISTINCT sp.supplier_id) FROM stock_delivery_items i
                JOIN supplier_products sp ON sp.id = i.supplier_product_id
                WHERE i.stock_delivery_id = f.stock_delivery_id
            ) > 1
        ');
        DB::statement('DROP INDEX IF EXISTS stock_delivery_feedbacks_delivery_supplier_unique');
        DB::statement('CREATE UNIQUE INDEX stock_delivery_feedbacks_delivery_unique ON stock_delivery_feedbacks (stock_delivery_id)');

        Schema::create('stock_delivery_item_feedbacks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedInteger('stock_delivery_id')->index();
            $table->foreign('stock_delivery_id')->references('id')->on('stock_deliveries')->cascadeOnDelete();
            $table->unsignedInteger('stock_delivery_feedback_id')->index();
            $table->foreign('stock_delivery_feedback_id')->references('id')->on('stock_delivery_feedbacks')->cascadeOnDelete();
            $table->unsignedInteger('stock_delivery_item_id')->unique();
            $table->foreign('stock_delivery_item_id')->references('id')->on('stock_delivery_items')->cascadeOnDelete();
            $table->unsignedInteger('supplier_id')->nullable()->index();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->unsignedSmallInteger('agent_id')->nullable()->index();
            $table->foreign('agent_id')->references('id')->on('agents')->nullOnDelete();
            $table->unsignedSmallInteger('buyer_id')->nullable()->index();
            $table->foreign('buyer_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedSmallInteger('author_id')->nullable()->index();
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
            $table->string('issue');
            $table->text('comment')->nullable();
            $table->unsignedInteger('procurement_note_id')->nullable();
            $table->foreign('procurement_note_id')->references('id')->on('procurement_notes')->nullOnDelete();
            $table->timestampTz('notified_at')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Feedback was one per supplier: a delivery with several keeps its first as the round, the others' comments are
     * added to it under their supplier code and their photos move to it.
     */
    private function mergeRoundsOfTheSameDelivery(): void
    {
        $extras = DB::table('stock_delivery_feedbacks as f')
            ->leftJoin('suppliers', 'suppliers.id', 'f.supplier_id')
            ->whereExists(fn ($query) => $query->from('stock_delivery_feedbacks as earlier')
                ->whereColumn('earlier.stock_delivery_id', 'f.stock_delivery_id')
                ->whereColumn('earlier.id', '<', 'f.id'))
            ->orderBy('f.id')
            ->get(['f.id', 'f.stock_delivery_id', 'f.rating', 'f.comment', 'suppliers.code']);

        foreach ($extras as $extra) {
            $roundId = DB::table('stock_delivery_feedbacks')->where('stock_delivery_id', $extra->stock_delivery_id)->min('id');
            $round   = DB::table('stock_delivery_feedbacks')->where('id', $roundId)->first(['comment']);
            $text    = trim(($extra->code ?? '').' ('.$extra->rating.'★): '.($extra->comment ?? ''));

            DB::table('stock_delivery_feedbacks')->where('id', $roundId)->update([
                'comment'     => trim(($round->comment ?? '')."\n\n".$text),
                'supplier_id' => null,
            ]);
            DB::table('model_has_attachments')
                ->where('model_type', 'StockDelivery')
                ->where('model_id', $extra->stock_delivery_id)
                ->where('sub_scope', 'feedback:'.$extra->id)
                ->update(['sub_scope' => 'feedback:'.$roundId]);
            DB::table('stock_delivery_feedbacks')->where('id', $extra->id)->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_delivery_item_feedbacks');
        DB::statement('DROP INDEX IF EXISTS stock_delivery_feedbacks_delivery_unique');
        DB::statement('CREATE UNIQUE INDEX stock_delivery_feedbacks_delivery_supplier_unique ON stock_delivery_feedbacks (stock_delivery_id, coalesce(supplier_id, 0))');
        Schema::table('stock_delivery_feedbacks', function (Blueprint $table) {
            $table->dropColumn('sent_at');
        });
    }
};
