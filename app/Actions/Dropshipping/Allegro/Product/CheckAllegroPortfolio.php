<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckAllegroPortfolio
{
    use AsAction;

    public const int RECHECK_AFTER_PUBLISH_SECONDS = 60;

    public const int RECHECK_ATTEMPTS = 5;

    public const string ACTIVATING_MESSAGE = 'Allegro is activating this offer.';

    public string $commandSignature = 'allegro:check-portfolios {customerSalesChannel}';

    public function handle(Portfolio $portfolio, ?string $publicationCommandId = null, int $attempt = 1): Portfolio
    {
        if (!$portfolio->customerSalesChannel) {
            return $portfolio;
        }

        /** @var AllegroUser $allegroUser */
        $allegroUser = $portfolio->customerSalesChannel->user;

        if (!$allegroUser instanceof AllegroUser) {
            return $portfolio;
        }

        if (!$portfolio->platform_product_id) {
            return $this->recordPossibleMatches($allegroUser, $portfolio);
        }

        try {
            $response = $allegroUser->getOfferResponse($portfolio->platform_product_id);
        } catch (\Throwable) {
            return $this->recheckLater($portfolio, $publicationCommandId, $attempt);
        }

        if ($response->status() === 404) {
            UpdatePortfolio::run($portfolio, [
                'has_valid_platform_product_id' => true,
                'exist_in_platform'             => false,
                'platform_status'               => false,
                'errors_response'               => ['message' => "Allegro no longer has offer $portfolio->platform_product_id. Upload the product again to create a new offer."],
            ]);

            return $portfolio;
        }

        if ($response->failed()) {
            return $this->recheckLater($portfolio, $publicationCommandId, $attempt);
        }

        $offer           = $response->json() ?? [];
        $publicationTask = $publicationCommandId ? Arr::get($allegroUser->getOfferPublicationTasks($publicationCommandId), 'tasks.0') : null;
        $taskFinished    = in_array(Arr::get($publicationTask, 'status'), ['SUCCESS', 'FAIL']);
        $offerStatus     = Arr::get($offer, 'publication.status');

        if ($publicationCommandId && $attempt < self::RECHECK_ATTEMPTS
            && ($offerStatus === 'ACTIVATING' || ($offerStatus === 'INACTIVE' && !$taskFinished))) {
            return $this->recheckLater($portfolio, $publicationCommandId, $attempt);
        }

        return $this->applyOffer($portfolio, $offer, $this->publicationFailure($publicationTask));
    }

    private function recheckLater(Portfolio $portfolio, ?string $publicationCommandId, int $attempt): Portfolio
    {
        if ($publicationCommandId && $attempt < self::RECHECK_ATTEMPTS) {
            static::dispatch($portfolio, $publicationCommandId, $attempt + 1)->delay(now()->addSeconds(self::RECHECK_AFTER_PUBLISH_SECONDS * ($attempt + 1)));
        }

        return $portfolio;
    }

    public function applyOffer(Portfolio $portfolio, array $offer, ?string $publicationFailure = null): Portfolio
    {
        $status  = Arr::get($offer, 'publication.status');
        $endedBy = Arr::get($offer, 'publication.endedBy');

        if (!$status) {
            return $portfolio;
        }

        $notLiveReason = $this->notLiveReason($status, $endedBy, $offer, $publicationFailure);

        UpdatePortfolio::run($portfolio, [
            'has_valid_platform_product_id' => true,
            'exist_in_platform'             => true,
            'platform_status'               => $status === 'ACTIVE',
            'errors_response'               => $notLiveReason ? ['message' => $notLiveReason] : null,
            'data'                          => ['allegro_publication' => ['status' => $status, 'ended_by' => $endedBy]],
        ]);

        return $portfolio;
    }

    public static function isLiveOrActivating(Portfolio $portfolio): bool
    {
        return $portfolio->platform_status || Arr::get($portfolio->data, 'allegro_publication.status') === 'ACTIVATING';
    }

    public static function storedAsEndedForEmptyStock(Portfolio $portfolio): bool
    {
        return self::endedForEmptyStock(['publication' => [
            'status'  => Arr::get($portfolio->data, 'allegro_publication.status'),
            'endedBy' => Arr::get($portfolio->data, 'allegro_publication.ended_by'),
        ]]);
    }

    public static function endedForEmptyStock(array $offer): bool
    {
        return Arr::get($offer, 'publication.status') === 'ENDED' && Arr::get($offer, 'publication.endedBy') === 'EMPTY_STOCK';
    }

    private function notLiveReason(string $status, ?string $endedBy, array $offer, ?string $publicationFailure): ?string
    {
        if ($status === 'ACTIVE') {
            return null;
        }

        if ($status === 'ACTIVATING') {
            return self::ACTIVATING_MESSAGE;
        }

        if ($status === 'ENDED') {
            return match ($endedBy) {
                'EMPTY_STOCK' => 'Allegro ended this offer because it ran out of stock. It is activated again when we have stock.',
                'USER'        => 'This offer was ended on Allegro.',
                default       => 'Allegro ended this offer'.($endedBy ? " ($endedBy)" : '').'.',
            };
        }

        $reasons = array_filter([
            $publicationFailure,
            ...array_map(
                fn ($error) => Arr::get($error, 'userMessage') ?? Arr::get($error, 'message'),
                Arr::get($offer, 'validation.errors', [])
            ),
        ]);

        return $reasons
            ? 'Allegro did not activate this offer: '.implode(' ', array_unique($reasons))
            : 'This offer is on Allegro but not active, and Allegro gave no reason.';
    }

    private function publicationFailure(?array $task): ?string
    {
        if (!$task || Arr::get($task, 'status') === 'SUCCESS') {
            return null;
        }

        $messages = array_filter([
            Arr::get($task, 'message'),
            ...array_map(fn ($error) => Arr::get($error, 'userMessage') ?? Arr::get($error, 'message'), Arr::get($task, 'errors', [])),
        ]);

        return $messages ? implode(' ', array_unique($messages)) : null;
    }

    private function recordPossibleMatches(AllegroUser $allegroUser, Portfolio $portfolio): Portfolio
    {
        $matches = CheckIfProductExistInAllegro::run($allegroUser, $portfolio);

        $portfolio->update([
            'has_valid_platform_product_id'    => false,
            'exist_in_platform'                => false,
            'platform_status'                  => false,
            'platform_possible_matches'        => [
                'number_matches' => count($matches),
                'matches_labels' => Arr::pluck($matches, 'name'),
                'raw_data'       => $matches
            ],
            'number_platform_possible_matches' => count($matches)
        ]);

        return $portfolio;
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $portfolios = $customerSalesChannel->portfolios()->whereNotNull('platform_product_id')->get();

        foreach ($portfolios as $portfolio) {
            $this->handle($portfolio);
            $command->line(implode(' | ', [
                $portfolio->id,
                $portfolio->platform_product_id,
                Arr::get($portfolio->data, 'allegro_publication.status', '?'),
                $portfolio->platform_status ? 'live' : 'not live',
                Arr::get($portfolio->errors_response, 'message', ''),
            ]));
        }
    }
}
