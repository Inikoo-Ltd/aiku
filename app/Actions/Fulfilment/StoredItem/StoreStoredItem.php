<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem;

use App\Models\Fulfilment\StoredItem;
use App\Models\Sales\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreStoredItem
{
    use AsAction;
    use WithAttributes;

    public function handle(Customer $customer, array $modelData): StoredItem
    {
        /** @var StoredItem $storedItem */
        $storedItem = $customer->storedItems()->create($modelData);
        CustomerHydrateStoredItems::dispatch($customer);

        return $storedItem;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasPermissionTo("fulfilment.edit");
    }


    public function rules(): array
    {
        return [
            'code'          => ['required', 'max:255'],

        ];
    }

    public function asController(Customer $customer, ActionRequest $request): StoredItem
    {
        $request->validate();

        return $this->handle($customer, $request->validated());
    }

    public function htmlResponse(StoredItem $storedItem, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('hr.employees.show', $storedItem->slug);
    }
}
