<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierMessage\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\SupplierMessage\AttachSupplierMessageAttachment;
use App\Actions\Procurement\SupplierMessage\Whatsapp\SendSupplierWhatsappMessage;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\Procurement\SupplierMessage\SupplierMessageChannelEnum;
use App\Enums\Procurement\SupplierMessage\SupplierMessageDirectionEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\SupplierMessage;
use App\Models\SysAdmin\Organisation;
use App\Services\Gmail\GmailClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;

class ShowSupplierMessage extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function asController(Organisation $organisation, SupplierMessage $supplierMessage, ActionRequest $request): SupplierMessage
    {
        $this->initialisation($organisation, $request);

        abort_unless($supplierMessage->organisation_id === $organisation->id || $organisation->type === OrganisationTypeEnum::AGENT, 404);

        return $supplierMessage;
    }

    /**
     * @return array{type: string, name: string, code: string, email: string|null, phone: string|null, key: string, route: array{name: string, parameters: array<int, string>}}
     */
    public static function counterpartSummary(OrgSupplier|OrgAgent|OrgPartner $counterpart, Organisation $organisation): array
    {
        return match (true) {
            $counterpart instanceof OrgSupplier => [
                'type'  => 'supplier',
                'name'  => $counterpart->supplier->name,
                'code'  => $counterpart->supplier->code,
                'email' => $counterpart->supplier->email,
                'phone' => $counterpart->supplier->phone,
                'key'   => 'supplier:'.$counterpart->id,
                'route' => ['name' => 'grp.org.procurement.org_suppliers.show', 'parameters' => [$organisation->slug, $counterpart->slug]],
            ],
            $counterpart instanceof OrgAgent => [
                'type'  => 'agent',
                'name'  => $counterpart->agent->name,
                'code'  => $counterpart->agent->code,
                'email' => $counterpart->agent->organisation?->email,
                'phone' => $counterpart->agent->organisation?->phone,
                'key'   => 'agent:'.$counterpart->id,
                'route' => ['name' => 'grp.org.procurement.org_agents.show', 'parameters' => [$organisation->slug, $counterpart->slug]],
            ],
            $counterpart instanceof OrgPartner => [
                'type'  => 'partner',
                'name'  => $counterpart->partner->name,
                'code'  => $counterpart->partner->code,
                'email' => $counterpart->partner->email,
                'phone' => $counterpart->partner->phone,
                'key'   => 'partner:'.$counterpart->id,
                'route' => ['name' => 'grp.org.procurement.org_partners.show', 'parameters' => [$organisation->slug, $counterpart->id]],
            ],
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function counterpartOptions(Organisation $organisation): array
    {
        $agents = OrgAgent::where('org_agents.organisation_id', $organisation->id)
            ->join('agents', 'agents.id', 'org_agents.agent_id')
            ->orderBy('agents.code')
            ->get(['org_agents.id', 'agents.code', 'agents.name'])
            ->map(fn ($option) => ['value' => 'agent:'.$option->id, 'label' => __('Agent').' · '.$option->code.' · '.$option->name]);

        $partners = OrgPartner::where('org_partners.organisation_id', $organisation->id)
            ->join('organisations', 'organisations.id', 'org_partners.partner_id')
            ->orderBy('organisations.code')
            ->get(['org_partners.id', 'organisations.code', 'organisations.name'])
            ->map(fn ($option) => ['value' => 'partner:'.$option->id, 'label' => __('Partner').' · '.$option->code.' · '.$option->name]);

        $suppliers = OrgSupplier::where('org_suppliers.organisation_id', $organisation->id)
            ->join('suppliers', 'suppliers.id', 'org_suppliers.supplier_id')
            ->orderBy('suppliers.code')
            ->get(['org_suppliers.id', 'suppliers.code', 'suppliers.name'])
            ->map(fn ($option) => ['value' => 'supplier:'.$option->id, 'label' => $option->code.' · '.$option->name]);

        return $agents->concat($partners)->concat($suppliers)->values()->all();
    }

    /**
     * @return Collection<int, SupplierMessage>
     */
    public function thread(SupplierMessage $supplierMessage): Collection
    {
        if ($supplierMessage->channel === SupplierMessageChannelEnum::WHATSAPP) {
            return SupplierMessage::where('organisation_id', $supplierMessage->organisation_id)
                ->where('channel', SupplierMessageChannelEnum::WHATSAPP)
                ->where('phone_number', $supplierMessage->phone_number)
                ->where('sent_at', '>=', now()->subDays(90))
                ->orderBy('sent_at')
                ->with(['dispatchedEmail', 'purchaseOrder', 'user'])
                ->get();
        }

        if (! $supplierMessage->gmail_thread_id) {
            return collect([$supplierMessage]);
        }

        return SupplierMessage::where('organisation_id', $supplierMessage->organisation_id)
            ->where('gmail_thread_id', $supplierMessage->gmail_thread_id)
            ->orderBy('sent_at')
            ->with(['dispatchedEmail', 'purchaseOrder', 'user'])
            ->get();
    }

    public function htmlResponse(SupplierMessage $supplierMessage, ActionRequest $request): Response
    {
        $thread      = $this->thread($supplierMessage);
        $counterpart = $supplierMessage->counterpart();
        $canEdit     = $request->user()->authTo("procurement.{$this->organisation->id}.edit");
        $lastEmail   = $thread->last();
        $mailbox     = Str::lower((string) Arr::get($this->organisation->settings, 'procurement.gmail.email'));
        $isWhatsapp  = $supplierMessage->channel === SupplierMessageChannelEnum::WHATSAPP;
        $targets     = $canEdit ? AttachSupplierMessageAttachment::targetOptions($supplierMessage) : [];
        $title       = $isWhatsapp
            ? __('WhatsApp with :name', ['name' => $counterpart ? self::counterpartSummary($counterpart, $this->organisation)['name'] : ($supplierMessage->from_name ?: '+'.$supplierMessage->phone_number)])
            : ($supplierMessage->subject ?: __('(no subject)'));

        return Inertia::render(
            'Procurement/SupplierMessage',
            [
                'breadcrumbs' => IndexSupplierMessages::make()->getBreadcrumbs(['organisation' => $this->organisation->slug]),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => $isWhatsapp ? ['fab', 'fa-whatsapp'] : ['fal', 'fa-envelope'],
                    'model' => $isWhatsapp ? __('WhatsApp') : __('Supplier email'),
                ],
                'supplier'    => $counterpart ? [
                    ...self::counterpartSummary($counterpart, $this->organisation),
                    'routed_by' => $supplierMessage->routed_by?->value,
                ] : null,
                'assign'      => $canEdit ? [
                    'route'   => [
                        'name'       => 'grp.org.procurement.supplier_messages.assign',
                        'parameters' => [$this->organisation->slug, $supplierMessage->id],
                    ],
                    'options' => self::counterpartOptions($this->organisation),
                ] : null,
                'attach'      => $canEdit ? [
                    'targets' => $targets,
                    'scopes'  => PurchaseOrderAttachmentScopeEnum::options(),
                ] : null,
                'reply'       => $isWhatsapp ? ($canEdit && SendSupplierWhatsappMessage::isConnected($this->organisation) ? [
                    'channel'     => 'whatsapp',
                    'route'       => ['name' => 'grp.org.procurement.supplier_messages.whatsapp', 'parameters' => [$this->organisation->slug]],
                    'phone'       => '+'.$supplierMessage->phone_number,
                    'counterpart' => $counterpart ? self::counterpartSummary($counterpart, $this->organisation)['key'] : null,
                    'window_open' => SendSupplierWhatsappMessage::isWindowOpen($this->organisation, (string) $supplierMessage->phone_number),
                    'has_template' => filled(Arr::get($this->organisation->settings, 'procurement.whatsapp.message_template')),
                    'template'     => SendSupplierWhatsappMessage::messageTemplate($this->organisation),
                ] : null) : ($canEdit && GmailClient::procurementMailbox($this->organisation) ? [
                    'channel' => 'email',
                    'route'   => [
                        'name'       => 'grp.org.procurement.supplier_messages.reply',
                        'parameters' => [$this->organisation->slug, $lastEmail->id],
                    ],
                    'to'      => $lastEmail->direction === SupplierMessageDirectionEnum::INBOUND
                        ? [$lastEmail->from_address]
                        : collect($lastEmail->to)->pluck('address')->all(),
                    'cc'      => collect($lastEmail->cc)->pluck('address')
                        ->reject(fn ($address) => Str::lower($address) === $mailbox)
                        ->values()->all(),
                    'subject' => $lastEmail->subject,
                ] : null),
                'messages'    => $thread->map(fn (SupplierMessage $email) => [
                    'id'          => $email->id,
                    'is_outbound' => $email->direction === SupplierMessageDirectionEnum::OUTBOUND,
                    'from'        => ['name' => $email->from_name, 'address' => $email->from_address],
                    'to'          => $email->to,
                    'cc'          => $email->cc,
                    'sent_at'     => $email->sent_at,
                    'body_html'   => $email->body_html,
                    'body_text'   => $email->body_text,
                    'channel'     => $email->channel->value,
                    'author'      => $email->user?->contact_name,
                    'delivery'    => match (true) {
                        (bool) $email->dispatchedEmail => [
                            'state'  => $email->dispatchedEmail->state->value,
                            'reads'  => $email->dispatchedEmail->number_reads,
                            'clicks' => $email->dispatchedEmail->number_clicks,
                        ],
                        filled($email->delivery_state) => ['state' => $email->delivery_state, 'reads' => 0, 'clicks' => 0],
                        default => null,
                    },
                    'purchase_orders' => $email->linkedPurchaseOrders()->map(fn (PurchaseOrder $purchaseOrder) => [
                        'reference' => $purchaseOrder->reference,
                        'route'     => [
                            'name'       => 'grp.org.procurement.purchase_orders.show',
                            'parameters' => [$this->organisation->slug, $purchaseOrder->slug],
                        ],
                    ])->values()->all(),
                    'attachments' => collect($email->attachments)->map(fn (array $attachment, int $index) => [
                        'name'      => $attachment['name'],
                        'size'      => $attachment['size'],
                        'mime_type' => $attachment['mime_type'],
                        'url'       => route('grp.org.procurement.supplier_messages.attachment', [$this->organisation->slug, $email->id, $index]),
                        'attached_to'      => AttachSupplierMessageAttachment::attachedTo($this->organisation, $attachment['attached_media_id'] ?? null),
                        'suggested_target' => $canEdit ? AttachSupplierMessageAttachment::suggestedTarget($email, $attachment['name'], $targets) : null,
                        'suggested_scope'  => PurchaseOrderAttachmentScopeEnum::guessFromFileName($attachment['name'])->value,
                        'attach_route'     => [
                            'name'       => 'grp.org.procurement.supplier_messages.attachment.attach',
                            'parameters' => [$this->organisation->slug, $email->id, $index],
                        ],
                    ])->values()->all(),
                ])->values()->all(),
            ]
        );
    }
}
