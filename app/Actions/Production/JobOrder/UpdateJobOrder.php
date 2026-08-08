<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Jul 2025 18:49:42 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\CRM\WebUser;
use App\Models\Production\JobOrder;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class UpdateJobOrder extends OrgAction
{
    use WithActionUpdate;


    private JobOrder $jobOrder;

    public function handle(JobOrder $jobOrder, array $modelData): JobOrder
    {
        return $this->update($jobOrder, $modelData);
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

        if (!request()->user() instanceof WebUser) {
            $rules = [
                'public_notes'  => ['sometimes','nullable','string','max:4000'],
                'internal_notes' => ['sometimes','nullable','string','max:4000'],
            ];
        }

        return [
            'customer_notes'  => ['sometimes','nullable','string','max:4000'],
            'reference'       => ['sometimes', 'nullable', 'string', 'max:64'],
            'state'           => ['sometimes', Rule::enum(JobOrderStateEnum::class)],
            'date'            => ['sometimes', 'nullable', 'date'],
            'in_process_at'   => ['sometimes', 'nullable', 'date'],
            'submitted_at'    => ['sometimes', 'nullable', 'date'],
            'confirmed_at'    => ['sometimes', 'nullable', 'date'],
            'received_at'     => ['sometimes', 'nullable', 'date'],
            'not_received_at' => ['sometimes', 'nullable', 'date'],
            ...$rules
        ];
    }

    public function asController(JobOrder $jobOrder, ActionRequest $request): JobOrder
    {
        $this->jobOrder = $jobOrder;
        $this->initialisation($jobOrder->organisation, $request);
        return $this->handle($jobOrder, $this->validatedData);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function action(Organisation $organisation, JobOrder $jobOrder, $modelData): JobOrder
    {
        $this->asAction = true;
        $this->jobOrder = $jobOrder;
        $this->initialisation($jobOrder->organisation, $modelData);
        $this->setRawAttributes($modelData);

        return $this->handle($jobOrder, $this->validatedData);
    }

    public function htmlResponse(JobOrder $jobOrder, ActionRequest $request): Response
    {
        $routeName = $request->route()->getName();

        return match ($routeName) {
            'grp.models.production.job-order.update' => Inertia::location(route('grp.org.productions.show.job-order.show', [
                'organisation'           => $jobOrder->organisation->slug,
            ])),
        };
    }

    public string $commandSignature = 'job-orders:update {job-order}';

    public function asCommand(Command $command): int
    {
        $this->asAction = true;


        try {
            $jobOrder = JobOrder::where('slug', $command->argument('job-order'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());
            return 1;
        }

        $jobOrder = $this->handle($jobOrder, modelData: $this->validatedData);

        $command->info("Job Order $jobOrder->reference updated successfully 🎉");

        return 0;
    }
}
