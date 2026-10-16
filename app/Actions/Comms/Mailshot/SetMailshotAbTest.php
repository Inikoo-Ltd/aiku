<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 12 Oct 2026 12:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Helpers\Snapshot\StoreEmailSnapshot;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Mailshot\MailshotAbTestWinningMetricEnum;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Email;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SetMailshotAbTest extends OrgAction
{
    use WithActionUpdate;
    use WithMarketingEditAuthorisation;

    /**
     * @param  array{enabled?: bool, size_percentage?: int, variant_delay_seconds?: int, tracking_hours?: int, winning_metric?: string, auto_send_winner?: bool}  $modelData
     *
     * @throws \Throwable
     */
    public function handle(Mailshot $mailshot, array $modelData): Mailshot
    {
        if ($mailshot->isAbTestVariant() || $mailshot->is_second_wave || !in_array($mailshot->type, [MailshotTypeEnum::MARKETING, MailshotTypeEnum::NEWSLETTER])) {
            throw ValidationException::withMessages([
                'ab_test' => __('A/B tests can only be set up on a newsletter or marketing mailshot.'),
            ]);
        }

        if (!in_array($mailshot->state, [MailshotStateEnum::IN_PROCESS, MailshotStateEnum::READY])) {
            throw ValidationException::withMessages([
                'ab_test' => __('The A/B test can only be changed before the email is scheduled or sent.'),
            ]);
        }

        $settings = array_filter([
            'ab_test_size_percentage'       => Arr::get($modelData, 'size_percentage'),
            'ab_test_variant_delay_seconds' => Arr::get($modelData, 'variant_delay_seconds'),
            'ab_test_tracking_hours'      => Arr::get($modelData, 'tracking_hours'),
            'ab_test_winning_metric'      => Arr::get($modelData, 'winning_metric'),
            'ab_test_auto_send_winner'    => Arr::get($modelData, 'auto_send_winner'),
        ], fn ($value) => $value !== null);

        if ($settings) {
            $this->update($mailshot, $settings);
        }

        if (!Arr::has($modelData, 'enabled')) {
            return $mailshot->refresh();
        }

        if (!$modelData['enabled']) {
            $mailshot->abTestVariant?->delete();

            return $mailshot->refresh();
        }

        if ($mailshot->is_second_wave_enabled) {
            throw ValidationException::withMessages([
                'ab_test' => __('Turn off the 2nd wave before starting an A/B test.'),
            ]);
        }

        if (!$mailshot->abTestVariant) {
            /** @var Mailshot|null $deletedVariant */
            $deletedVariant = $mailshot->abTestVariant()->onlyTrashed()->latest('id')->first();

            if ($deletedVariant) {
                $this->restoreVariant($mailshot, $deletedVariant);
            } else {
                $this->createVariant($mailshot);
            }
        }

        return $mailshot->refresh();
    }

    /**
     * @throws \Throwable
     */
    private function createVariant(Mailshot $mailshot): Mailshot
    {
        return DB::transaction(function () use ($mailshot) {
            /** @var Mailshot $variant */
            $variant = $mailshot->abTestVariant()->create($this->variantData($mailshot));

            $variant->stats()->create();

            CloneMailshotForSecondWave::make()->copyEmail($mailshot, $variant);

            return $variant;
        });
    }

    /**
     * @throws \Throwable
     */
    private function restoreVariant(Mailshot $mailshot, Mailshot $variant): Mailshot
    {
        return DB::transaction(function () use ($mailshot, $variant) {
            $variant->restore();
            $this->update($variant, $this->variantData($mailshot));
            $this->copyEmailDesign($mailshot->email, $variant->email, $variant->subject);

            return $variant;
        });
    }

    private function copyEmailDesign(Email $sourceEmail, Email $targetEmail, string $subject): void
    {
        $sourceLiveSnapshot = $sourceEmail->liveSnapshot;
        $sourceSnapshot     = $sourceLiveSnapshot ?? $sourceEmail->unpublishedSnapshot;

        $this->update($targetEmail->unpublishedSnapshot, [
            'layout'          => $sourceSnapshot?->layout,
            'compiled_layout' => $sourceLiveSnapshot?->compiled_layout,
        ]);

        $liveSnapshot = null;
        if ($sourceLiveSnapshot) {
            $liveSnapshot = StoreEmailSnapshot::run($targetEmail, [
                'builder'         => $sourceLiveSnapshot->builder,
                'state'           => SnapshotStateEnum::LIVE,
                'published_at'    => $sourceLiveSnapshot->published_at,
                'layout'          => $sourceLiveSnapshot->layout,
                'compiled_layout' => $sourceLiveSnapshot->compiled_layout,
                'first_commit'    => false,
            ]);
        }

        $this->update($targetEmail, [
            'subject'          => $subject,
            'live_snapshot_id' => $liveSnapshot?->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function variantData(Mailshot $mailshot): array
    {
        return [
            'date'              => $mailshot->date,
            'group_id'          => $mailshot->group_id,
            'organisation_id'   => $mailshot->organisation_id,
            'shop_id'           => $mailshot->shop_id,
            'type'              => $mailshot->type,
            'subject'           => $mailshot->subject,
            'name'              => $mailshot->name,
            'preview_text'      => $mailshot->preview_text,
            'sender_name'       => $mailshot->sender_name,
            'state'             => $mailshot->state,
            'ready_at'          => $mailshot->ready_at,
            'outbox_id'         => $mailshot->outbox_id,
            'recipients_recipe' => $mailshot->recipients_recipe,
        ];
    }

    public function rules(): array
    {
        return [
            'enabled'             => ['sometimes', 'boolean'],
            'size_percentage'       => ['sometimes', 'integer', 'min:10', 'max:100'],
            'variant_delay_seconds' => ['sometimes', 'integer', 'min:0', 'max:604800'],
            'tracking_hours'      => ['sometimes', 'integer', 'min:1', 'max:720'],
            'winning_metric'      => ['sometimes', Rule::enum(MailshotAbTestWinningMetricEnum::class)],
            'auto_send_winner'    => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Mailshot $mailshot, array $modelData): Mailshot
    {
        $this->asAction = true;
        $this->initialisationFromShop($mailshot->shop, $modelData);

        return $this->handle($mailshot, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        abort_unless($mailshot->shop_id === $shop->id, 404);

        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }
}
