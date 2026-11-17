<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Accounting\BankConnection\SyncBankConnection;
use App\Models\Accounting\BankConnection;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        BankConnection::all()->each(fn (BankConnection $bankConnection) => SyncBankConnection::keepExchangeRates($bankConnection));
    }

    public function down(): void
    {
    }
};
