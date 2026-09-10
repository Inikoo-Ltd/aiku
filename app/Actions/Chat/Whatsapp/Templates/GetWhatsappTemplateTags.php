<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Enums\CRM\Livechat\WhatsappTemplateTagEnum;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The tag list for the template builder, with shop-level samples replaced by the shop's
 * real values.
 *
 * Customer and order samples have to stay generic — they differ per recipient — but a
 * shop's name, site and contact details are the same in every message it sends. Showing
 * the real ones means Meta's reviewer sees exactly the domain the template will use,
 * rather than an unrelated one that makes the template look like it belongs to someone
 * else.
 */
class GetWhatsappTemplateTags
{
    use AsObject;

    /**
     * @return array<int, array{name: string, value: string, example: string, group: string}>
     */
    public function handle(?Shop $shop = null): array
    {
        $overrides = $shop ? array_filter([
            WhatsappTemplateTagEnum::SHOP_NAME->value  => $shop->name,
            WhatsappTemplateTagEnum::SHOP_URL->value   => $this->shopUrl($shop),
            WhatsappTemplateTagEnum::SHOP_EMAIL->value => $shop->email,
            WhatsappTemplateTagEnum::SHOP_PHONE->value => $shop->phone,
        ]) : [];

        return array_map(
            function (array $tag) use ($overrides) {
                $name = trim($tag['value'], '[]');

                if (isset($overrides[$name])) {
                    $tag['example'] = $overrides[$name];
                }

                return $tag;
            },
            WhatsappTemplateTagEnum::tags()
        );
    }

    protected function shopUrl(Shop $shop): ?string
    {
        $domain = $shop->website?->domain;

        return $domain ? 'https://'.ltrim($domain, '/') : null;
    }
}
