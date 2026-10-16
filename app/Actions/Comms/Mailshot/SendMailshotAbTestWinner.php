<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 12 Oct 2026 12:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SendMailshotAbTestWinner extends OrgAction
{
    use WithMarketingEditAuthorisation;

    /**
     * @param  array{winner_mailshot_id: int}  $modelData
     */
    public function handle(Mailshot $mailshot, array $modelData): Mailshot
    {
        $variant = $mailshot->abTestVariant;

        if (!$variant || !in_array($modelData['winner_mailshot_id'], [$mailshot->id, $variant->id])) {
            throw ValidationException::withMessages([
                'winner_mailshot_id' => __('The winner must be version A or version B of this A/B test.'),
            ]);
        }

        if ($mailshot->ab_test_size_percentage >= 100) {
            throw ValidationException::withMessages([
                'winner_mailshot_id' => __('This A/B test was sent to the whole list, there is nobody left to send the winner to.'),
            ]);
        }

        if ($mailshot->state !== MailshotStateEnum::SENT || $variant->state !== MailshotStateEnum::SENT) {
            throw ValidationException::withMessages([
                'winner_mailshot_id' => __('Wait until both versions have finished sending before picking a winner.'),
            ]);
        }

        $trackingEndsAt = $mailshot->abTestTrackingEndsAt();
        if ($trackingEndsAt?->isFuture()) {
            throw ValidationException::withMessages([
                'winner_mailshot_id' => __('Results are still being tracked until :date.', ['date' => $trackingEndsAt->toDayDateTimeString()]),
            ]);
        }

        FreezeMailshotAbTestResults::run($mailshot);

        $claimed = Mailshot::whereKey($mailshot->id)
            ->whereNull('ab_test_winner_sent_at')
            ->update([
                'ab_test_winner_mailshot_id' => $modelData['winner_mailshot_id'],
                'ab_test_winner_sent_at'     => now(),
            ]);

        if ($claimed !== 1) {
            throw ValidationException::withMessages([
                'winner_mailshot_id' => __('The winner has already been sent.'),
            ]);
        }

        Mailshot::whereKey($modelData['winner_mailshot_id'])->update([
            'state'                => MailshotStateEnum::SENDING,
            'recipients_stored_at' => null,
            'sent_at'              => null,
        ]);

        PrepareMailshotAbTestWinnerRecipients::dispatch($mailshot->refresh());

        return $mailshot;
    }

    public function rules(): array
    {
        return [
            'winner_mailshot_id' => ['required', 'integer'],
        ];
    }

    public function action(Mailshot $mailshot, array $modelData): Mailshot
    {
        $this->asAction = true;
        $this->initialisationFromShop($mailshot->shop, $modelData);

        return $this->handle($mailshot, $this->validatedData);
    }

    public function asController(Shop $shop, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        abort_unless($mailshot->shop_id === $shop->id, 404);

        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }
}
