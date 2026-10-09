<?php

namespace App\Models\CRM;

use App\Enums\CRM\Appointment\AppointmentSourceEnum;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $appointment_type_id
 * @property int|null $customer_id
 * @property int|null $user_id
 * @property int|null $created_by_user_id
 * @property AppointmentStateEnum $state
 * @property AppointmentSourceEnum $source
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 * @property string $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property int $number_visitors
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read AppointmentType $appointmentType
 * @property-read Customer|null $customer
 * @property-read User|null $user
 * @property-read User|null $createdBy
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop $shop
 * @mixin \Eloquent
 */
class Appointment extends Model implements Auditable
{
    use InShop;
    use SoftDeletes;
    use HasHistory;

    protected $casts = [
        'state'        => AppointmentStateEnum::class,
        'source'       => AppointmentSourceEnum::class,
        'starts_at'    => 'datetime',
        'ends_at'      => 'datetime',
        'cancelled_at' => 'datetime',
        'data'         => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'crm'
        ];
    }

    protected array $auditInclude = [
        'appointment_type_id',
        'user_id',
        'state',
        'starts_at',
        'ends_at',
        'contact_name',
        'email',
        'phone',
        'number_visitors',
        'notes',
    ];

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
