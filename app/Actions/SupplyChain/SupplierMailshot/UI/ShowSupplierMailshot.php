<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierMailshot\UI;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierMailshot\PrepareSupplierMailshotRecipients;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Comms\Mailshot;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * One email to suppliers: before it goes, who it will reach and the buttons to write and send it; after, every
 * supplier email it went to and whether it was read.
 */
class ShowSupplierMailshot extends OrgAction
{
    use WithSupplyChainAuthorisation;
    use WithSupplierMailshotsSubNavigation;

    public function asController(Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        abort_if($mailshot->type !== MailshotTypeEnum::SUPPLIER || $mailshot->group_id !== group()->id, 404);
        $this->initialisationFromGroup(group(), $request);

        return $mailshot;
    }

    public function recipients(Mailshot $mailshot, string $prefix): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($query) => $query->whereStartWith('supplier_portal_contacts.email', $value)->orWhereStartWith('suppliers.code', $value)->orWhereAnyWordStartWith('suppliers.name', $value));
        });

        InertiaTable::updateQueryBuilderParameters($prefix);

        return QueryBuilder::for($mailshot->recipients()->getQuery())
            ->join('dispatched_emails', 'dispatched_emails.id', 'mailshot_recipients.dispatched_email_id')
            ->join('supplier_portal_contacts', 'supplier_portal_contacts.id', 'mailshot_recipients.recipient_id')
            ->join('suppliers', 'suppliers.id', 'supplier_portal_contacts.supplier_id')
            ->where('mailshot_recipients.recipient_type', 'SupplierPortalContact')
            ->defaultSort('-first_read_at')
            ->select([
                'mailshot_recipients.id',
                'supplier_portal_contacts.email',
                'suppliers.code as supplier_code',
                'suppliers.name as supplier_name',
                'suppliers.slug as supplier_slug',
                'dispatched_emails.state',
                'dispatched_emails.first_read_at',
                'dispatched_emails.number_reads',
                'dispatched_emails.number_clicks',
            ])
            ->allowedSorts([
                'email',
                'supplier_code',
                'number_clicks',
                AllowedSort::callback('first_read_at', fn ($query, bool $descending) => $query->orderByRaw('dispatched_emails.first_read_at '.($descending ? 'desc' : 'asc').' nulls last')),
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function htmlResponse(Mailshot $mailshot): Response
    {
        $mailshot->loadMissing(['stats', 'shop']);
        $isDraft   = in_array($mailshot->state, [MailshotStateEnum::IN_PROCESS, MailshotStateEnum::READY]);
        $countries = DB::table('countries')->whereIn('id', data_get($mailshot->recipients_recipe, 'countries', []))->orderBy('name')->pluck('name');

        return Inertia::render('SupplyChain/SupplierMailshot', [
            'title'       => $mailshot->subject,
            'breadcrumbs' => $this->getBreadcrumbsFor($mailshot),
            'pageHead'    => [
                'title'         => $mailshot->subject,
                'model'         => __('Email to suppliers'),
                'icon'          => ['icon' => ['fal', 'fa-mail-bulk'], 'title' => __('Email to suppliers')],
                'subNavigation' => $this->getSuppliersSubNavigation(),
            ],
            'summary'     => [
                'state'       => $mailshot->state->value,
                'state_label' => MailshotStateEnum::labels()[$mailshot->state->value] ?? $mailshot->state->value,
                'sent_as'     => $mailshot->shop?->name,
                'countries'   => $countries->implode(', '),
                'is_draft'    => $isDraft,
                'is_published' => (bool) $mailshot->email?->liveSnapshot?->compiled_layout,
                'will_reach'  => $isDraft ? PrepareSupplierMailshotRecipients::make()->audienceQuery($mailshot)->count() : null,
                'sent_at'     => $mailshot->sent_at,
                'emails'      => (int) $mailshot->stats?->number_dispatched_emails,
                'read'        => (int) $mailshot->stats?->number_dispatched_emails_state_opened + (int) $mailshot->stats?->number_dispatched_emails_state_clicked,
                'clicked'     => (int) $mailshot->stats?->number_dispatched_emails_state_clicked,
                'unsubscribed' => (int) $mailshot->stats?->number_provoked_unsubscribe,
            ],
            'can_send'    => $this->canEdit,
            'routes'      => [
                'workshop' => ['name' => 'grp.supply-chain.supplier_mailshots.workshop', 'parameters' => [$mailshot->slug]],
                'send'     => ['name' => 'grp.models.supplier_mailshot.send', 'parameters' => [$mailshot->id]],
            ],
            'recipients'  => $isDraft ? null : $this->recipients($mailshot, 'recipients')->through(fn ($recipient) => [
                'id'            => $recipient->id,
                'email'         => $recipient->email,
                'supplier_code' => $recipient->supplier_code,
                'supplier_name' => $recipient->supplier_name,
                'supplier_slug' => $recipient->supplier_slug,
                'state'         => $recipient->state,
                'first_read_at' => $recipient->first_read_at,
                'number_reads'  => (int) $recipient->number_reads,
                'number_clicks' => (int) $recipient->number_clicks,
            ]),
        ])->table($this->recipientsTableStructure('recipients'));
    }

    public function recipientsTableStructure(string $prefix): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            $table->name($prefix)->pageName($prefix.'Page')
                ->withGlobalSearch()
                ->withLabelRecord([__('email'), __('emails')])
                ->withEmptyState(['title' => __('Nothing sent yet')])
                ->column(key: 'email', label: __('Email'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'supplier_code', label: __('Supplier'), canBeHidden: false, sortable: true)
                ->column(key: 'state', label: __('Status'), canBeHidden: false)
                ->column(key: 'first_read_at', label: __('Read'), canBeHidden: false, sortable: true)
                ->column(key: 'number_clicks', label: __('Clicks'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('-first_read_at');
        };
    }

    public function getBreadcrumbsFor(Mailshot $mailshot): array
    {
        return array_merge(IndexSupplierMailshots::make()->getBreadcrumbs(), [
            ['type' => 'simple', 'simple' => ['label' => $mailshot->subject, 'route' => ['name' => 'grp.supply-chain.supplier_mailshots.show', 'parameters' => [$mailshot->slug]]]],
        ]);
    }
}
