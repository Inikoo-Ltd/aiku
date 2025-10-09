<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 14 May 2023 00:49:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use App\Models\Helpers\Address;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasAddress
{
    public function getLocation($scope = 'contact'): array
    {
        $location = [null, '', ''];
        if ($contactAddress = $this->getAddress($scope)) {
            $location = $contactAddress->getLocation() ?? [null, '', ''];
        }

        return $location;
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function getAddress($scope): ?Address
    {
        /** @var Address $address */
        return $this->addresses()->where('scope', '=', $scope)->first();
    }

}
