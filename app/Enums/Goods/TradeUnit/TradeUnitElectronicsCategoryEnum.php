<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\TradeUnit;

use App\Enums\EnumHelperTrait;

/**
 * Electrical and electronic equipment category of a unit, as numbered in the WEEE take-back schemes.
 */
enum TradeUnitElectronicsCategoryEnum: string
{
    use EnumHelperTrait;

    case FLUORESCENT_LAMPS = '3.1.1';
    case LED_LAMPS = '3.1.2';
    case OTHER_HOUSEHOLD_LAMPS = '3.3.1';
    case OTHER_NON_HOUSEHOLD_LAMPS = '3.4.1';
    case SMALL_AUDIO_ACCESSORIES = '5.8.1';
    case CAMERAS_PORTABLE_PLAYERS = '5.9.1';
    case SMALL_NON_HOUSEHOLD_LUMINAIRES_UP_TO_1KG = '5.12.1';
    case SMALL_HOUSEHOLD_LUMINAIRES_UP_TO_1KG = '5.12.2';
    case SMALL_NON_HOUSEHOLD_LUMINAIRES_ABOVE_1KG = '5.13.1';
    case SMALL_HOUSEHOLD_LUMINAIRES_ABOVE_1KG = '5.13.2';
    case SPORTS_TOYS_GAMING = '5.17.1';

    public static function labels(): array
    {
        return [
            '3.1.1'  => '3.1.1 '.__('Linear fluorescent lamps, compact fluorescent lamps, fluorescent lamps'),
            '3.1.2'  => '3.1.2 '.__('LED lamps, LED strips (1 piece = 1 m)'),
            '3.3.1'  => '3.3.1 '.__('Other household lamps'),
            '3.4.1'  => '3.4.1 '.__('Other lamps for non-household use'),
            '5.8.1'  => '5.8.1 '.__('Headphones, remote controls, microphones, 3D glasses, satellite and TV reception components (excluding TV, satellite and DVB-T receivers), adapters, chargers, cable sets, digital and other voice and audio recorders, personal audio and video players (MP3, MP4, iPod) without screens, radios, clock radios, separate photographic flash units, separate electrically powered telephoto lenses'),
            '5.9.1'  => '5.9.1 '.__('Video cameras, cameras, portable radio-cassette players, portable DVD players'),
            '5.12.1' => '5.12.1 '.__('Small luminaires for non-household use up to max. 1 kg'),
            '5.12.2' => '5.12.2 '.__('Small household luminaires up to max. 1 kg'),
            '5.13.1' => '5.13.1 '.__('Small luminaires for non-household above 1 kg'),
            '5.13.2' => '5.13.2 '.__('Small household luminaires above 1 kg'),
            '5.17.1' => '5.17.1 '.__('Sports watches, heart-rate monitors, pedometers, camping lamps and lights, headlamps, remote-controlled cars, aircraft and other toys, sound-producing toys, pocket digital toys, motors, transformer units and other gaming accessories, gaming devices (joysticks, steering wheels, gamepads)'),
        ];
    }

    public static function options(): array
    {
        return array_map(
            fn (self $category) => ['value' => $category->value, 'label' => self::labels()[$category->value]],
            self::cases()
        );
    }
}
