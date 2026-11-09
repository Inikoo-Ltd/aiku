<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-09 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\DepositRequest;

use App\Actions\OrgAction;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Models\SupplyChain\DepositRequest;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent sends the money request email again to the client's payers and copied users, and remembers when.
 * One reminder an hour at most, so a double click or an impatient agent does not flood the client.
 */
class RemindAgentMoneyRequest extends OrgAction
{
    public const int MINUTES_BETWEEN_REMINDERS = 0;  //  TODO: change to 60 (means 60 minutes) after testing phase is finish

    private DepositRequest $depositRequest;

    public static function nextReminderAt(DepositRequest $depositRequest): ?Carbon
    {
        $nextReminderAt = $depositRequest->last_reminded_at?->copy()->addMinutes(self::MINUTES_BETWEEN_REMINDERS);

        return $nextReminderAt?->isFuture() ? $nextReminderAt : null;
    }

    /**
     * @throws ValidationException
     */
    public function handle(DepositRequest $depositRequest, ?int $userId = null): DepositRequest
    {
        if ($depositRequest->state !== DepositRequestStateEnum::REQUESTED) {
            throw ValidationException::withMessages(['request' => __('Only an open money request can be reminded.')]);
        }

        $claimed = DepositRequest::whereKey($depositRequest->id)
            ->where(fn ($query) => $query->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<=', now()->subMinutes(self::MINUTES_BETWEEN_REMINDERS)))
            ->update([
                'last_reminded_at' => now(),
                'last_reminded_by' => $userId,
                'number_reminders' => DB::raw('number_reminders + 1'),
            ]);

        if (!$claimed) {
            throw ValidationException::withMessages(['request' => __('A reminder was sent less than an hour ago. Try again in :minutes minutes.', [
                'minutes' => max(1, (int) ceil(now()->diffInMinutes(self::nextReminderAt($depositRequest->refresh()) ?? now()))),
            ])]);
        }

        NotifyMoneyRequestCreated::run($depositRequest->refresh(), true);

        return $depositRequest;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->depositRequest->agent->organisation_id}.edit");
    }

    /**
     * @throws ValidationException
     */
    public function asController(DepositRequest $depositRequest, ActionRequest $request): DepositRequest
    {
        $this->depositRequest = $depositRequest;
        $this->initialisation($depositRequest->agent->organisation, $request);

        return $this->handle($depositRequest, $request->user()->id);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
