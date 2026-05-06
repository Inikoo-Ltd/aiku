<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Sept 2024 11:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerClient\Search;

use App\Actions\HydrateModel;
use App\Models\Dropshipping\CustomerClient;
use Illuminate\Support\Collection;

class ReindexCustomerClientSearch extends HydrateModel
{
    public string $commandSignature = 'search:customer-clients {organisations?*} {--s|slugs=}';


    public function handle(CustomerClient $customerClient): void
    {
    }

    protected function getModel(string $slug): CustomerClient
    {
        return CustomerClient::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return CustomerClient::withTrashed()->get();
    }
}
