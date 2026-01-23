<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 17:41:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\UniversalScan;

use App\Actions\OrgAction;
use App\Http\Resources\Helpers\UniversalScanResource;
use App\Models\Helpers\UniversalSearch;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class ShowUniversalScan extends OrgAction
{
    public function handle(Warehouse $warehouse, string $ulid, ActionRequest $request): UniversalSearch
    {
        /** @var UniversalSearch $query */
        $query = $warehouse->universalSearches();

        if ($type = $request->input('type')) {
            $query->where('model_type', $type);
        }

        $query->where('keyword', $ulid);

        return $query->firstOrFail();
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, string $ulid, ActionRequest $request): UniversalSearch
    {
        $this->initialisation($organisation, $request);

        return $this->handle($warehouse, $ulid, $request);
    }

    public function jsonResponse(UniversalSearch $universalSearch): UniversalScanResource
    {
        return new UniversalScanResource($universalSearch);
    }
}
