<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Jun 2023 01:35:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Web\Webpage;

use App\Enums\EnumHelperTrait;
use Illuminate\Support\Arr;

enum WebpageTypeEnum: string
{
    use EnumHelperTrait;

    case STOREFRONT = "storefront";
    case CATALOGUE = "catalogue";
    case CONTENT = "content";
    case BLOG = "blog";

    public static function labels(): array
    {
        return [
            "storefront" => __("storefront"),
            "catalogue" => __("catalogue"),
            "content" => __("content"),
            "blog" => __("blog")
        ];
    }

    public function label(): string
    {
        return Arr::get($this->labels(), $this->value);
    }

    public static function stateIcon(): array
    {
        // Icon is imported in resources/js/Composables/Icon/WebpageTypeEnum.ts
        return [
            "storefront" => [
                "tooltip" => __("Storefront"),
                "icon" => "fal fa-home",
                "class" => "text-lime-500", // Color for normal icon (Aiku)
                "color" => "lime", // Color for box (Retina)
                "app" => [
                    "name" => "home",
                    "type" => "font-awesome-5",
                ],
            ],
            "catalogue" => [
                "tooltip" => __("Catalogue"),
                "icon" => "fal fa-books",
                "class" => "text-indigo-400",
                "color" => "indigo",
                "app" => [
                    "name" => "books",
                    "type" => "font-awesome-5",
                ],
            ],
            "content" => [
                "tooltip" => __("Content"),
                "icon" => "fal fa-columns",
                "class" => "text-emerald-500",
                "color" => "emerald",
                "app" => [
                    "name" => "columns",
                    "type" => "font-awesome-5",
                ],
            ],
            "blog" => [
                "tooltip" => __("Blog"),
                "icon" => "fal fa-newspaper",
                "class" => "text-red-500",
                "color" => "slate",
                "app" => [
                    "name" => "newspaper",
                    "type" => "font-awesome-5",
                ],
            ],
        ];
    }
}
