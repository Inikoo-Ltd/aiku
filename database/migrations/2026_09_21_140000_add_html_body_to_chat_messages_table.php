<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * An email is mostly its layout: the pictures, the headings, the table of products. Kept
     * beside the plain text rather than instead of it, because the text is what search, previews
     * and translation read, and what is shown when the markup cannot be trusted.
     *
     * What is stored here has already been through the purifier, so nothing unsafe is written.
     */
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->text('html_body')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('html_body');
        });
    }
};
