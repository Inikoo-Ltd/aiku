<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Jun 2025 17:32:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->renameColumn('url', 'from_url');
            $table->renameColumn('path', 'from_path');
            $table->renameColumn('webpage_id', 'from_webpage_id');

        });

        Schema::table('redirects', function (Blueprint $table) {
            $table->string('from_path')->comment('path to redirect from')->change();
            $table->unsignedInteger('to_webpage_id')->index()->comment('webpage where it going to be redirected to');
            $table->index('from_webpage_id');
        });

    }


    public function down(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->dropColumn('to_webpage_id');
            $table->renameColumn('from_path', 'path');
            $table->renameColumn('from_webpage_id', 'webpage_id');
        });

        Schema::table('redirects', function (Blueprint $table) {
            $table->string('path')->comment('path to redirect from');
            $table->unsignedInteger('webpage_id')->index()->comment('webpage where it going to be redirected to');

        });
    }
};
