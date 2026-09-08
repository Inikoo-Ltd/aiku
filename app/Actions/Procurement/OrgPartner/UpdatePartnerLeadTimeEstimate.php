<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 1 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Actions\OrgAction;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class UpdatePartnerLeadTimeEstimate extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("org-supervisor.{$this->organisation->id}.procurement");
    }

    public function handle(OrgPartner $orgPartner, array $modelData): OrgPartner
    {
        abort_if(
            GetPartnerLeadTime::run($orgPartner)['source'] === 'measured',
            422,
            'Lead time is measured from delivery history and cannot be overridden'
        );

        $data = $orgPartner->data;
        data_set($data, 'shopping.lead_time_days', (int) $modelData['lead_time_days']);
        $orgPartner->update(['data' => $data]);

        return $orgPartner;
    }

    public function rules(): array
    {
        return [
            'lead_time_days' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): OrgPartner
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function action(OrgPartner $orgPartner, array $modelData): OrgPartner
    {
        $this->asAction = true;
        $this->initialisation($orgPartner->organisation, $modelData);

        return $this->handle($orgPartner, $this->validatedData);
    }
}
