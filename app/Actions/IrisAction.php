<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Jun 2025 14:01:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions;

use App\Actions\Traits\WithTab;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class IrisAction
{
    use AsAction;
    use WithAttributes;
    use WithTab;

    protected Group $group;
    protected Organisation $organisation;
    protected Shop $shop;
    protected Website $website;


    protected bool $asAction = false;

    public int $hydratorsDelay = 0;


    protected array $validatedData;


    public function initialisation(ActionRequest|array $request): static
    {

        $this->website = $request->get('website');
        $this->shop = $this->website->shop;

        $this->organisation = $this->website->organisation;
        $this->group        =  $this->website->group;
        if (is_array($request)) {
            $this->setRawAttributes($request);
        } else {
            $this->fillFromRequest($request);
        }
        $this->validatedData = $this->validateAttributes();

        return $this;
    }


}
