<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Oct 2024 11:58:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Models\Traits\InCustomer;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $group_id
 * @property int|null $organisation_id
 * @property int|null $shop_id
 * @property int|null $website_id
 * @property int|null $customer_id
 * @property string|null $user_type
 * @property int|null $user_id
 * @property array<array-key, mixed> $tags
 * @property string $auditable_type
 * @property int $auditable_id
 * @property string $event
 * @property string|null $comments
 * @property array<array-key, mixed>|null $old_values
 * @property array<array-key, mixed>|null $new_values
 * @property array<array-key, mixed>|null $data
 * @property string|null $url
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $source_id
 * @property-read \App\Models\CRM\Customer|null $customer
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|History newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|History newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|History query()
 * @mixin \Eloquent
 */
class History extends Model
{
    use InCustomer;

    protected $table = 'audits';

    protected $casts = [
        'tags'       => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
        'data'       => 'array',
    ];

    protected $attributes = [
        'tags'       => '{}',
        'old_values' => '{}',
        'new_values' => '{}',
        'data'       => '{}',
    ];

    protected $guarded = [];
}
