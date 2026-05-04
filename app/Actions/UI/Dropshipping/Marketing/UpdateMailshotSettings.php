<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Apr 2026 15:43:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateMailshotSettings extends OrgAction
{
    use WithActionUpdate;

    public function authorize(ActionRequest $request): bool
    {
        // return $request->user()->authTo("marketing.{$this->shop->id}.edit");
        return true;
    }

    public function handle(Shop $shop, array $modelData): Shop
    {
        if (Arr::exists($modelData, 'after_days')) {
            data_set($modelData, 'settings.mailshot_tracking.after_days', Arr::pull($modelData, 'after_days'));
        }

        if (Arr::exists($modelData, 'every_hours')) {
            data_set($modelData, 'settings.mailshot_tracking.every_hours', Arr::pull($modelData, 'every_hours'));
        }
        return $this->update($shop, $modelData, ['settings']);
    }

    public function rules(): array
    {
        return [
            'after_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'every_hours' => ['nullable', 'integer', 'min:1', 'max:24'],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }
}
