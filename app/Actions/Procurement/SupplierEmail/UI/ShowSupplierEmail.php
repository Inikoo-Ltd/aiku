<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierEmail\UI;

use App\Actions\OrgAction;
use App\Enums\Procurement\SupplierEmail\SupplierEmailDirectionEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\SupplierEmail;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSupplierEmail extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function asController(Organisation $organisation, SupplierEmail $supplierEmail, ActionRequest $request): SupplierEmail
    {
        $this->initialisation($organisation, $request);

        abort_unless($supplierEmail->organisation_id === $organisation->id, 404);

        return $supplierEmail;
    }

    /**
     * @return Collection<int, SupplierEmail>
     */
    public function thread(SupplierEmail $supplierEmail): Collection
    {
        if (! $supplierEmail->gmail_thread_id) {
            return collect([$supplierEmail]);
        }

        return SupplierEmail::where('organisation_id', $supplierEmail->organisation_id)
            ->where('gmail_thread_id', $supplierEmail->gmail_thread_id)
            ->orderBy('sent_at')
            ->with(['dispatchedEmail', 'purchaseOrder'])
            ->get();
    }

    public function htmlResponse(SupplierEmail $supplierEmail, ActionRequest $request): Response
    {
        $orgSupplier = $supplierEmail->orgSupplier;
        $canEdit     = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return Inertia::render(
            'Procurement/SupplierEmail',
            [
                'breadcrumbs' => IndexSupplierEmails::make()->getBreadcrumbs(['organisation' => $this->organisation->slug]),
                'title'       => $supplierEmail->subject ?: __('Email'),
                'pageHead'    => [
                    'title' => $supplierEmail->subject ?: __('(no subject)'),
                    'icon'  => ['fal', 'fa-envelope'],
                    'model' => __('Supplier email'),
                ],
                'supplier'    => $orgSupplier ? [
                    'name'      => $orgSupplier->supplier->name,
                    'code'      => $orgSupplier->supplier->code,
                    'routed_by' => $supplierEmail->routed_by?->value,
                    'route'     => [
                        'name'       => 'grp.org.procurement.org_suppliers.show',
                        'parameters' => [$this->organisation->slug, $orgSupplier->slug],
                    ],
                ] : null,
                'assign'      => $canEdit ? [
                    'route'   => [
                        'name'       => 'grp.org.procurement.supplier_emails.assign',
                        'parameters' => [$this->organisation->slug, $supplierEmail->id],
                    ],
                    'options' => OrgSupplier::where('org_suppliers.organisation_id', $this->organisation->id)
                        ->join('suppliers', 'suppliers.id', 'org_suppliers.supplier_id')
                        ->orderBy('suppliers.code')
                        ->get(['org_suppliers.id', 'suppliers.code', 'suppliers.name'])
                        ->map(fn ($option) => ['value' => $option->id, 'label' => $option->code.' · '.$option->name])
                        ->all(),
                ] : null,
                'messages'    => $this->thread($supplierEmail)->map(fn (SupplierEmail $email) => [
                    'id'          => $email->id,
                    'is_outbound' => $email->direction === SupplierEmailDirectionEnum::OUTBOUND,
                    'from'        => ['name' => $email->from_name, 'address' => $email->from_address],
                    'to'          => $email->to,
                    'cc'          => $email->cc,
                    'sent_at'     => $email->sent_at,
                    'body_html'   => $email->body_html,
                    'body_text'   => $email->body_text,
                    'delivery'    => $email->dispatchedEmail ? [
                        'state'  => $email->dispatchedEmail->state->value,
                        'reads'  => $email->dispatchedEmail->number_reads,
                        'clicks' => $email->dispatchedEmail->number_clicks,
                    ] : null,
                    'purchase_order' => $email->purchaseOrder ? [
                        'reference' => $email->purchaseOrder->reference,
                        'route'     => [
                            'name'       => 'grp.org.procurement.purchase_orders.show',
                            'parameters' => [$this->organisation->slug, $email->purchaseOrder->slug],
                        ],
                    ] : null,
                    'attachments' => collect($email->attachments)->map(fn (array $attachment, int $index) => [
                        'name'      => $attachment['name'],
                        'size'      => $attachment['size'],
                        'mime_type' => $attachment['mime_type'],
                        'url'       => route('grp.org.procurement.supplier_emails.attachment', [$this->organisation->slug, $email->id, $index]),
                    ])->values()->all(),
                ])->values()->all(),
            ]
        );
    }
}
