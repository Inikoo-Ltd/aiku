<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        $this->setRescueByPurchaseOrder(true);
    }

    public function down(): void
    {
        $this->setRescueByPurchaseOrder(false);
    }

    private function setRescueByPurchaseOrder(bool $value): void
    {
        DB::table('organisations')->where('code', 'ES')->where('is_manufacturing_hub', true)->get(['id', 'settings'])->each(function (object $organisation) use ($value) {
            $settings = json_decode($organisation->settings ?? '{}', true) ?: [];
            data_set($settings, 'procurement.rescue_by_purchase_order', $value);
            DB::table('organisations')->where('id', $organisation->id)->update(['settings' => json_encode($settings)]);
        });
    }
};
