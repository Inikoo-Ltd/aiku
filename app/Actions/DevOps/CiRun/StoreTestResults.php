<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Oct 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\CiRun;

use App\Events\BroadcastCiRunUpdated;
use App\Models\DevOps\CiRun;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreTestResults
{
    use AsAction;

    public function handle(array $modelData): CiRun
    {
        $ciRun = CiRun::firstOrCreate(['github_run_id' => $modelData['run_id']], ['workflow' => 'Backend Tests']);
        $ciRun->update(['test_results' => Arr::except($modelData, ['run_id'])]);

        BroadcastCiRunUpdated::dispatch($ciRun);

        return $ciRun;
    }

    public function rules(): array
    {
        return [
            'run_id'           => ['required', 'integer', 'min:1'],
            'missing'          => ['sometimes', 'boolean'],
            'tests'            => ['sometimes', 'integer', 'min:0'],
            'assertions'       => ['sometimes', 'integer', 'min:0'],
            'failures'         => ['sometimes', 'integer', 'min:0'],
            'errors'           => ['sometimes', 'integer', 'min:0'],
            'skipped'          => ['sometimes', 'integer', 'min:0'],
            'seconds'          => ['sometimes', 'numeric', 'min:0'],
            'failed'           => ['sometimes', 'array', 'max:25'],
            'failed.*.test'    => ['required', 'string', 'max:500'],
            'failed.*.message' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->handle($request->validated());

        return ['ok' => true];
    }
}
