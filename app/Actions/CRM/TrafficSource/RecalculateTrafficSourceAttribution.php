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
     * its raw touch history, using the requested attribution model. When no model is given the one
     * already stamped on the existing pivot rows is kept, so a recalculation triggered as a side effect
     * of new activity never silently re-attributes a record someone deliberately put on another model.
     *
     * This is deliberately idempotent and safe to rerun: every previously attached traffic source
     * (regardless of which model attached it) is detached before reattaching, so switching models or
     * rerunning the same model never leaves stale or duplicate pivot rows behind.
     *
     * Only the record's own touch history is used. An Order deliberately does not fall back to its
     * customer's history here: that fallback belongs to ProcessOrderTrafficSource at submit time, and
     * applying it on every recalculation would rewrite a historical order's attribution snapshot with
     * whatever the customer's journey happens to look like today.
     *
     * Because of that, an Order with no touch history of its own is left completely untouched rather
     * than detached. No order in production carries one (checkout does not persist a per-order cookie),
     * so their attribution is entirely the submit-time snapshot taken from the customer, and detaching
     * would destroy the only attribution they have without being able to rebuild anything.
     */
    public function handle(Model $model, ?string $attributionModel = null): void
    {
        if (!$model instanceof Customer && !$model instanceof Order && !$model instanceof Prospect) {
            throw new \InvalidArgumentException('RecalculateTrafficSourceAttribution only supports Customer, Prospect and Order models.');
        }

        $attributionModel ??= $model->trafficSources()->first()?->pivot->attribution_model
            ?? ProcessTrafficSourceShare::ATTRIBUTION_LINEAR;

        $rawTouchesData = $model->traffic_sources;

        if ($model instanceof Order && blank($rawTouchesData)) {
            return;
        }

        $model->trafficSources()->detach();

        if (blank($rawTouchesData)) {
            return;
        }

        $touches = ParseTrafficSourceTouches::run($rawTouchesData);

        if (empty($touches)) {
            return;
        }

        AttachTrafficSourcesToModel::run($model, $model->shop_id, $touches, $attributionModel);
    }
}
