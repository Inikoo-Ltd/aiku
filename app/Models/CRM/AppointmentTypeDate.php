<?php

namespace App\Models\CRM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $appointment_type_id
 * @property \Illuminate\Support\Carbon $date
 * @property array<int, array{from: string, to: string}> $hours
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read AppointmentType $appointmentType
 * @mixin \Eloquent
 */
class AppointmentTypeDate extends Model
{
    protected $casts = [
        'date'  => 'date:Y-m-d',
        'hours' => 'array',
    ];

    protected $attributes = [
        'hours' => '[]',
    ];

    protected $guarded = [];

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }
}
