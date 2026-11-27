<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call;

use App\Models\Chat\StaffCall;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class PingStaffCall
{
    use AsAction;

    /**
     * A browser in a call says so every StaffCall::PING_SECONDS; one that stops (closed, crashed, lost) is taken out of the
     * call by CloseStaleStaffCalls, which every ping runs for its own call: whoever is still in closes a call the other side
     * abandoned without waiting for the minute schedule. Someone no longer allowed in the conversation is taken out at once.
     */
    public const array QUALITY_METRICS = [
        'rtt_ms'              => 10_000,
        'loss_pct'            => 100,
        'jitter_ms'           => 10_000,
        'sent_audio_kbps'     => 10_000,
        'sent_video_kbps'     => 100_000,
        'received_audio_kbps' => 10_000,
        'received_video_kbps' => 100_000,
    ];

    public const array QUALITY_PATHS = ['udp', 'tcp', 'relay'];

    public const array QUALITY_CONNECTIONS = ['excellent', 'good', 'poor', 'lost', 'unknown'];

    /**
     * @param  array<string, mixed>|null  $sample  what the browser measured since its last ping
     */
    public function handle(StaffCall $call, User $user, ?array $sample = null): StaffCall
    {
        if (!$call->mayBeJoinedBy($user)) {
            $call = LeaveStaffCall::run($call, $user);
            RemoveFromLiveKitRoom::dispatch($call, $user->id);

            return $call;
        }

        $participant = $call->users()->newPivotStatement()
            ->where('staff_call_id', $call->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at');
        $update = ['last_seen_at' => now()];
        if ($sample) {
            $update['quality'] = json_encode(self::addQualitySample(json_decode((clone $participant)->value('quality') ?? 'null', true), $sample));
        }
        $participant->update($update);

        CloseStaleStaffCalls::make()->handle($call->conversation, $call->conversation ? null : $call);

        return $call->refresh();
    }

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCall::isEnabled(), 404);

        return $request->route('staffCall')->users()->where('users.id', $request->user()->id)->exists();
    }

    /**
     * One sample per ping: for each metric its sum, count and worst value, so averages are sum / n; which network path
     * was used how often (relay or tcp means the direct UDP path was blocked); and how many samples LiveKit rated poor or lost.
     *
     * @param  array<string, mixed>|null  $quality
     * @param  array<string, mixed>  $sample
     * @return array<string, mixed>
     */
    public static function addQualitySample(?array $quality, array $sample): array
    {
        $quality ??= ['samples' => 0, 'poor_samples' => 0, 'paths' => []];
        $quality['samples']++;
        if (in_array($sample['connection'] ?? null, ['poor', 'lost'], true)) {
            $quality['poor_samples']++;
        }
        if (isset($sample['path'])) {
            $quality['paths'][$sample['path']] = ($quality['paths'][$sample['path']] ?? 0) + 1;
        }
        foreach (array_keys(self::QUALITY_METRICS) as $metric) {
            if (!isset($sample[$metric])) {
                continue;
            }
            $value              = round((float) $sample[$metric], 1);
            $totals             = $quality[$metric] ?? ['sum' => 0, 'n' => 0, 'max' => $value];
            $quality[$metric]   = ['sum' => round($totals['sum'] + $value, 1), 'n' => $totals['n'] + 1, 'max' => max($totals['max'], $value)];
        }

        return $quality;
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(StaffCall $staffCall, ActionRequest $request): array
    {
        return ['call' => $this->handle($staffCall, $request->user(), self::qualitySampleFrom($request->input('quality')) ?: null)->toBroadcast()];
    }

    /**
     * The ping is the heartbeat that keeps a person in the call, so a bad measurement is dropped field by field and never
     * fails the request: a refused ping would leave last_seen_at behind and sweep the person out of a live call.
     *
     * @return array<string, mixed>
     */
    public static function qualitySampleFrom(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }
        $sample = [];
        if (in_array($input['path'] ?? null, self::QUALITY_PATHS, true)) {
            $sample['path'] = $input['path'];
        }
        if (in_array($input['connection'] ?? null, self::QUALITY_CONNECTIONS, true)) {
            $sample['connection'] = $input['connection'];
        }
        foreach (self::QUALITY_METRICS as $metric => $max) {
            $value = $input[$metric] ?? null;
            if (is_numeric($value) && $value >= 0 && $value <= $max) {
                $sample[$metric] = (float) $value;
            }
        }

        return $sample;
    }
}
