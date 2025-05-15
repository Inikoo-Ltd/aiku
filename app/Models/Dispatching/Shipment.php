<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 22:27:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Helpers\UniversalSearch;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InCustomer;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Dispatching\Shipment
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $shipper_id
 * @property int|null $shipper_account_id
 * @property int|null $customer_id
 * @property string|null $reference
 * @property string $tracking
 * @property string|null $error_message
 * @property array<array-key, mixed> $data
 * @property string|null $shipped_at
 * @property string|null $tracked_at
 * @property int $number_shipment_trackings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property int|null $number_parcels
 * @property array<array-key, mixed>|null $api_response
 * @property string $combined_label_url
 * @property array<array-key, mixed>|null $trackings
 * @property array<array-key, mixed>|null $tracking_urls
 * @property array<array-key, mixed>|null $label_urls
 * @property-read Customer|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Dispatching\DeliveryNote> $deliveryNotes
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PalletReturn> $palletReturns
 * @property-read \App\Models\Dispatching\Shipper|null $shipper
 * @property-read Shop|null $shop
 * @property-read UniversalSearch|null $universalSearch
 * @method static Builder<static>|Shipment newModelQuery()
 * @method static Builder<static>|Shipment newQuery()
 * @method static Builder<static>|Shipment onlyTrashed()
 * @method static Builder<static>|Shipment query()
 * @method static Builder<static>|Shipment withTrashed()
 * @method static Builder<static>|Shipment withoutTrashed()
 * @mixin Eloquent
 */
class Shipment extends Model
{
    use SoftDeletes;
    use HasUniversalSearch;
    use HasFactory;
    use InCustomer;

    protected $casts = [
        'data'          => 'array',
        'api_response'  => 'array',
        'trackings'     => 'array',
        'tracking_urls' => 'array',
        'label_urls'    => 'array',
    ];

    protected $attributes = [
        'data'          => '{}',
        'api_response'  => '{}',
        'trackings'     => '{}',
        'tracking_urls' => '{}',
        'label_urls'    => '{}',
    ];

    protected $guarded = [];


    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class);
    }


    public function deliveryNotes(): MorphToMany
    {
        return $this->morphedByMany(DeliveryNote::class, 'model', 'model_has_shipments');
    }

    public function palletReturns(): MorphToMany
    {
        return $this->morphedByMany(PalletReturn::class, 'model', 'model_has_shipments');
    }


}
