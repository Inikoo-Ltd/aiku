<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 12:17:53 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $number_productions
 * @property int $number_productions_state_in_process
 * @property int $number_productions_state_open
 * @property int $number_productions_state_closing_down
 * @property int $number_productions_state_closed
 * @property int $number_raw_materials
 * @property int $number_raw_materials_type_stock
 * @property int $number_raw_materials_type_consumable
 * @property int $number_raw_materials_type_intermediate
 * @property int $number_raw_materials_state_in_process
 * @property int $number_raw_materials_state_in_use
 * @property int $number_raw_materials_state_orphan
 * @property int $number_raw_materials_state_discontinued
 * @property int $number_raw_materials_unit_unit
 * @property int $number_raw_materials_unit_pack
 * @property int $number_raw_materials_unit_carton
 * @property int $number_raw_materials_unit_liter
 * @property int $number_raw_materials_unit_kilogram
 * @property int $number_raw_materials_stock_status_unlimited
 * @property int $number_raw_materials_stock_status_surplus
 * @property int $number_raw_materials_stock_status_optimal
 * @property int $number_raw_materials_stock_status_low
 * @property int $number_raw_materials_stock_status_critical
 * @property int $number_raw_materials_stock_status_out_of_stock
 * @property int $number_raw_materials_stock_status_error
 * @property int $number_manufacture_tasks
 * @property int $number_manufacture_tasks_operative_reward_terms_above_upper_lim
 * @property int $number_manufacture_tasks_operative_reward_terms_above_lower_lim
 * @property int $number_manufacture_tasks_operative_reward_terms_always
 * @property int $number_manufacture_tasks_operative_reward_terms_never
 * @property int $number_manufacture_tasks_operative_reward_allowance_type_on_top
 * @property int $number_manufacture_tasks_operative_reward_allowance_type_offset
 * @property int $number_artefacts
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationManufactureStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationManufactureStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationManufactureStats query()
 * @mixin \Eloquent
 */
class OrganisationManufactureStats extends Model
{
    protected $table = 'organisation_manufacture_stats';

    protected $guarded = [];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
