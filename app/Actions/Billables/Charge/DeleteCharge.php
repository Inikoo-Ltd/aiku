<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Aug 2026 12:10:00 Central European Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Charge;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCharges;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCharges;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateCharges;
use App\Models\Billables\Charge;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class DeleteCharge extends OrgAction
{
    public string $commandSignature = 'delete:charge {id}';

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Charge $charge): Charge
    {
        if ($this->isUsedOnOrders($charge)) {
            throw ValidationException::withMessages(
                [
                    'charge' => __('This charge has already been used on orders, deleting it would change their history. Discontinue it instead.')
                ]
            );
        }

        $shop = $charge->shop;

        DB::transaction(function () use ($charge) {
            $charge->asset?->delete();
            $charge->delete();
        });

        ShopHydrateCharges::dispatch($shop)->delay($this->hydratorsDelay);
        OrganisationHydrateCharges::dispatch($shop->organisation)->delay($this->hydratorsDelay);
        GroupHydrateCharges::dispatch($shop->group)->delay($this->hydratorsDelay);

        return $charge;
    }

    public function isUsedOnOrders(Charge $charge): bool
    {
        return DB::table('transactions')
            ->where('model_type', 'Charge')
            ->where('model_id', $charge->id)
            ->exists();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("products.{$this->shop->id}.edit");
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(Charge $charge, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($charge->shop, $request);
        $this->handle($charge);

        return back();
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(Charge $charge, int $hydratorsDelay = 0, bool $audit = true): Charge
    {
        if (!$audit) {
            Charge::disableAuditing();
        }

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($charge->shop, []);

        return $this->handle($charge);
    }

    public function asCommand(Command $command): int
    {
        try {
            $charge = Charge::findOrFail($command->argument('id'));
        } catch (Exception) {
            $command->error('Charge not found');

            return 1;
        }

        try {
            $this->action($charge);
        } catch (ValidationException $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $command->info('Charge '.$charge->code.' deleted');

        return 0;
    }
}
