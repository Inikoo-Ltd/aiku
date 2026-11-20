<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-20 09:31
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Enums\UI\NotesEnum;
use App\Models\Procurement\PurchaseOrder;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The notes of a purchase order as one side sees them, labelled with the name of the organisation that placed the
 * order: anyone in its procurement team writes its note, not only the order's buyer. Each note has one reader besides
 * its writer. Through an agent: the organisation writes to the agent, the agent writes to the organisation and to the
 * supplier, and the supplier writes to the agent. Without an agent the organisation and the supplier write to each
 * other on the organisation and supplier notes.
 */
class GetPurchaseOrderNotes
{
    use AsObject;

    public const string VIEWER_ORGANISATION = 'organisation';

    public const string VIEWER_AGENT = 'agent';

    public const string VIEWER_SUPPLIER = 'supplier';

    /**
     * @return array{note_list: list<array<string, mixed>>}
     */
    public function handle(PurchaseOrder $purchaseOrder, string $viewer, bool $canEdit): array
    {
        $isAgentOrder     = $purchaseOrder->isAgentOrder();
        $organisationName = $purchaseOrder->organisation->name;
        $organisationNote = __(':organisation Note', ['organisation' => $organisationName]);

        $notes = match (true) {
            $viewer === self::VIEWER_AGENT => [
                $this->note(NotesEnum::PURCHASE_ORDER_ORGANISATION, 'notes', $purchaseOrder->notes, false, __('From :organisation. Only you read it.', ['organisation' => $organisationName]), $organisationNote),
                $this->note(NotesEnum::PURCHASE_ORDER_AGENT, 'agent_notes', $purchaseOrder->agent_notes, $canEdit, __('Only :organisation reads this note.', ['organisation' => $organisationName]), __('Agent Note (for :organisation)', ['organisation' => $organisationName])),
                $this->note(NotesEnum::PURCHASE_ORDER_AGENT_FOR_SUPPLIER, 'agent_notes_for_supplier', $purchaseOrder->agent_notes_for_supplier, $canEdit, __('Only the supplier reads this note, in its portal.')),
                $this->note(NotesEnum::PURCHASE_ORDER_SUPPLIER, 'supplier_notes', $purchaseOrder->supplier_notes, false, __('From the supplier, written in its portal. Only you read it.')),
            ],
            $viewer === self::VIEWER_SUPPLIER && $isAgentOrder => [
                $this->note(NotesEnum::PURCHASE_ORDER_AGENT_FOR_SUPPLIER, 'agent_notes_for_supplier', $purchaseOrder->agent_notes_for_supplier, false, __('From the agent.'), __('Agent Note')),
                $this->note(NotesEnum::PURCHASE_ORDER_SUPPLIER, 'supplier_notes', $purchaseOrder->supplier_notes, $canEdit, __('Only the agent reads this note.'), __('Your Note'), __('Write a note for the agent: a question about this order, a delay, a change in production…')),
            ],
            $viewer === self::VIEWER_SUPPLIER => [
                $this->note(NotesEnum::PURCHASE_ORDER_ORGANISATION, 'notes', $purchaseOrder->notes, false, __('From :organisation.', ['organisation' => $organisationName]), $organisationNote),
                $this->note(NotesEnum::PURCHASE_ORDER_SUPPLIER, 'supplier_notes', $purchaseOrder->supplier_notes, $canEdit, __('Only :organisation reads this note.', ['organisation' => $organisationName]), __('Your Note'), __('Write a note for :organisation: a question about this order, a delay, a change in production…', ['organisation' => $organisationName])),
            ],
            $isAgentOrder => [
                $this->note(NotesEnum::PURCHASE_ORDER_ORGANISATION, 'notes', $purchaseOrder->notes, $canEdit, __('Only the agent reads this note.'), $organisationNote),
                $this->note(NotesEnum::PURCHASE_ORDER_AGENT, 'agent_notes', $purchaseOrder->agent_notes, false, __('From the agent. Only you read it.'), __('Agent Note')),
            ],
            default => [
                $this->note(NotesEnum::PURCHASE_ORDER_ORGANISATION, 'notes', $purchaseOrder->notes, $canEdit, __('Only the supplier reads this note, in its portal.'), $organisationNote),
                $this->note(NotesEnum::PURCHASE_ORDER_SUPPLIER, 'supplier_notes', $purchaseOrder->supplier_notes, false, __('From the supplier, written in its portal. Only you read it.')),
            ],
        };

        return ['note_list' => $notes];
    }

    /**
     * @return array<string, mixed>
     */
    private function note(NotesEnum $type, string $field, ?string $note, bool $editable, string $information, ?string $label = null, ?string $placeholder = null): array
    {
        return [
            'label'       => $label ?? $type->label(),
            'placeholder' => $placeholder,
            'note'        => $note ?? '',
            'information' => $information,
            'editable'    => $editable,
            'field'       => $field,
            ...$type->boilerPlate(),
        ];
    }
}
