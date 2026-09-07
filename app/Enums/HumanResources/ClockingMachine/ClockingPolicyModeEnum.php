<?php

namespace App\Enums\HumanResources\ClockingMachine;

use App\Enums\EnumHelperTrait;

enum ClockingPolicyModeEnum: string
{
    use EnumHelperTrait;

    case ONSITE = 'onsite';
    case REMOTE = 'remote';
    case HYBRID = 'hybrid';
    case NO_LOCATION = 'no_location';

    public static function labels(): array
    {
        return [
            'onsite'      => __('Onsite'),
            'remote'      => __('Remote'),
            'hybrid'      => __('Hybrid'),
            'no_location' => __('Phone Without Location'),
        ];
    }

    /**
     * Remote staff are not expected on site at all, while NO_LOCATION covers staff who are on site
     * but carry a phone that cannot report a usable position. Both skip the geofence, and they are
     * kept apart so attendance reporting can still tell a home worker from a broken handset.
     */
    public function skipsLocationCheck(): bool
    {
        return in_array($this, [self::REMOTE, self::NO_LOCATION], true);
    }
}
