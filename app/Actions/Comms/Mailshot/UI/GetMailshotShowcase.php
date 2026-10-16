<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 May 2023 20:59:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\Comms\Mailshot\InjectUtmToEmailLinks;
use App\Http\Resources\Mail\MailshotResource;
use App\Models\Comms\Mailshot;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMailshotShowcase
{
    use AsAction;

    public function handle(Mailshot $mailshot): array
    {
        $compiledLayout = $this->compiledLayout($mailshot);
        $snapshot       = $mailshot->email?->unpublishedSnapshot;
        $bytes = strlen($compiledLayout ?? '');
        $kb    = round(($bytes / 1024), 2);
        return [
            'mailshot' => new MailshotResource($mailshot),
            'compiled_layout' => $compiledLayout,
            'compiled_layout_size' => $kb,
            'is_composed' => (bool) $compiledLayout || ($snapshot && !$snapshot->updated_at->eq($snapshot->created_at)),
            'ab_test_previews' => $this->abTestPreviews($mailshot),
        ];
    }

    private function compiledLayout(Mailshot $mailshot): ?string
    {
        return InjectUtmToEmailLinks::run($mailshot, $mailshot->email?->liveSnapshot?->compiled_layout);
    }

    /**
     * @return array<int, array{label: string, id: int, subject: ?string, compiled_layout: ?string}>
     */
    private function abTestPreviews(Mailshot $mailshot): array
    {
        $original = $mailshot->isAbTestVariant() ? $mailshot->abTestOriginal : $mailshot;
        $variant  = $original?->abTestVariant;

        if (!$variant) {
            return [];
        }

        return collect(['A' => $original, 'B' => $variant])
            ->map(fn (Mailshot $version, string $label) => [
                'label'           => $label,
                'id'              => $version->id,
                'subject'         => $version->subject,
                'compiled_layout' => $this->compiledLayout($version),
            ])
            ->values()
            ->all();
    }
}
