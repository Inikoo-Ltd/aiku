<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Enums\Web\Webpage;

use App\Enums\EnumHelperTrait;
use Illuminate\Support\Arr;

enum WebpageSeoStructureTypeEnum: string
{
    use EnumHelperTrait;

    case ECOMMERCE = 'ecommerce';
    case ORGANISATION = 'organisation';
    /*   case SPORT = 'sport';
      case JOB = 'job';
      case ENTERTAINMENT = 'entertainment'; */
    case NEWS = 'news'; /*
    case FOOD_AND_DRINK = 'food_and_drink';
    case EDUCATION_AND_SCIENCE = 'education_and_science'; */

    public static function labels(): array
    {
        return [
            'ecommerce' => __('E-commerce'),
            'organisation' => __('Organisation'),
            /* 'sport' => __('Sport'),
            'job' => __('Job'),
            'entertainment' => __('Entertainment'), */
            'news' => __('News'),
            /*   'food_and_drink' => __('Food and Drink'),
            'education_and_science' => __('Education and Science'), */
        ];
    }

    public function label(): string
    {
        return Arr::get($this->labels(), $this->value);
    }
}
