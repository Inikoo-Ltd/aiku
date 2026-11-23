<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim;

use App\Actions\Helpers\AI\Traits\WithAICreditErrorHandler;
use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Actions\Helpers\Images\GetPictureSources;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use App\Models\Helpers\Media;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * A claim the shop would approve with no person has its photos checked first (HELP-4869): a photo
 * already sent with another claim, or one the model says does not show the ordered product, does
 * not show the claimed problem, or is a screenshot, stock picture, generated or unrelated image,
 * sends the claim to customer services instead. It never rejects a claim, and when the check
 * itself fails the claim goes to a person too. The outcome is kept on the claim for the reviewer.
 */
class CheckCustomerClaimPhotos
{
    use AsAction;
    use WithAIGateway;
    use WithAICreditErrorHandler;

    public const string MODEL = 'anthropic/claude-opus-5.5';

    // ponytail: synchronous, so the customer waits for it when they send; queue it if it gets slow
    public const int TIMEOUT_SECONDS = 45;

    public function handle(CustomerClaim $claim): bool
    {
        $photos = $claim->getMedia(CustomerClaim::PHOTOS);
        if ($photos->isEmpty()) {
            return true;
        }

        $problems = $this->reusedPhotos($claim, $photos);
        if (!$problems) {
            try {
                $problems = $this->askModel($claim, $photos);
            } catch (Throwable $exception) {
                report($exception);
                $problems = ['The photos could not be checked automatically.'];
            }
        }

        $claim->update(['result' => array_merge($claim->result ?? [], ['photo_check' => ['passed' => !$problems, 'problems' => $problems]])]);

        return !$problems;
    }

    /**
     * @param  Collection<int, Media>  $photos
     *
     * @return array<int, string>
     */
    private function reusedPhotos(CustomerClaim $claim, Collection $photos): array
    {
        $reused = Media::where('model_type', $claim->getMorphClass())
            ->where('model_id', '!=', $claim->id)
            ->whereIn('checksum', $photos->pluck('checksum')->filter()->all())
            ->distinct()
            ->pluck('model_id');

        if ($reused->isEmpty()) {
            return [];
        }

        $references = CustomerClaim::whereIn('id', $reused)->pluck('reference')->implode(', ');

        return ["A photo was already sent with claim $references."];
    }

    /**
     * @param  Collection<int, Media>  $photos
     *
     * @return array<int, string>
     */
    private function askModel(CustomerClaim $claim, Collection $photos): array
    {
        $lines = $claim->lines->keyBy('transaction_id');

        $content = [[
            'type' => 'text',
            'text' => 'A customer claims these items of their order arrived with a problem, and sent the photos below. Our staff will review the claim if you report any problem, so report only what is clearly wrong, not doubts about photo quality.'
                ."\nItems claimed:\n".$claim->lines->map(fn ($line) => '- '.($line->name ?: $line->code).' ('.$line->code.') x'.(float) $line->quantity.': '.GetCustomerClaimForm::reasonLabel($line->reason, 'en'))->implode("\n")
                ."\n\nFor each photo check: does it show the product it is for (photos of the outer box only need to show a parcel box), does it show the claimed problem where the reason is broken, faulty or wrong item, and is it clearly not a real photo the customer took (a screenshot, a stock or website product picture, an AI-generated or edited image, or an unrelated or offensive image)."
                ."\nReturn problems as short sentences in English, each naming the photo by its number and what it is for (e.g. \"Photo 2, for the glass candle: shows a different product\"). Return an empty list when nothing is clearly wrong.",
        ]];

        foreach ($photos->values() as $index => $media) {
            $line      = str_starts_with((string) $media->media_scope, 'transaction:') ? $lines->get((int) substr((string) $media->media_scope, 12)) : null;
            $content[] = ['type' => 'text', 'text' => 'Photo '.($index + 1).': '.($line ? 'for '.($line->name ?: $line->code) : 'of the outer box')];
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => GetPictureSources::run($media->getImage()->resize(1200, 0))['original']]];
        }

        $response = $this->aiRequest()
            ->timeout(self::TIMEOUT_SECONDS)
            ->post('chat/completions', [
                'model'           => $this->aiModel(self::MODEL),
                'messages'        => [['role' => 'user', 'content' => $content]],
                'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'claim_photo_check', 'strict' => true, 'schema' => [
                    'type'                 => 'object',
                    'properties'           => ['problems' => ['type' => 'array', 'items' => ['type' => 'string']]],
                    'required'             => ['problems'],
                    'additionalProperties' => false,
                ]]],
            ]);

        $this->guardAICreditResponse($response, 'grp');
        $response->throw();

        $answer = json_decode(trim((string) preg_replace('/^```(?:json)?|```$/m', '', trim((string) $response->json('choices.0.message.content')))), true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($answer['problems'] ?? null)) {
            throw new RuntimeException('The photo check returned no list of problems');
        }

        return array_values(array_filter(array_map(fn ($problem) => mb_substr(trim((string) $problem), 0, 300), $answer['problems'])));
    }
}
