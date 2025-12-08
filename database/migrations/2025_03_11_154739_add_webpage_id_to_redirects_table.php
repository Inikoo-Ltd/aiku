<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Mar 2025 23:47:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->renameColumn('redirection', 'url');
            $table->string('path')->index()->comment('path (no domain) that will be redirected');

            $table->unsignedInteger('webpage_id');
            $table->foreign('webpage_id')->references('id')->on('webpages')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::table('redirects', function (Blueprint $table) {
            $table->index('url');
        });

        DB::statement("COMMENT ON COLUMN redirects.url IS 'Full URL including https scheme from url that will be redirected'");

    }

    public function down(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->dropForeign(['webpage_id']);
            $table->dropColumn('webpage_id');
            $table->dropColumn('path');
            $table->dropIndex('redirects_url_index');
            $table->renameColumn('url', 'redirection');
        });
    }
};
