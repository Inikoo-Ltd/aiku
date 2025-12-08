<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Apr 2024 09:13:36 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_agreement_snapshots', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('rental_agreement_id')->index();
            $table->foreign('rental_agreement_id')->references('id')->on('rental_agreements');
            $table->jsonb('data');
            $table->boolean('is_first_snapshot')->default(false);
            $table->unsignedSmallInteger('clauses_added')->default(0);
            $table->unsignedSmallInteger('clauses_removed')->default(0);
            $table->unsignedSmallInteger('clauses_updated')->default(0);
            $table->timestampsTz();
        });

        Schema::table('rental_agreement_clauses', function (Blueprint $table) {
            $table->foreign('rental_agreement_snapshot_id')->references('id')->on('rental_agreement_snapshots');
        });
        Schema::table('rental_agreements', function (Blueprint $table) {
            $table->foreign('current_snapshot_id')->references('id')->on('rental_agreement_snapshots');
        });

    }

    public function down(): void
    {
        Schema::table('rental_agreements', function (Blueprint $table) {
            $table->dropForeign('current_snapshot_id_foreign');
        });

        Schema::table('rental_agreement_clauses', function (Blueprint $table) {
            $table->dropForeign('rental_agreement_snapshot_id_foreign');
        });

        Schema::dropIfExists('rental_agreement_snapshots');
    }
};
