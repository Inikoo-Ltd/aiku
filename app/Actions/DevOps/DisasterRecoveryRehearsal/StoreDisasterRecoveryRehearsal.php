<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\DisasterRecoveryRehearsal;

use App\Models\DevOps\DisasterRecoveryRehearsal;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreDisasterRecoveryRehearsal
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'rehearsed_on' => ['required', 'date', 'before_or_equal:today'],
            'next_due_on'  => ['required', 'date', 'after:rehearsed_on'],
            'notes'        => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @param array{rehearsed_on: string, next_due_on: string, notes?: string|null} $modelData */
    public function handle(array $modelData, ?User $user = null): DisasterRecoveryRehearsal
    {
        $rehearsal = DisasterRecoveryRehearsal::create([...$modelData, 'user_id' => $user?->id]);
        Cache::forget(DisasterRecoveryRehearsal::ISSUE_CACHE_KEY);

        return $rehearsal;
    }

    public function asController(ActionRequest $request): DisasterRecoveryRehearsal
    {
        return $this->handle($request->validated(), $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
