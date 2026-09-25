<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Barcode\Json;

use App\Actions\OrgAction;
use App\Models\Helpers\Barcode;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\ActionRequest;

/**
 * Proposes the lowest free number of the group pool. Nothing is booked until the form that
 * asked for it is saved, so an abandoned proposal leaves no orphan behind.
 */
class GetNextFreeBarcode extends OrgAction
{
    public function handle(Group $group): ?Barcode
    {
        return Barcode::where('group_id', $group->id)->free()->orderBy('number')->first();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['masters.edit', 'goods.edit']);
    }

    /**
     * @return array{number: string|null, free: int}
     */
    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return [
            'number' => $this->handle($this->group)?->number,
            'free'   => Barcode::where('group_id', $this->group->id)->free()->count(),
        ];
    }
}
