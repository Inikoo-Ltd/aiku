<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 12:09:22 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Actions\Production\Production\Hydrators\ProductionHydrateJobOrders;
use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\OrgAction;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Http\Resources\Production\JobOrderResource;
use App\Models\CRM\WebUser;
use App\Models\Production\JobOrder;
use App\Models\Production\Production;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
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
        data_set($modelData, 'in_process_at', now(), overwrite: false);
        data_set($modelData, 'state', JobOrderStateEnum::IN_PROCESS, overwrite: false);

        if (!Arr::get($modelData, 'reference')) {
            $organisation = $production->organisation;
            $organisation->serialReferences()->firstOrCreate(
                ['model' => SerialReferenceModelEnum::JOB_ORDER],
                [
                    'organisation_id' => $organisation->id,
                    'format'          => 'JO'.$organisation->slug.'-%04d',
                ]
            );
            data_set(
                $modelData,
                'reference',
                GetSerialReference::run(
                    container: $organisation,
                    modelType: SerialReferenceModelEnum::JOB_ORDER
                )
            );
        }

        /** @var JobOrder $jobOrder */
        $jobOrder = $production->jobOrders()->create($modelData);
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

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }


    public function rules(): array
    {
        $rules = [];

        if (!request()->user() instanceof WebUser) {
            $rules = [
                'public_notes'  => ['sometimes','nullable','string','max:4000'],
                'internal_notes' => ['sometimes','nullable','string','max:4000'],
            ];
        }

        return [
            'customer_notes'  => ['sometimes','nullable','string'],
            'reference'       => ['sometimes', 'nullable', 'string', 'max:64'],
            'state'           => ['sometimes', Rule::enum(JobOrderStateEnum::class)],
            'date'            => ['sometimes', 'nullable', 'date'],
            'in_process_at'   => ['sometimes', 'nullable', 'date'],
            'submitted_at'    => ['sometimes', 'nullable', 'date'],
            'confirmed_at'    => ['sometimes', 'nullable', 'date'],
            'received_at'     => ['sometimes', 'nullable', 'date'],
            'not_received_at' => ['sometimes', 'nullable', 'date'],
            'created_at'      => ['sometimes', 'nullable', 'date'],
            'source_id'       => ['sometimes', 'nullable', 'string'],
            ...$rules
        ];
    }

    public function action(Production $production, array $modelData): JobOrder
    {
        $this->asAction = true;
        $this->initialisation($production->organisation, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function asController(Production $production, ActionRequest $request): JobOrder
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }

    public function jsonResponse(JobOrder $jobOrder): JobOrderResource
    {
        return JobOrderResource::make($jobOrder) ;

    }

    public function htmlResponse(JobOrder $jobOrder): Response
    {
        return Inertia::location(route('grp.org.productions.show.operations.job-orders.show', [
            'organisation' => $jobOrder->organisation->slug,
            'production'   => $jobOrder->production->slug,
            'jobOrder'     => $jobOrder->slug,
        ]));
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
