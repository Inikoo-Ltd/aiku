<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sat, 17 Sept 2022 02:10:19 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions;

use App\Actions\Traits\WithTab;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Inventory\Warehouse;
use App\Models\Production\Production;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithTab;

    protected Group $group;
    protected Organisation $organisation;
    protected Shop $shop;
    protected Fulfilment $fulfilment;
    protected Warehouse $warehouse;
    protected Production $production;

    protected bool $asAction = false;
    protected bool $canEdit = false;
    protected bool $canDelete = false;
    protected bool $isSupervisor = false;
    public int $hydratorsDelay = 0;
    protected bool $strict = true;
    protected bool $han = false;
    protected bool $maya = false;

    protected array $validatedData;
    protected DateIntervalEnum $dateInterval = DateIntervalEnum::YEAR_TO_DAY;


    public function initialisation(Organisation $organisation, ActionRequest|array $request): static
    {
        $this->organisation = $organisation;
        $this->group        = $organisation->group;
        if (is_array($request)) {
            $this->setRawAttributes($request);
        } else {
            $this->fillFromRequest($request);
        }
        $this->validatedData = $this->validateAttributes();

        return $this;
    }

    public function initialisationFromShop(Shop $shop, ActionRequest|array $request): static
    {
        $this->shop         = $shop;
        $this->organisation = $shop->organisation;
        $this->group        = $this->organisation->group;
        if (is_array($request)) {
            $this->setRawAttributes($request);
        } else {
            $this->fillFromRequest($request);
        }
        $this->validatedData = $this->validateAttributes();

        return $this;
    }

    public function initialisationFromFulfilment(Fulfilment $fulfilment, ActionRequest|array $request): static
    {
        $this->fulfilment   = $fulfilment;
        $this->shop         = $fulfilment->shop;
        $this->organisation = $fulfilment->organisation;
        $this->group        = $this->organisation->group;
        if (is_array($request)) {
            $this->setRawAttributes($request);
        } else {
            $this->fillFromRequest($request);
        }
        $this->validatedData = $this->validateAttributes();

        return $this;
    }

    public function initialisationFromWarehouse(Warehouse $warehouse, ActionRequest|array $request): static
    {
        $this->warehouse    = $warehouse;
        $this->organisation = $warehouse->organisation;
        $this->group        = $this->organisation->group;
        if (is_array($request)) {
            $this->setRawAttributes($request);
        } else {
            $this->fillFromRequest($request);
        }
        $this->validatedData = $this->validateAttributes();

        return $this;
    }

    public function initialisationFromProduction(Production $production, ActionRequest|array $request): static
    {
        $this->production   = $production;
        $this->organisation = $production->organisation;
        $this->group        = $this->organisation->group;
        if (is_array($request)) {
            $this->setRawAttributes($request);
        } else {
            $this->fillFromRequest($request);
        }
        $this->validatedData = $this->validateAttributes();

        return $this;
    }

    public function initialisationFromGroup(Group $group, ActionRequest|array $request): static
    {
        $this->group = $group;
        if (is_array($request)) {
            $this->setRawAttributes($request);
        } else {
            $this->fillFromRequest($request);
        }
        $this->validatedData = $this->validateAttributes();

        return $this;
    }

}
