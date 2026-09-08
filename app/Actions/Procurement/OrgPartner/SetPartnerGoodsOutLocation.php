<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Models\Inventory\Location;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class SetPartnerGoodsOutLocation
{
    use AsAction;

    /**
     * Where a partner's pre-picked stock gathers before it is dispatched. Setting it is what
     * gives the seller's dispatching hub its Pre-pick tab, so it is deliberately not editable
     * from the UI.
     */
    public function handle(OrgPartner $orgPartner, ?Location $location): OrgPartner
    {
        if ($location) {
            if ($location->organisation_id !== $orgPartner->organisation_id) {
                throw ValidationException::withMessages(['location' => __('Location belongs to another organisation')]);
            }
            if (!$location->is_goods_out) {
                throw ValidationException::withMessages(['location' => __('Location is not a goods out gathering location')]);
            }
        }

        $orgPartner->update(['goods_out_location_id' => $location?->id]);

        return $orgPartner->refresh();
    }

    public string $commandSignature = 'org:set_partner_goods_out_location {organisation : Seller organisation slug} {partner : Buying organisation slug} {location? : Location code, omit to clear}';

    public function asCommand(Command $command): int
    {
        $seller = Organisation::where('slug', $command->argument('organisation'))->first();
        $buyer  = Organisation::where('slug', $command->argument('partner'))->first();

        if (!$seller || !$buyer) {
            $command->error('Organisation not found');

            return 1;
        }

        $orgPartner = OrgPartner::where('organisation_id', $seller->id)->where('partner_id', $buyer->id)->first();
        if (!$orgPartner) {
            $command->error("{$buyer->code} is not a partner of {$seller->code}");

            return 1;
        }

        $location = null;
        if ($code = $command->argument('location')) {
            $location = Location::whereRelation('warehouse', 'organisation_id', $seller->id)
                ->where('code', $code)
                ->first();

            if (!$location) {
                $command->error("Location $code not found in {$seller->code}");

                return 1;
            }
        }

        try {
            $this->handle($orgPartner, $location);
        } catch (ValidationException $exception) {
            $command->error(collect($exception->errors())->flatten()->implode(', '));

            return 1;
        }

        $command->info($location
            ? "{$buyer->code} stock gathers in {$location->code}"
            : "{$buyer->code} has no goods out location any more");

        return 0;
    }
}
