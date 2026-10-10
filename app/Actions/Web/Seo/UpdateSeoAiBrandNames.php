<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * The names an AI answer may call the shop by ("AW Gifts", "AWGifts"), kept in the shop settings.
 * The answers already stored keep what was found when they came in.
 */
class UpdateSeoAiBrandNames extends OrgAction
{
    use WithSeoEditAuthorisation;

    public const int MAX_NAMES = 10;

    /**
     * @param  array<int, string>  $names
     */
    public function handle(Shop $shop, array $names): Shop
    {
        $settings = $shop->settings ?? [];
        data_set($settings, StoreSeoAiAnswer::BRAND_NAMES_SETTING, collect($names)->map(fn (string $name) => trim($name))->filter()->unique(fn (string $name) => mb_strtolower($name))->values()->all());
        $shop->update(['settings' => $settings]);

        return $shop;
    }

    public function rules(): array
    {
        return [
            'names'   => ['present', 'array', 'max:'.self::MAX_NAMES],
            'names.*' => ['string', 'min:2', 'max:64'],
        ];
    }

    public function asController(Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData['names']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
