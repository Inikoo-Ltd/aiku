<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        $cutOver = now()->addDay()->toDateString();

        DB::table('agents')->orderBy('id')->get(['id', 'settings'])->each(function (object $agent) use ($cutOver) {
            $settings        = json_decode($agent->settings ?? '{}', true) ?: [];
            $hadOwnWarehouse = (bool) Arr::pull($settings, 'receives_goods_in_own_warehouse', false);

            if (!Arr::get($settings, 'po_journey.cho_mode')) {
                Arr::set($settings, 'po_journey.cho_mode', $hadOwnWarehouse ? 'next_container' : 'forwarder');
            }

            if (!$hadOwnWarehouse && !Arr::get($settings, 'goods_receipts_required_from')) {
                $settings['goods_receipts_required_from'] = $cutOver;
            }

            DB::table('agents')->where('id', $agent->id)->update(['settings' => json_encode($settings)]);
        });
    }

    public function down(): void
    {
    }
};
