<?php

namespace App\Models\CRM;

use App\Enums\CRM\Appointment\AppointmentSourceEnum;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\Helpers\Audit;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $appointment_type_id
 * @property string|null $visitor_type
 * @property int|null $visitor_id
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
 * @property \Illuminate\Support\Carbon|null $accepted_at
 * @property \Illuminate\Support\Carbon|null $declined_at
 * @property string|null $state_reason
 * @property \Illuminate\Support\Carbon|null $reminder_sent_at
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read AppointmentType $appointmentType
 * @property-read Customer|Prospect|null $visitor
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
        'state'            => AppointmentStateEnum::class,
        'source'           => AppointmentSourceEnum::class,
        'starts_at'        => 'datetime',
        'ends_at'          => 'datetime',
        'cancelled_at'     => 'datetime',
        'accepted_at'      => 'datetime',
        'declined_at'      => 'datetime',
        'reminder_sent_at' => 'datetime',
        'data'             => 'array',
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
        'visitor_type',
        'visitor_id',
        'user_id',
        'state',
        'starts_at',
        'ends_at',
        'contact_name',
        'email',
        'phone',
        'number_visitors',
        'notes',
        'state_reason',
    ];

    public function whatsappUrl(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone);
        if (str_starts_with((string) $this->phone, '00') || str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return $digits ? 'https://wa.me/'.$digits : null;
    }

    /**
     * What the visitor's own link carries, so they can move or cancel without signing in.
     */
    public function manageToken(): string
    {
        return hash_hmac('sha256', 'appointment-manage:'.$this->id, (string) config('app.key'));
    }

    public function manageUrl(): ?string
    {
        $domain = $this->shop->website?->domain;

        return $domain ? 'https://'.$domain.'/appointment/'.$this->id.'/'.$this->manageToken() : null;
    }

    public function canBeChangedByVisitor(): bool
    {
        return $this->state->holdsSlot() && $this->starts_at->isFuture();
    }

    public static function auditAuthorName(Audit $audit, string $contactName): ?string
    {
        return match (true) {
            $audit->user instanceof User    => $audit->user->chatName(),
            $audit->user instanceof WebUser => $contactName,
            default                         => null,
        };
    }

    /**
     * Who put the appointment in its current state and when, read off the audit of that change.
     * With no such audit the state is the one it was booked in, so it comes from the booking.
     * A visitor cancelling from their email link is not signed in, so nobody is on that audit.
     *
     * @return array{by: string|null, at: \Illuminate\Support\Carbon|null}
     */
    public function stateChange(): array
    {
        $audits = $this->relationLoaded('audits')
            ? $this->audits
            : $this->audits()->where('event', 'updated')->with('user')->orderBy('id')->get();

        $audit = $audits->last(fn (Audit $audit) => ($audit->new_values['state'] ?? null) === $this->state->value);

        if ($audit) {
            return [
                'by' => self::auditAuthorName($audit, $this->contact_name)
                    ?? ($this->state === AppointmentStateEnum::CANCELLED ? $this->contact_name : null),
                'at' => $audit->created_at,
            ];
        }

        return [
            'by' => $this->createdBy?->chatName()
                ?? ($this->source === AppointmentSourceEnum::WEBSITE ? $this->contact_name : null),
            'at' => $this->created_at,
        ];
    }

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class)->withTrashed();
    }

    public function visitor(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function zoomJoinUrl(): ?string
    {
        return data_get($this->data, 'zoom.join_url') ?: null;
    }

    public function googleMeetJoinUrl(): ?string
    {
        return data_get($this->data, 'google_meet.join_url') ?: null;
    }
}
