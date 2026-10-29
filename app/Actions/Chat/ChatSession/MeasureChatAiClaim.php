<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 22:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Helpers\AI\AskJev;
use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Enums\CRM\Livechat\ChatIntentEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatTopicEnum;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\MetaChatMessage;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What staff did with a damaged, missing or wrong item claim the assistant drafted for: the
 * order it is about, what the draft proposed (its claim button, or what Jev reads the text
 * offers), what was done on that order in the next 7 days (refund, hand credit, replacement,
 * items free in the next order) and Jev's reading of staff's reply. Refunds are not linked to
 * chats, so the order and timing match is an estimate; the reply keeps it honest. Values are
 * net, in the group's currency, so the value bands compare across shops. Measure only: nothing
 * is sent or changed. Run nightly, which is also the backfill; open claims are looked at again.
 */
class MeasureChatAiClaim
{
    use AsAction;

    public string $commandSignature = 'chat:measure-claims {--days=90 : How far back to look for claim drafts} {--limit=1000 : At most this many questions to Jev}';

    public const int WINDOW_DAYS = 7;

    public const float SAME_AMOUNT = 0.1;

    public const array INTENTS = [ChatIntentEnum::DAMAGED_ITEM, ChatIntentEnum::MISSING_ITEM, ChatIntentEnum::WRONG_ITEM];

    public const array LABELS = ['as_proposed', 'differently', 'declined', 'open', 'no_order'];

    private const string CONTEXT = 'Customer service of a wholesale giftware supplier. The customer reported damaged, missing or wrong items.';

    private const array OFFERS = [
        'refund'      => 'Money back or credit to the customer account balance for the items',
        'replacement' => 'Sends the items again now, in a replacement delivery',
        'next_order'  => 'Adds the items free to the customer next order',
        'none'        => 'Offers nothing yet: asks for photos or details, passes it to a colleague, declines, or only informs',
    ];

    private const array VERDICTS = [
        'as_proposed' => '"staff_reply" settles the claim the way "draft" proposed (same remedy, about the same amount)',
        'differently' => '"staff_reply" settles the claim but in another way or for another amount than "draft"',
        'declined'    => '"staff_reply" refuses the claim',
        'not_yet'     => '"staff_reply" does not settle the claim yet: asks for more, says it will look into it, or only informs',
    ];

    /**
     * @return array<string, mixed>
     */
    public function handle(ChatAiDraft $draft, bool $askJev = true): array
    {
        $customer = $draft->chat_session_id ? $draft->chatSession?->webUser?->customer : $draft->metaChatSession?->customer;
        $action   = collect(Arr::get($draft->facts, 'actions', []))->first(fn ($action) => is_array($action) && isset($action['resolution']));
        $order    = $customer ? self::order($customer, $action['order'] ?? null, $draft->text) : null;
        $check    = $draft->claim_check ?? [];

        $jev = $check['jev'] ?? [];
        if ($askJev) {
            $jev = $this->askJev($draft, $jev, (bool) $action);
        }

        $proposal = $action
            ? ['remedy' => self::remedy($action['resolution']), 'amount' => $order ? self::proposedAmount($order, $action) : null, 'from' => 'button']
            : ['remedy' => $jev['offer'] ?? null, 'amount' => null, 'from' => isset($jev['offer']) ? 'jev' : null];

        $given = $order ? self::given($order, $draft->created_at) : [];
        $value = $proposal['amount'] ?? (collect($given)->sum('amount') ?: null);

        $check = [
            'customer_id' => $customer?->id,
            'order_id'    => $order?->id,
            'order'       => $order?->reference,
            'proposal'    => $proposal,
            'given'       => $given,
            'label'       => $order ? self::label($proposal, $given, $draft->created_at) : 'no_order',
            'value'       => $value === null ? null : round($value, 2),
            'band'        => self::band($value),
            'jev'         => $jev,
            'measured_at' => now()->toIso8601String(),
        ];

        $draft->update(['claim_check' => $check]);

        return $check;
    }

    /**
     * The proposal against what was done: the same remedy for about the same amount is as
     * proposed; something else done is differently; nothing done in the window is declined,
     * and open until the window has passed, or while Jev has not read what the draft offered.
     *
     * @param  array{remedy: string|null, amount: float|null}  $proposal
     * @param  array<int, array{remedy: string, amount: float|null}>  $given
     */
    public static function label(array $proposal, array $given, Carbon $since): string
    {
        if ($given === []) {
            return $since->copy()->addDays(self::WINDOW_DAYS)->isFuture() ? 'open' : 'declined';
        }

        if (!$proposal['remedy']) {
            return 'open';
        }

        $same = collect($given)->where('remedy', $proposal['remedy']);
        if ($same->isEmpty()) {
            return 'differently';
        }

        $proposed = (float) $proposal['amount'];
        $done     = (float) $same->sum('amount');

        return !$proposal['amount'] || !$done || abs($done - $proposed) <= self::SAME_AMOUNT * $proposed ? 'as_proposed' : 'differently';
    }

    /**
     * The band edges are in the group's currency, net of tax.
     */
    public static function band(?float $value): string
    {
        if ($value === null) {
            return 'unknown';
        }

        $lower = 0;
        foreach (config('chat.claim_value_bands') as $edge) {
            if ($value < $edge) {
                return "$lower-$edge";
            }
            $lower = $edge;
        }

        return "$lower+";
    }

    /**
     * The order the claim is about: the one on the draft's claim button, else the latest of the
     * customer's orders whose reference the draft names.
     */
    private static function order(Customer $customer, ?string $reference, string $text): ?Order
    {
        $orders = $customer->orders()->latest('id');

        return $reference
            ? $orders->whereRaw('lower(reference) = ?', [strtolower(trim($reference))])->first()
            : $orders->whereRaw("length(reference) >= 4 and position(lower(reference) in ?) > 0", [strtolower($text)])->first();
    }

    /**
     * What the claim button would have settled, worked out from the order lines and not from
     * PlanChatClaim, which after staff settle sees nothing left to claim.
     *
     * @param  array<string, mixed>  $action
     */
    private static function proposedAmount(Order $order, array $action): ?float
    {
        $lines = DB::table('transactions')
            ->join('products', 'products.id', 'transactions.model_id')
            ->where('transactions.order_id', $order->id)
            ->where('transactions.model_type', 'Product')
            ->whereNull('transactions.deleted_at')
            ->selectRaw('lower(products.code) as code, sum(transactions.quantity_ordered) as ordered, sum(transactions.grp_net_amount) as amount')
            ->groupByRaw('1')
            ->get()
            ->keyBy('code');

        $percentage = $action['resolution'] === 'refund_to_balance' ? (float) ($action['percentage'] ?? 100) : 100.0;
        $amount     = collect($action['items'] ?? [])->sum(function (array $item) use ($lines) {
            $line = $lines->get(strtolower(trim((string) $item['code'])));

            return $line && (float) $line->ordered > 0 ? (float) $line->amount * min(1, (float) $item['quantity'] / (float) $line->ordered) : 0;
        });

        return $amount > 0 ? round($amount * $percentage / 100, 2) : null;
    }

    /**
     * Everything done on the order in the window, each with its net value in the group's currency.
     *
     * @return array<int, array{remedy: string, amount: float|null, reference: string|null}>
     */
    private static function given(Order $order, Carbon $since): array
    {
        $window = [$since, $since->copy()->addDays(self::WINDOW_DAYS)];

        $refunds = DB::table('invoices')->where('order_id', $order->id)->where('type', 'refund')->whereNull('deleted_at')
            ->where(fn ($finished) => $finished->where('in_process', false)->orWhereNull('in_process'))
            ->where('grp_net_amount', '<>', 0)
            ->whereBetween('created_at', $window)
            ->get(['reference', 'grp_net_amount'])
            ->map(fn ($refund) => ['remedy' => 'refund', 'amount' => round(abs((float) $refund->grp_net_amount), 2), 'reference' => $refund->reference]);

        $credits = DB::table('credit_transactions')->where('customer_id', $order->customer_id)
            ->whereIn('type', ['compensation', 'add_funds_other'])->where('amount', '>', 0)
            ->where(fn ($notTransfer) => $notTransfer->whereNull('reason')->orWhere('reason', '<>', 'transfer'))
            ->whereBetween('created_at', $window)
            ->get(['grp_amount'])
            ->map(fn ($credit) => ['remedy' => 'refund', 'amount' => round((float) $credit->grp_amount, 2), 'reference' => null]);

        $originals = DB::table('delivery_note_items')->join('delivery_notes', 'delivery_notes.id', 'delivery_note_items.delivery_note_id')
            ->join('delivery_note_order', 'delivery_note_order.delivery_note_id', 'delivery_notes.id')
            ->where('delivery_note_order.order_id', $order->id)->where('delivery_notes.type', '<>', 'replacement')
            ->selectRaw('delivery_note_items.transaction_id, sum(delivery_note_items.quantity_required) as quantity')
            ->groupBy('delivery_note_items.transaction_id');

        $replacements = DB::table('delivery_notes')
            ->join('delivery_note_order', 'delivery_note_order.delivery_note_id', 'delivery_notes.id')
            ->where('delivery_note_order.order_id', $order->id)->where('delivery_notes.type', 'replacement')
            ->whereBetween('delivery_notes.created_at', $window)
            ->get(['delivery_notes.id', 'delivery_notes.reference'])
            ->map(fn ($deliveryNote) => ['remedy' => 'replacement', 'reference' => $deliveryNote->reference, 'amount' => self::orNull(DB::table('delivery_note_items')
                ->joinSub($originals, 'originals', 'originals.transaction_id', 'delivery_note_items.transaction_id')
                ->join('transactions', 'transactions.id', 'delivery_note_items.transaction_id')
                ->where('delivery_note_items.delivery_note_id', $deliveryNote->id)
                ->selectRaw('transactions.id, max(transactions.grp_net_amount) * least(1, sum(delivery_note_items.quantity_required) / nullif(max(originals.quantity), 0)) as amount')
                ->groupBy('transactions.id')
                ->get()->sum('amount'))]);

        $nextOrder = DB::table('upcoming_transactions')
            ->join('transactions', 'transactions.id', 'upcoming_transactions.source_transaction_id')
            ->where('transactions.order_id', $order->id)
            ->where('upcoming_transactions.type', 'follow_on')
            ->whereBetween('upcoming_transactions.created_at', $window)
            ->selectRaw('sum(transactions.grp_net_amount * least(1, upcoming_transactions.quantity / nullif(transactions.quantity_ordered, 0))) as amount, count(*) as lines')
            ->first();

        return $refunds->concat($credits)->concat($replacements)
            ->when($nextOrder?->lines, fn ($given) => $given->push(['remedy' => 'next_order', 'amount' => self::orNull((float) $nextOrder->amount), 'reference' => null]))
            ->values()->all();
    }

    /**
     * Jev reads what the draft offers (only when it has no claim button saying so) and, once
     * staff replied, whether the reply settles it the way the draft proposed. Each is asked once.
     *
     * @param  array<string, mixed>  $jev
     * @return array<string, mixed>
     */
    private function askJev(ChatAiDraft $draft, array $jev, bool $hasButton): array
    {
        $reply     = self::staffReply($draft);
        $questions = [];

        if (!$hasButton && !isset($jev['offer'])) {
            $questions['offer'] = ['type' => 'choice', 'instructions' => self::CONTEXT.' "draft" is the reply drafted for staff to send. What does it offer for the claim? When it offers several, pick the one listed first.', 'criteria' => self::OFFERS];
        }
        if ($reply !== '' && !isset($jev['verdict'])) {
            $questions['verdict'] = ['type' => 'choice', 'instructions' => self::CONTEXT.' "draft" is what the assistant proposed and "staff_reply" what staff sent. How does "staff_reply" settle the claim compared with "draft"?', 'criteria' => self::VERDICTS];
        }

        if ($questions === []) {
            return $jev;
        }

        $answers = AskJev::make()->handle(array_filter(['draft' => mb_substr($draft->text, 0, 3000), 'staff_reply' => mb_substr($reply, 0, 3000)]), $questions);

        foreach (array_keys($questions) as $question) {
            if ($choice = Arr::get($answers, "$question.choice")) {
                $jev[$question]      = $choice;
                $jev[$question.'_p'] = round((float) Arr::get($answers, "$question.probabilities.$choice", 0), 3);
            }
        }

        return $jev;
    }

    /**
     * The reply staff sent from the draft, else the first thing staff wrote after it.
     */
    private static function staffReply(ChatAiDraft $draft): string
    {
        $message = $draft->chat_session_id ? ChatMessage::class : MetaChatMessage::class;
        if ($draft->reply_message_id) {
            return trim((string) $message::find($draft->reply_message_id)?->message_text);
        }

        return trim((string) $message::query()
            ->where($draft->chat_session_id ? 'chat_session_id' : 'meta_chat_session_id', $draft->chat_session_id ?? $draft->meta_chat_session_id)
            ->where('sender_type', ChatSenderTypeEnum::AGENT)
            ->where('created_at', '>=', $draft->created_at)
            ->orderBy('id')
            ->value('message_text'));
    }

    private static function remedy(string $resolution): string
    {
        return $resolution === 'refund_to_balance' ? 'refund' : $resolution;
    }

    private static function orNull(float $amount): ?float
    {
        return $amount > 0 ? round($amount, 2) : null;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $budget   = (int) $command->option('limit');
        $measured = 0;

        ChatAiDraft::where('facts->mode', DraftChatReply::ASSISTANT)
            ->where('created_at', '>=', now()->subDays((int) $command->option('days')))
            ->where('status', '<>', ChatAiDraftStatusEnum::PENDING)
            ->where(fn ($claim) => $claim->whereIn('intent', self::INTENTS)->orWhere('topic', ChatTopicEnum::MISSING_OR_DAMAGED))
            ->where(fn ($due) => $due->whereNull('claim_check')
                ->orWhere('claim_check->label', 'open')
                ->orWhere(fn ($noVerdict) => $noVerdict->whereNull('claim_check->jev->verdict')->where('created_at', '>=', now()->subDays(2 * self::WINDOW_DAYS))))
            ->chunkById(100, function ($drafts) use (&$measured, &$budget) {
                foreach ($drafts as $draft) {
                    $this->handle($draft, $budget-- > 0);
                    $measured++;
                }
            });

        $command->info("Measured $measured claim drafts.");

        return 0;
    }
}
