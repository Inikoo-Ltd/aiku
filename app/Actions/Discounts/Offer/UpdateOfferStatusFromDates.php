<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Mar 2026 09:50:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Discounts\Offer\Traits\HandlesOfferSideEffects;
use App\Actions\OrgAction;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class UpdateOfferStatusFromDates extends OrgAction
{
    use HandlesOfferSideEffects;

    public function handle(Offer $offer, ?Command $command = null): Offer
    {
        $state = $this->stateToApply($offer);

        if ($state == $offer->state && $offer->status == ($state == OfferStateEnum::ACTIVE)) {
            return $offer;
        }

        $command?->info("{$offer->slug}: {$offer->state->value} -> {$state->value}");

        match ($state) {
            OfferStateEnum::ACTIVE   => ActivateOffer::make()->handle($offer),
            OfferStateEnum::FINISHED => FinishOffer::make()->handle($offer, false),
            default                  => $this->setAsInProcess($offer),
        };

        $this->syncOfferAllowances($offer->refresh());

        return $offer;
    }

    /**
     * The state the dates ask for, bounded by what a sweep is allowed to do:
     * a suspended offer stays suspended until its end date, a finished offer never comes back,
     * and an offer without a start date is never switched on by the sweep.
     */
    protected function stateToApply(Offer $offer): OfferStateEnum
    {
        $state = $offer->stateFromDates();

        if ($state == OfferStateEnum::FINISHED) {
            return $state;
        }

        if ($offer->state == OfferStateEnum::SUSPENDED || $offer->state == OfferStateEnum::FINISHED) {
            return $offer->state;
        }

        if ($state == OfferStateEnum::ACTIVE && !$offer->start_at) {
            return $offer->state;
        }

        return $state;
    }

    protected function setAsInProcess(Offer $offer): void
    {
        $offer->update(['state' => OfferStateEnum::IN_PROCESS]);

        $this->handleOfferSideEffects($offer);
    }

    protected function syncOfferAllowances(Offer $offer): void
    {
        foreach ($offer->offerAllowances as $offerAllowance) {
            if ($offerAllowance->state == OfferAllowanceStateEnum::SUSPENDED && $offer->state != OfferStateEnum::FINISHED) {
                continue;
            }

            $offerAllowance->update(['state' => OfferAllowanceStateEnum::from($offer->state->value)]);
        }
    }

    public function getCommandSignature(): string
    {
        return 'offer:update_status_from_dates {offer?} {--shop=}';
    }

    public function asCommand(Command $command): int
    {
        if ($command->argument('offer')) {
            $this->handle(Offer::where('slug', $command->argument('offer'))->firstOrFail(), $command);

            return 0;
        }

        $query = $this->outOfSyncOffers();

        if ($command->option('shop')) {
            $query->where('shop_id', Shop::where('slug', $command->option('shop'))->firstOrFail()->id);
        }

        $bar = $command->getOutput()->createProgressBar($query->count());
        $bar->start();

        $query->chunkById(500, function ($offers) use ($command, $bar) {
            foreach ($offers as $offer) {
                $this->handle($offer, $command);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info('');

        return 0;
    }

    /**
     * Offers whose stored state or status no longer matches their dates.
     */
    public function outOfSyncOffers(): Builder
    {
        return Offer::where(function (Builder $query) {
            $query->where(function (Builder $query) {
                $query->where('state', '<>', OfferStateEnum::SUSPENDED)
                    ->where(function (Builder $query) {
                        $query->where('status', true)->orWhere('state', OfferStateEnum::ACTIVE);
                    })
                    ->where(function (Builder $query) {
                        $query->where('end_at', '<=', now())
                            ->orWhere('start_at', '>', now())
                            ->orWhere('state', OfferStateEnum::FINISHED);
                    });
            })->orWhere(function (Builder $query) {
                $query->where('state', OfferStateEnum::IN_PROCESS)
                    ->whereNotNull('start_at')
                    ->where('start_at', '<=', now())
                    ->where(function (Builder $query) {
                        $query->whereNull('end_at')->orWhere('end_at', '>', now());
                    });
            });
        });
    }
}
