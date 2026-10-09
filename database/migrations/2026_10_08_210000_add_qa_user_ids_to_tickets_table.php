<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->jsonb('qa_user_ids')->default('[]');
        });

        DB::statement("UPDATE tickets SET qa_user_ids = jsonb_build_array(qa_user_id), qa_user_id = NULL WHERE qa_status = 'requested' AND qa_user_id IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE tickets SET qa_user_id = (qa_user_ids->>0)::int WHERE qa_status = 'requested' AND qa_user_id IS NULL AND jsonb_array_length(qa_user_ids) > 0");

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('qa_user_ids');
        });
    }
};
