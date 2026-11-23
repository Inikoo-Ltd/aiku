<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\ShoppingAssistant;

use App\Actions\CRM\ShoppingAssistant\AnswerShoppingAssistantMessage;
use App\Actions\CRM\ShoppingAssistant\AskShoppingAssistant;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantBudget;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantHandOverRoute;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantSettings;
use App\Actions\CRM\ShoppingAssistant\StartShoppingAssistantConversation;
use App\Actions\CRM\ShoppingAssistant\StoreShoppingAssistantRefusal;
use App\Actions\Helpers\AI\AskJev;
use App\Actions\IrisAction;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantConversationStatusEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage;
use App\Models\Web\Webpage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Stores the customer's message and queues the answer; the page polls ShowIrisShoppingAssistantConversation.
 *
 * Two messages are not answered by the model again. The question the customer just asked, sent
 * again within SAME_QUESTION_SECONDS (the search box pressed twice, the same words typed again):
 * the page is pointed at the answer it already has. And a short "yes" to an answer that offered a
 * person, where the customer types instead of pressing the button: the page is told which offer
 * was accepted and hands over as the button does, when it says it can ($canHandOver).
 */
class AskIrisShoppingAssistant extends IrisAction
{
    public const int SAME_QUESTION_SECONDS = 180;

    public const int ACCEPTANCE_MAX_WORDS = 12;

    public const float ACCEPTANCE_CONFIDENCE = 0.8;

    public const int TRAIL_PAGES = 5;

    /**
     * @param  array<int, UploadedFile>  $images
     */
    public function handle(Customer $customer, ?ShoppingAssistantConversation $conversation, string $message, int $webUserId, ShoppingAssistantEntryPointEnum $entryPoint = ShoppingAssistantEntryPointEnum::SEARCH, bool $canHandOver = false, array $images = [], ?int $webpageId = null, array $trailWebpageIds = []): JsonResponse
    {
        if ($conversation?->isPending()) {
            return response()->json(['pending' => true], 409);
        }

        if (!$images && ($repeated = $this->justAnswered($customer, $conversation, $message, $entryPoint))) {
            return response()->json(['conversation' => $repeated->conversation->ulid, 'message_id' => $repeated->id, 'pending' => false, 'repeated' => true]);
        }

        if (!$images && $canHandOver && $conversation && ($offer = $this->acceptedOffer($customer, $conversation, $message))) {
            return response()->json(['conversation' => $conversation->ulid, 'message_id' => $offer['question']->id, 'pending' => false, 'accept_offer' => $offer['offer']->id]);
        }

        $conversation ??= StartShoppingAssistantConversation::run($customer, $entryPoint, ['web_user_id' => $webUserId]);

        $customerMessage = AskShoppingAssistant::make()->storeCustomerMessage($conversation, $message, $images, $webpageId, $trailWebpageIds);
        AnswerShoppingAssistantMessage::dispatch($customerMessage);

        return response()->json([
            'conversation' => $conversation->ulid,
            'message_id'   => $customerMessage->id,
            'pending'      => true,
        ], 202);
    }

    /**
     * The customer's last question when this message repeats it and it already has its answer: in
     * the conversation they are in, else in the one they asked in last on this website.
     */
    private function justAnswered(Customer $customer, ?ShoppingAssistantConversation $conversation, string $message, ShoppingAssistantEntryPointEnum $entryPoint): ?ShoppingAssistantMessage
    {
        $conversation ??= ShoppingAssistantConversation::where('customer_id', $customer->id)
            ->where('website_id', $customer->shop->website?->id)
            ->where('entry_point', $entryPoint)
            ->where('status', ShoppingAssistantConversationStatusEnum::OPEN)
            ->whereNull('wiped_at')
            ->where('last_message_at', '>=', now()->subSeconds(self::SAME_QUESTION_SECONDS))
            ->latest('id')
            ->first();

        $last = $conversation?->messages()->latest('id')->limit(2)->get();
        if (!$last || $last->count() < 2 || $last[0]->role !== ShoppingAssistantMessageRoleEnum::ASSISTANT || $last[1]->role !== ShoppingAssistantMessageRoleEnum::CUSTOMER) {
            return null;
        }

        $question = $last[1];
        $same     = mb_strtolower(trim((string) $question->body)) === mb_strtolower(mb_substr(trim($message), 0, AskShoppingAssistant::MAX_MESSAGE));

        return $same && $question->created_at->gte(now()->subSeconds(self::SAME_QUESTION_SECONDS)) ? $question->setRelation('conversation', $conversation) : null;
    }

    /**
     * The offer of a person the customer is saying yes to, with the question it answered. Jev reads
     * the short message; no verdict (no key, Jev down) sends it to the model as before.
     *
     * @return array{offer: ShoppingAssistantMessage, question: ShoppingAssistantMessage}|null
     */
    private function acceptedOffer(Customer $customer, ShoppingAssistantConversation $conversation, string $message): ?array
    {
        $last = $conversation->messages()->latest('id')->limit(2)->get();
        if ($last->count() < 2 || $last[0]->role !== ShoppingAssistantMessageRoleEnum::ASSISTANT || !$last[0]->hand_over_offer || $last[1]->role !== ShoppingAssistantMessageRoleEnum::CUSTOMER) {
            return null;
        }

        if (count(preg_split('/\s+/u', trim($message), -1, PREG_SPLIT_NO_EMPTY) ?: []) > self::ACCEPTANCE_MAX_WORDS
            || !$conversation->website
            || !GetShoppingAssistantHandOverRoute::isOffered($conversation->website, $customer)) {
            return null;
        }

        $probability = AskJev::make()->noul(
            ['our_offer' => (string) $last[0]->body, 'customer_reply' => $message],
            'Our shop assistant could not answer a customer and offered to pass their question to our team. The customer replied with this message. Is the customer simply accepting the offer?',
            'They say yes: they want their question passed to our team or to talk to a person, and add no new question or information.',
            'They decline, ask something else, add details about their problem, or the message is about anything other than accepting the offer.'
        );

        return $probability !== null && $probability >= self::ACCEPTANCE_CONFIDENCE ? ['offer' => $last[0], 'question' => $last[1]] : null;
    }

    public function rules(): array
    {
        return [
            'message'      => ['required_without:images', 'nullable', 'string', 'max:1000'],
            'images'       => ['sometimes', 'array', 'max:'.ShoppingAssistantMessage::MAX_IMAGES],
            'images.*'     => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'can_hand_over' => ['sometimes', 'boolean'],
            'conversation' => ['sometimes', 'nullable', 'string', 'ulid'],
            'page'         => ['sometimes', 'nullable', 'string'],
            'trail'        => ['sometimes', 'nullable', 'array', 'max:10'],
            'trail.*'      => ['nullable'],
            'entry_point'  => ['sometimes', 'nullable', Rule::in([ShoppingAssistantEntryPointEnum::SEARCH->value, ShoppingAssistantEntryPointEnum::CHAT->value])],
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $this->initialisation($request);

        $customerId = $this->signedInCustomerId();
        $customer   = $customerId ? Customer::find($customerId) : null;
        abort_unless($customer, 404);
        if (!GetShoppingAssistantSettings::run($this->website, $customer, true)) {
            if (GetShoppingAssistantSettings::run($this->website, $customer)) {
                $reason = GetShoppingAssistantBudget::run($this->website)['paused'] ? StoreShoppingAssistantRefusal::BUDGET : StoreShoppingAssistantRefusal::CUSTOMER_CAP;
                StoreShoppingAssistantRefusal::afterResponse($this->website, $customer->id, $reason, $request->input('message'));
            }
            abort(404);
        }

        $conversation = null;
        if ($ulid = Arr::get($this->validatedData, 'conversation')) {
            $conversation = ShoppingAssistantConversation::where('ulid', $ulid)
                ->where('customer_id', $customer->id)
                ->where('website_id', $this->website->id)
                ->where('status', ShoppingAssistantConversationStatusEnum::OPEN)
                ->whereNull('wiped_at')
                ->first();
            abort_unless($conversation, 404);
        }

        return $this->handle($customer, $conversation, (string) Arr::get($this->validatedData, 'message'), $request->user()->id, ShoppingAssistantEntryPointEnum::tryFrom((string) Arr::get($this->validatedData, 'entry_point')) ?? ShoppingAssistantEntryPointEnum::SEARCH, (bool) Arr::get($this->validatedData, 'can_hand_over', false), Arr::get($this->validatedData, 'images', []), $webpageId = $this->openWebpageId(Arr::get($this->validatedData, 'page')), $this->trailWebpageIds(Arr::get($this->validatedData, 'trail') ?? [], $webpageId));
    }

    /**
     * The live page of this website at the path the customer asked from; webpages are found by the
     * last part of the path, as the storefront finds them.
     */
    private function openWebpageId(?string $page): ?int
    {
        return $this->liveWebpageIds([(string) $page])[0] ?? null;
    }

    /**
     * The catalogue pages the customer opened before asking, latest first, without the page they
     * asked from.
     *
     * @param  array<int, mixed>  $paths
     *
     * @return array<int, int>
     */
    private function trailWebpageIds(array $paths, ?int $openWebpageId): array
    {
        $ids       = $this->liveWebpageIds(array_filter($paths, 'is_string'));
        $catalogue = Webpage::whereIn('id', $ids)->whereIn('model_type', ['Product', 'ProductCategory'])->pluck('id')->flip();

        return array_slice(array_values(array_filter($ids, fn (int $id) => $id !== $openWebpageId && isset($catalogue[$id]))), 0, self::TRAIL_PAGES);
    }

    /**
     * @param  array<int, string>  $paths
     *
     * @return array<int, int> ids in the order of the paths that resolved, each once
     */
    private function liveWebpageIds(array $paths): array
    {
        $urls = array_values(array_unique(array_filter(array_map(
            fn (string $path) => Str::of(mb_substr($path, 0, 500))->before('?')->before('#')->trim('/')->afterLast('/')->lower()->toString(),
            $paths
        ))));
        if (!$urls) {
            return [];
        }

        $ids = Webpage::where('website_id', $this->website->id)
            ->whereIn('url', $urls)
            ->where('state', WebpageStateEnum::LIVE)
            ->whereNull('deleted_at')
            ->pluck('id', 'url');

        return array_values(array_filter(array_map(fn (string $url) => $ids[$url] ?? null, $urls)));
    }
}
