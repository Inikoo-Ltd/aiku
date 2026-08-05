<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\Ordering\Order;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

class RecalculateTrafficSourceAttribution
{
    use AsAction;

    /**
     * Rebuilds the traffic-source attribution pivot rows for a single Customer, Prospect or Order from
     * its raw touch history, using the requested attribution model.
     *
     * This is deliberately idempotent and safe to rerun: every previously attached traffic source
     * (regardless of which model attached it) is detached before reattaching, so switching models or
     * rerunning the same model never leaves stale or duplicate pivot rows behind.
     */
    public function handle(Model $model, string $attributionModel = ProcessTrafficSourceShare::ATTRIBUTION_LINEAR): void
    {
        if (!$model instanceof Customer && !$model instanceof Order && !$model instanceof Prospect) {
            throw new \InvalidArgumentException('RecalculateTrafficSourceAttribution only supports Customer, Prospect and Order models.');
        }

        $rawTouchesData = $model->traffic_sources;

        if ($model instanceof Order) {
            $shopId          = $model->shop_id;
            $rawTouchesData  = $rawTouchesData ?: $model->customer?->traffic_sources;
        } else {
            $shopId = $model->shop_id;
        }

        $model->trafficSources()->detach();

        if (blank($rawTouchesData)) {
            return;
        }

        $touches = ParseTrafficSourceTouches::run($rawTouchesData);

        if (empty($touches)) {
            return;
        }

        AttachTrafficSourcesToModel::run($model, $shopId, $touches, $attributionModel);
    }
}
