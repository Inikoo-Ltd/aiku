<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Mar 2026 15:43:31 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('email_ongoing_run_has_dispatched_emails', function (Blueprint $table) {
            $table->unsignedInteger('email_ongoing_run_id');
            $table->foreign('email_ongoing_run_id', 'eor_hde_run_id_foreign')->references('id')->on('email_ongoing_runs')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedBigInteger('dispatched_email_id');
            $table->foreign('dispatched_email_id', 'eor_hde_email_id_foreign')->references('id')->on('dispatched_emails')->onUpdate('cascade')->onDelete('cascade');
            $table->unique(['email_ongoing_run_id', 'dispatched_email_id'], 'eor_hde_unique');
        });

        Schema::create('email_bulk_run_has_dispatched_emails', function (Blueprint $table) {
            $table->unsignedInteger('email_bulk_run_id');
            $table->foreign('email_bulk_run_id', 'ebr_hde_run_id_foreign')->references('id')->on('email_bulk_runs')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedBigInteger('dispatched_email_id');
            $table->foreign('dispatched_email_id', 'ebr_hde_email_id_foreign')->references('id')->on('dispatched_emails')->onUpdate('cascade')->onDelete('cascade');
            $table->unique(['email_bulk_run_id', 'dispatched_email_id'], 'ebr_hde_unique');
        });

        Schema::create('mailshot_has_dispatched_emails', function (Blueprint $table) {
            $table->unsignedInteger('mailshot_id');
            $table->foreign('mailshot_id', 'mshot_hde_mailshot_id_foreign')->references('id')->on('mailshots')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedBigInteger('dispatched_email_id');
            $table->foreign('dispatched_email_id', 'mshot_hde_email_id_foreign')->references('id')->on('dispatched_emails')->onUpdate('cascade')->onDelete('cascade');
            $table->unique(['mailshot_id', 'dispatched_email_id'], 'mshot_hde_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailshot_has_dispatched_emails');
        Schema::dropIfExists('email_bulk_run_has_dispatched_emails');
        Schema::dropIfExists('email_ongoing_run_has_dispatched_emails');
    }
};
