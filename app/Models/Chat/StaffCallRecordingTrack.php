<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One speaker's audio track in a recording, written by one LiveKit track egress to the private R2 bucket.
 *
 * @property int $id
 * @property int $staff_call_recording_id
 * @property string $track_sid
 * @property string|null $egress_id
 * @property string $identity
 * @property string $speaker
 * @property string $path
 * @property string $status
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property-read StaffCallRecording $recording
 */
class StaffCallRecordingTrack extends Model
{
    public const string STATUS_STARTING = 'starting';
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_STOPPING = 'stopping';
    public const string STATUS_ENDED = 'ended';
    public const string STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at'   => 'datetime',
        ];
    }

    public function recording(): BelongsTo
    {
        return $this->belongsTo(StaffCallRecording::class, 'staff_call_recording_id');
    }
}
