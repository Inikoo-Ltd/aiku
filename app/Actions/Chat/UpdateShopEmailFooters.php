<?php

/*
 * Author: Yudhistira <artha@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * The shop's footer under every email customer service sends, after the agent's own signature,
 * in the language the email goes out in. One without a language is used for every language
 * that has none of its own; empty text removes a footer.
 */
class UpdateShopEmailFooters extends OrgAction
{
    use WithChatAgentAuthorisation;

    public const string DEFAULT_KEY = 'default';

    public function authorize(ActionRequest $request): bool
    {
        return $this->userCanActOnChatOnShop($request->user(), $this->shop);
    }

    public function rules(): array
    {
        return [
            'footers'            => ['present', 'array', 'max:50'],
            'footers.*.language' => ['nullable', 'string', 'max:10', 'distinct'],
            'footers.*.text'     => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function handle(Shop $shop, array $modelData): Shop
    {
        $footers = collect($modelData['footers'] ?? [])
            ->mapWithKeys(fn (array $footer) => [($footer['language'] ?? null) ?: self::DEFAULT_KEY => trim((string) ($footer['text'] ?? ''))])
            ->filter(fn (string $text) => $text !== '')
            ->all();

        $settings = $shop->settings ?? [];

        if ($footers === []) {
            data_forget($settings, 'chat.email_footers');
        } else {
            data_set($settings, 'chat.email_footers', $footers);
        }

        $shop->update(['settings' => $settings]);

        return $shop;
    }

    public static function footerFor(Shop $shop, ?string $languageCode): string
    {
        $footers = (array) data_get($shop->settings, 'chat.email_footers', []);

        return (string) (($languageCode ? ($footers[$languageCode] ?? null) : null) ?? $footers[self::DEFAULT_KEY] ?? '');
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        $this->handle($shop, $this->validatedData);

        return back()->with('notification', [
            'status' => 'success',
            'title'  => __('Email footers saved'),
        ]);
    }
}
