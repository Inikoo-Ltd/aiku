<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 01:18:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('translation_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('language_id');
            $table->foreign('language_id')->references('id')->on('languages');
            $table->string('source_term');
            $table->string('target_term');
            $table->unsignedSmallInteger('support')->comment('Webmaster-written examples that use target_term for source_term');
            $table->unsignedSmallInteger('examples')->comment('Webmaster-written examples shown when the term was mined');
            $table->timestampsTz();
            $table->unique(['language_id', 'source_term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_terms');
    }
};
