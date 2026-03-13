<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 03-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Helpers\AI;

use App\Actions\OrgAction;
use OpenAI;

class GetGeneratedImages extends OrgAction
{
    public function handle(string $prompt, array $metadata = []): array
    {
        $client = OpenAI::client(config('services.openai.api_key'));

        $n       = $metadata['n'] ?? 1;
        $size    = $metadata['size'] ?? '1024x1024';
        $quality = $metadata['quality'] ?? 'standard';
        $format  = $metadata['format'] ?? 'url';
        $images  = $metadata['images'] ?? [];

        if (!empty($images)) {
            $response = $client->images()->edit([
                'model'           => 'dall-e-2',
                'image'           => $this->prepareImage($images[0]),
                'prompt'          => $prompt,
                'n'               => $n,
                'size'            => $size,
                'response_format' => $format,
            ]);
        } else {
            $response = $client->images()->create([
                'model'           => 'dall-e-3',
                'prompt'          => $prompt,
                'n'               => $n,
                'size'            => $size,
                'quality'         => $quality,
                'response_format' => $format,
            ]);
        }

        return collect($response->data)->map(function ($image) use ($format) {
            return $format === 'b64_json' ? $image->b64_json : $image->url;
        })->toArray();
    }

    private function prepareImage(string $image): false
    {
        if (file_exists($image)) {
            return fopen($image, 'r');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'ai_img_') . '.png';
        file_put_contents($tmpFile, base64_decode($image));

        return fopen($tmpFile, 'r');
    }

    public function action(string $prompt, array $metadata = []): array
    {
        $this->asAction = true;

        return $this->handle($prompt, $metadata);
    }
}
