<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Sept 2026 15:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::table('languages')->where('code', 'hi')->update(['status' => '1', 'native_name' => 'हिन्दी']);
        DB::table('languages')->where('code', 'ne')->update(['status' => '1', 'native_name' => 'नेपाली']);
    }

    public function down(): void
    {
        DB::table('languages')->where('code', 'hi')->update(['status' => '0', 'native_name' => 'Hindi']);
        DB::table('languages')->where('code', 'ne')->update(['status' => '0', 'native_name' => 'Nepali']);
    }
};
