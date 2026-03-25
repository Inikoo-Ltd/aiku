<?php

namespace App\Actions\Helpers\AI;

use App\Actions\OrgAction;
use OpenAI;

class GetGeneratedImages extends OrgAction
{
    public function handle(string $prompt, array $metadata = []): array
    {
        $client = OpenAI::client(config('services.openai.api_key'));

        $n = $metadata['n'] ?? 1;
        $size = $metadata['size'] ?? '1024x1024';
        $quality = $metadata['quality'] ?? 'standard';
        $format = $metadata['format'] ?? 'url';
        $images = $metadata['images'] ?? [];

        if (!empty($images)) {
            $processedImages = [];
            foreach ($images as $image) {
                $preparedImage = $this->prepareImage($image);
                if ($preparedImage) {
                    $processedImages[] = $preparedImage;
                }
            }

            $response = $client->images()->edit([
                'model' => 'dall-e-2',
                'image' => $processedImages[0],
                'prompt' => $prompt,
                'n' => $n,
                'size' => $size,
                'response_format' => $format,
            ]);
        } else {
            $response = $client->images()->create([
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => $n,
                'size' => $size,
                'quality' => $quality,
                'response_format' => $format,
            ]);
        }

        return collect($response->data)->map(function ($image) use ($format) {
            return $format === 'b64_json' ? $image->b64_json : $image->url;
        })->toArray();
    }

    private function prepareImage(string $image): mixed
    {
        if (file_exists($image)) {
            return fopen($image, 'r');
        }

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            $imageContent = file_get_contents($image);
            if ($imageContent !== false) {
                $tmpFile = tempnam(sys_get_temp_dir(), 'ai_img_') . '.png';
                file_put_contents($tmpFile, $imageContent);

                return fopen($tmpFile, 'r');
            }
        }

        $decodedImage = base64_decode($image, true);
        if ($decodedImage !== false) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'ai_img_') . '.png';
            file_put_contents($tmpFile, $decodedImage);

            return fopen($tmpFile, 'r');
        }

        return false;
    }

    public function action(string $prompt, array $metadata = []): array
    {
        $this->asAction = true;

        return $this->handle($prompt, $metadata);
    }
}
