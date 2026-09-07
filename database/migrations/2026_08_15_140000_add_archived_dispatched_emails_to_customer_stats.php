<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * No customer counter is recounted from dispatched emails, so this is not a stats baseline: it
     * records how many of a customer's emails were archived and how recent they are, so the email
     * listing can offer them without querying the archive server on every page load.
     */
    public function up(): void
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->jsonb('archived_dispatched_emails')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->dropColumn('archived_dispatched_emails');
        });
    }
};
