<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Jun 2023 08:43:57 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use App\Actions\Utils\Abbreviate;
use App\Actions\Utils\ReadableRandomStringGenerator;
use App\Enums\CRM\Prospect\ProspectContactedStateEnum;
use App\Enums\CRM\Prospect\ProspectFailStatusEnum;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Enums\CRM\Prospect\ProspectSuccessStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\SubscriptionEvent;
use App\Models\Helpers\Address;
use App\Models\Helpers\UniversalSearch;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasAddress;
use App\Models\Traits\HasAddresses;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InCustomer;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\CRM\Prospect
 *
 * @property int $id
 * @property string $slug
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int|null $customer_id
 * @property string|null $name
 * @property string|null $contact_name
 * @property string|null $company_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $identity_document_type
 * @property string|null $identity_document_number
 * @property string|null $contact_website
 * @property int|null $address_id
 * @property array<array-key, mixed> $location
 * @property bool $is_valid_email
 * @property ProspectStateEnum $state
 * @property ProspectContactedStateEnum $contacted_state
 * @property ProspectFailStatusEnum $fail_status
 * @property ProspectSuccessStatusEnum $success_status
 * @property bool $dont_contact_me
 * @property bool $can_contact_by_email
 * @property bool $can_contact_by_phone
 * @property bool $can_contact_by_address
 * @property array<array-key, mixed> $data
 * @property string|null $contacted_at
 * @property \Illuminate\Support\Carbon|null $last_contacted_at
 * @property \Illuminate\Support\Carbon|null $last_opened_at
 * @property \Illuminate\Support\Carbon|null $last_clicked_at
 * @property \Illuminate\Support\Carbon|null $dont_contact_me_at
 * @property \Illuminate\Support\Carbon|null $failed_at
 * @property \Illuminate\Support\Carbon|null $registered_at
 * @property \Illuminate\Support\Carbon|null $invoiced_at
 * @property \Illuminate\Support\Carbon|null $last_soft_bounced_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property string|null $source_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property bool $is_opt_in
 * @property-read Address|null $address
 * @property-read Collection<int, Address> $addresses
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\CRM\Customer|null $customer
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read Shop $shop
 * @property-read Collection<int, SubscriptionEvent> $subscriptionEvents
 * @property-read UniversalSearch|null $universalSearch
 * @method static \Database\Factories\CRM\ProspectFactory factory($count = null, $state = [])
 * @method static Builder<static>|Prospect newModelQuery()
 * @method static Builder<static>|Prospect newQuery()
 * @method static Builder<static>|Prospect onlyTrashed()
 * @method static Builder<static>|Prospect query()
 * @method static Builder<static>|Prospect withTrashed()
 * @method static Builder<static>|Prospect withoutTrashed()
 * @mixin Eloquent
 */
class Prospect extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasFactory;
    use InCustomer;
    use HasAddress;
    use HasAddresses;
    use HasHistory;

    protected $casts = [
        'data'                 => 'array',
        'location'             => 'array',
        'contact_name_components'     => 'array',
        'state'                => ProspectStateEnum::class,
        'contacted_state'      => ProspectContactedStateEnum::class,
        'fail_status'          => ProspectFailStatusEnum::class,
        'success_status'       => ProspectSuccessStatusEnum::class,
        'dont_contact_me'      => 'boolean',
        'last_contacted_at'    => 'datetime',
        'last_opened_at'       => 'datetime',
        'last_clicked_at'      => 'datetime',
        'dont_contact_me_at'   => 'datetime',
        'failed_at'            => 'datetime',
        'registered_at'        => 'datetime',
        'invoiced_at'          => 'datetime',
        'last_soft_bounced_at' => 'datetime',
        'fetched_at'           => 'datetime',
        'last_fetched_at'      => 'datetime',

    ];

    protected $attributes = [
        'contact_name_components' => '{}',
        'data'     => '{}',
        'location' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'crm'
        ];
    }

    protected array $auditInclude = [
        'contact_name',
        'company_name',
        'email',
        'phone',
        'contact_website',
        'identity_document_type',
        'identity_document_number',
    ];


    protected static function booted(): void
    {
        static::creating(
            function (Prospect $prospect) {
                $prospect->name = $prospect->company_name == '' ? $prospect->contact_name : $prospect->company_name;
            }
        );
        static::updated(function (Prospect $prospect) {
            if ($prospect->wasChanged(['company_name', 'contact_name'])) {
                $prospect->updateQuietly(
                    [
                        'name' => $prospect->company_name == '' ? $prospect->contact_name : $prospect->company_name
                    ]
                );
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                $slug = '';
                if ($this->email) {
                    $tmp = explode('@', $this->email);
                    if (!empty($tmp[0])) {
                        $slug = substr($tmp[0], 0, 8);
                    }
                }


                $name = $this->company_name ? ' '.Abbreviate::run(string: $this->company_name, maximumLength: 4) : '';
                if ($name == '') {
                    $name = $this->contact_name ? ' '.Abbreviate::run(string: $this->contact_name, maximumLength: 4) : '';
                }
                $slug .= $name;


                if ($slug == '') {
                    $slug = ReadableRandomStringGenerator::run();
                }

                return $slug;
            })
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }

    public function subscriptionEvents(): MorphMany
    {
        return $this->morphMany(SubscriptionEvent::class, 'model');
    }


}
