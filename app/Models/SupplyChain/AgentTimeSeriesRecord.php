<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 14:44:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentTimeSeriesRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentTimeSeriesRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentTimeSeriesRecord query()
 * @mixin \Eloquent
 */
class AgentTimeSeriesRecord extends Model
{
    protected $table = 'agent_time_series_records';

    protected $guarded = [];



}
