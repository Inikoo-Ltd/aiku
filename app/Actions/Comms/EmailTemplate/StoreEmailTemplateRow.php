<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 21 Sep 2026 10:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\EmailTemplate;

use App\Actions\OrgAction;
use App\Enums\Comms\EmailTemplate\EmailTemplateBuilderEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateRowTypeEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreEmailTemplateRow extends OrgAction
{
    public function handle(Shop $shop, array $modelData): EmailTemplate
    {
        $row = Arr::get($modelData, 'layout');
        Arr::forget($row, 'metadata');

        return StoreEmailTemplate::make()->action(
            $shop->group,
            [
                'name'        => Arr::get($modelData, 'name'),
                'layout'      => $row,
                'is_seeded'   => false,
                'builder'     => EmailTemplateBuilderEnum::BEEFREE,
                'state'       => EmailTemplateStateEnum::ACTIVE,
                'active_at'   => now(),
                'language_id' => $shop->language_id,
                'data'        => [
                    'is_row'   => true,
                    'row_type' => Arr::get($modelData, 'row_type', EmailTemplateRowTypeEnum::BLOCK->value),
                ],
                'shop_id'     => $shop->id,
            ],
            strict: false
        );
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'layout'   => ['required', 'array'],
            'row_type' => ['sometimes', Rule::enum(EmailTemplateRowTypeEnum::class)],
        ];
    }

    public function action(Shop $shop, array $modelData): EmailTemplate
    {
        $this->asAction = true;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }

    public function asController(Shop $shop, ActionRequest $request): EmailTemplate
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }
}
