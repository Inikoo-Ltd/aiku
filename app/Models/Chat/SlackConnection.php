<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Actions\Chat\Staff\Slack\Write\SlackWriteAccess;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A person's own permission to write in Slack from aiku, one per aiku user and Slack workspace. The token never leaves the server.
 *
 * @property int $id
 * @property int $user_id
 * @property string $slack_user_id
 * @property string $team_id
 * @property string|null $token
 * @property string|null $scopes
 * @property \Illuminate\Support\Carbon|null $connected_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property bool $needs_reconnect
 * @property \Illuminate\Support\Carbon|null $private_consented_at
 * @property string|null $private_consent_version
 * @property string|null $private_terms_version
 */
class SlackConnection extends Model
{
    protected $guarded = [];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'token'           => 'encrypted',
            'connected_at'    => 'datetime',
            'revoked_at'      => 'datetime',
            'needs_reconnect'      => 'boolean',
            'private_consented_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The main workspace's connection (INI-540): private opt-in, DMs and every user-keyed lookup that is not about
     * writing in another workspace's channel go through it. A person has at most one connection per workspace.
     */
    public function scopeMain(Builder $query): Builder
    {
        if ($mainTeamId = config('services.slack.team_id')) {
            return $query->where('team_id', $mainTeamId);
        }
        $otherTeams = array_map(fn ($workspace) => $workspace->teamId, \App\Actions\Chat\Staff\Slack\SlackWorkspace::others());

        return $otherTeams === [] ? $query : $query->whereNotIn('team_id', $otherTeams);
    }

    public function scopeForWorkspace(Builder $query, \App\Actions\Chat\Staff\Slack\SlackWorkspace $workspace): Builder
    {
        return $workspace->isMain() ? $query->main() : $query->where('team_id', $workspace->teamId);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNotNull('token')->whereNull('revoked_at')->where('needs_reconnect', false);
    }

    /**
     * @return list<string>
     */
    public function missingScopes(): array
    {
        return array_values(array_diff(SlackWriteAccess::USER_SCOPES, array_map('trim', explode(',', (string) $this->scopes))));
    }

    public function needsReconnection(): bool
    {
        return $this->needs_reconnect || ($this->getRawOriginal('token') !== null && $this->revoked_at === null && $this->missingScopes() !== []);
    }

    public function isLive(): bool
    {
        return $this->getRawOriginal('token') !== null && $this->revoked_at === null && !$this->needs_reconnect;
    }

    /**
     * @return list<string>
     */
    public function grantedScopes(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->scopes))));
    }

    /**
     * Private conversations are on only with the person's recorded yes and a live token that still holds every private scope.
     * Slack adds scopes up and never drops them, so the scopes alone never mean yes.
     */
    public function hasPrivateConsent(): bool
    {
        return $this->private_consented_at !== null && $this->isLive()
            && array_diff(SlackWriteAccess::PRIVATE_SCOPES, $this->grantedScopes()) === [];
    }

    public function grantPrivateConsent(string $consentVersion, string $termsVersion): void
    {
        $this->forceFill([
            'private_consented_at'    => now(),
            'private_consent_version' => $consentVersion,
            'private_terms_version'   => $termsVersion,
        ])->save();
        $this->logPrivateConsent('granted');
    }

    public function endPrivateConsent(string $event): void
    {
        if ($this->private_consented_at === null) {
            return;
        }
        $this->logPrivateConsent($event);
        $this->forceFill(['private_consented_at' => null, 'private_consent_version' => null, 'private_terms_version' => null])->save();
    }

    private function logPrivateConsent(string $event): void
    {
        DB::table('slack_private_consents')->insert([
            'user_id'         => $this->user_id,
            'slack_user_id'   => $this->slack_user_id,
            'event'           => $event,
            'consent_version' => $this->private_consent_version,
            'terms_version'   => $this->private_terms_version,
            'scopes'          => $this->scopes,
            'created_at'      => now(),
        ]);
    }
}
