<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Feb 2024 20:59:47 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions;

use App\Actions\Traits\WithTab;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class RetinaWebhookAction
{
    use AsAction;
    use WithAttributes;
    use WithTab;


    protected Website $website;
    protected ShopifyUser $shopifyUser;
    protected ?Fulfilment $fulfilment;
    protected ?FulfilmentCustomer $fulfilmentCustomer;
    protected Organisation $organisation;
    protected Shop $shop;

    protected array $validatedData;

    public function initialisation(ActionRequest $request): static
    {
        $this->website = $request->get('website');
        $this->shop = $this->website->shop;
        $this->fulfilment = $this->shop->fulfilment;
        $this->organisation = $this->shop->organisation;
        $this->fillFromRequest($request);
        $this->validatedData = $this->validateAttributes();
        return $this;
    }






}
