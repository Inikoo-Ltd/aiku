<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Jul 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\Ses;

use App\Enums\EnumHelperTrait;

enum SesRegionEnum: string
{
    use EnumHelperTrait;

    case US_EAST_1 = 'us-east-1';
    case US_EAST_2 = 'us-east-2';
    case US_WEST_1 = 'us-west-1';
    case US_WEST_2 = 'us-west-2';
    case AF_SOUTH_1 = 'af-south-1';
    case AP_EAST_1 = 'ap-east-1';
    case AP_SOUTH_1 = 'ap-south-1';
    case AP_SOUTH_2 = 'ap-south-2';
    case AP_SOUTHEAST_1 = 'ap-southeast-1';
    case AP_SOUTHEAST_2 = 'ap-southeast-2';
    case AP_SOUTHEAST_3 = 'ap-southeast-3';
    case AP_SOUTHEAST_4 = 'ap-southeast-4';
    case AP_SOUTHEAST_5 = 'ap-southeast-5';
    case AP_NORTHEAST_1 = 'ap-northeast-1';
    case AP_NORTHEAST_2 = 'ap-northeast-2';
    case AP_NORTHEAST_3 = 'ap-northeast-3';
    case CA_CENTRAL_1 = 'ca-central-1';
    case CA_WEST_1 = 'ca-west-1';
    case EU_CENTRAL_1 = 'eu-central-1';
    case EU_CENTRAL_2 = 'eu-central-2';
    case EU_WEST_1 = 'eu-west-1';
    case EU_WEST_2 = 'eu-west-2';
    case EU_WEST_3 = 'eu-west-3';
    case EU_SOUTH_1 = 'eu-south-1';
    case EU_SOUTH_2 = 'eu-south-2';
    case EU_NORTH_1 = 'eu-north-1';
    case IL_CENTRAL_1 = 'il-central-1';
    case ME_SOUTH_1 = 'me-south-1';
    case ME_CENTRAL_1 = 'me-central-1';
    case SA_EAST_1 = 'sa-east-1';

    public function label(): string
    {
        return match ($this) {
            self::US_EAST_1 => 'US East (N. Virginia)',
            self::US_EAST_2 => 'US East (Ohio)',
            self::US_WEST_1 => 'US West (N. California)',
            self::US_WEST_2 => 'US West (Oregon)',
            self::AF_SOUTH_1 => 'Africa (Cape Town)',
            self::AP_EAST_1 => 'Asia Pacific (Hong Kong)',
            self::AP_SOUTH_1 => 'Asia Pacific (Mumbai)',
            self::AP_SOUTH_2 => 'Asia Pacific (Hyderabad)',
            self::AP_SOUTHEAST_1 => 'Asia Pacific (Singapore)',
            self::AP_SOUTHEAST_2 => 'Asia Pacific (Sydney)',
            self::AP_SOUTHEAST_3 => 'Asia Pacific (Jakarta)',
            self::AP_SOUTHEAST_4 => 'Asia Pacific (Melbourne)',
            self::AP_SOUTHEAST_5 => 'Asia Pacific (Malaysia)',
            self::AP_NORTHEAST_1 => 'Asia Pacific (Tokyo)',
            self::AP_NORTHEAST_2 => 'Asia Pacific (Seoul)',
            self::AP_NORTHEAST_3 => 'Asia Pacific (Osaka)',
            self::CA_CENTRAL_1 => 'Canada (Central)',
            self::CA_WEST_1 => 'Canada West (Calgary)',
            self::EU_CENTRAL_1 => 'Europe (Frankfurt)',
            self::EU_CENTRAL_2 => 'Europe (Zurich)',
            self::EU_WEST_1 => 'Europe (Ireland)',
            self::EU_WEST_2 => 'Europe (London)',
            self::EU_WEST_3 => 'Europe (Paris)',
            self::EU_SOUTH_1 => 'Europe (Milan)',
            self::EU_SOUTH_2 => 'Europe (Spain)',
            self::EU_NORTH_1 => 'Europe (Stockholm)',
            self::IL_CENTRAL_1 => 'Israel (Tel Aviv)',
            self::ME_SOUTH_1 => 'Middle East (Bahrain)',
            self::ME_CENTRAL_1 => 'Middle East (UAE)',
            self::SA_EAST_1 => 'South America (São Paulo)',
        };
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[] = [
                'label' => $case->label().' ('.$case->value.')',
                'value' => $case->value,
            ];
        }
        return $options;
    }
}
