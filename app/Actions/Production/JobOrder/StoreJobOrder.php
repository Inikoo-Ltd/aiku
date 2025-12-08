<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 12:09:22 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrder\Hydrators\JobOrderHydrateUniversalSearch;
use App\Actions\Production\Production\Hydrators\ProductionHydrateJobOrders;
use App\Http\Resources\Production\JobOrderResource;
use App\Models\CRM\WebUser;
use App\Models\Production\JobOrder;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\Response;

class StoreJobOrder extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Production $production, array $modelData): JobOrder
    {
        data_set($modelData, 'group_id', $production->group_id);
        data_set($modelData, 'organisation_id', $production->organisation_id);
        data_set($modelData, 'in_process_at', now());

        if (! Arr::get($modelData, 'reference')) {
            data_set(
                $modelData,
                'reference',
                Str::random(10) // TODO: make a reference generator for Job Order
            );
        }

        /** @var JobOrder $jobOrder */
        $jobOrder = $production->jobOrders()->create($modelData);
        JobOrderHydrateUniversalSearch::dispatch($jobOrder);
        ProductionHydrateJobOrders::dispatch($jobOrder->production);

        return $jobOrder;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("productions-view.{$this->organisation->id}");
    }

    public function rules(): array
    {
        $rules = [];

        if (! request()->user() instanceof WebUser) {
            $rules = [
                'public_notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
                'internal_notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
            ];
        }

        return [
            'customer_notes' => ['sometimes', 'nullable', 'string'],
            ...$rules,
        ];
    }

    public function action(Production $production, array $modelData): JobOrder
    {
        $this->asAction = true;
        $this->initialisation($production->organisation, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): JobOrder
    {
        $this->initialisation($organisation, $request);

        return $this->handle($production, $this->validatedData);
    }

    public function jsonResponse(JobOrder $jobOrder): JobOrderResource
    {
        return JobOrderResource::make($jobOrder);

    }

    public function htmlResponse(JobOrder $jobOrder, ActionRequest $request): Response
    {
        $routeName = $request->route()->getName();

        return match ($routeName) {
            'grp.models.production.job-order.store' => Inertia::location(route('grp.org.productions.show.job-order.show', [
                'organisation' => $jobOrder->organisation->slug,
            ])),
            default => Inertia::location(route('retina.fulfilment.storage.pallet_deliveries.show', [
                'jobOrder' => $jobOrder->slug,
            ]))
        };
    }

    public string $commandSignature = 'job_orders:create {production}';

    public function asCommand(Command $command): int
    {
        $this->asAction = true;

        try {
            $production = Production::where('slug', $command->argument('production'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $jobOrder = $this->handle($production, modelData: $this->validatedData);

        $command->info("Job Order $jobOrder->reference created successfully 🎉");

        return 0;
    }
}
