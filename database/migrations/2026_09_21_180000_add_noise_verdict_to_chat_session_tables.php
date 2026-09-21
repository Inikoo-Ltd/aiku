<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private const array TABLES = ['chat_sessions', 'meta_chat_sessions'];

    /**
     * What a rule or the model made of a stranger's first message, kept apart from who put the
     * conversation aside: the verdict stays when a person undoes it, and that undoing is what
     * gets counted. Checked once and never again, so a person's decision is never overwritten.
     */
    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('noise_verdict')->nullable()->index();
                $table->string('noise_source')->nullable();
                $table->unsignedSmallInteger('noise_confidence')->nullable();
                $table->text('noise_note')->nullable();
                $table->dateTimeTz('noise_checked_at')->nullable();
                $table->dateTimeTz('noise_reversed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['noise_verdict', 'noise_source', 'noise_confidence', 'noise_note', 'noise_checked_at', 'noise_reversed_at']);
            });
        }
    }
};
