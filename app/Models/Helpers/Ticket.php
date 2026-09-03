<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasTicketImages;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $group_id
 * @property int|null $organisation_id
 * @property int|null $shop_id
 * @property int|null $customer_id
 * @property TicketTypeEnum $type
 * @property int $number
 * @property string $reference
 * @property TicketStatusEnum $status
 * @property ChatPriorityEnum $priority
 * @property string $subject
 * @property string|null $description
 * @property string|null $reporter_type
 * @property int|null $reporter_id
 * @property int|null $assignee_id
 * @property string|null $model_type
 * @property int|null $model_id
 * @property array<array-key, mixed> $data
 * @property int|null $rating
 * @property string|null $rating_comment
 * @property \Illuminate\Support\Carbon|null $rated_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read User|null $assignee
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketComment> $comments
 * @property-read Customer|null $customer
 * @property-read Model|\Eloquent|null $model
 * @property-read Model|\Eloquent|null $reporter
 * @mixin \Eloquent
 */
class Ticket extends Model implements Auditable, HasMedia
{
    use SoftDeletes;
    use HasHistory;
    use InShop;
    use InteractsWithMedia;
    use HasTicketImages;

    protected $guarded = [];

    protected $attributes = [
        'data'     => '{}',
        'status'   => TicketStatusEnum::OPEN,
        'priority' => ChatPriorityEnum::NORMAL,
    ];

    protected array $auditInclude = [
        'status',
        'priority',
        'assignee_id',
        'subject',
    ];

    protected function casts(): array
    {
        return [
            'type'        => TicketTypeEnum::class,
            'status'      => TicketStatusEnum::class,
            'priority'    => ChatPriorityEnum::class,
            'data'        => 'array',
            'rated_at'    => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at'   => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function reporter(): MorphTo
    {
        return $this->morphTo();
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }
}
