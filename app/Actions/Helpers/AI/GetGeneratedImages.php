<?php

namespace App\Actions\Helpers\AI;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\OrgAction;
use App\Models\Helpers\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use OpenAI;

class GetGeneratedImages extends OrgAction
{
    public function handle(string $prompt, array $metadata = []): array
    {
        $apiKey = config('services.openai.api_key');
        $client = OpenAI::client($apiKey);

        $n = $metadata['n'] ?? 1;
        $size = $metadata['size'] ?? '1024x1024';
        $quality = $metadata['quality'] ?? 'standard';
        $format = $metadata['format'] ?? 'url';
        $images = $metadata['images'] ?? [];

        if (!empty($images)) {
            $processedImages = [];
            foreach ($images as $imageId) {
                $preparedImageUrl = $this->prepareImage($imageId);
                if ($preparedImageUrl) {
                    $processedImages[] = [
                        'image_url' => $preparedImageUrl
                    ];
                }
            }

            $response = Http::baseUrl('https://api.openai.com/v1')
                ->withToken($apiKey)
                ->post('images/edits', [
                'model' => 'gpt-image-1.5',
                'images' => $processedImages,
                'prompt' => $prompt,
                'n' => $n,
                'size' => $size
            ])->json();

            return $response;
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

    private function prepareImage(string $imageId): string
    {
        $media = Media::find($imageId);
        $imageUrl = GetImgProxyUrl::run($media->getImage());

        if(app()->isLocal()) {
            $imageUrl = 'https://media.aiku.io/Odwk6eyl1y8D32Y9yQn98Ant9rD09RSGOcyrXEPeOhU/rs::0:600::/bG9jYWw6Ly9tZWRpYS8xUi9DRC82MFIzMEMxRzZNVktDRDFSL2Y5NGJhNDQ1LmpwZWc';
        }

        return $imageUrl;
    }

    public $commandSignature = 'ai:generate-images {media_id}';

    public function asCommand(Command $command): void
    {
        $mediaId = $command->argument('media_id');

        $result = $this->handle('make this image better with stunning background', [
            'images' => [$mediaId],
        ]);

        dd($result);
    }

    public function action(string $prompt, array $metadata = []): array
    {
        $this->asAction = true;

        return $this->handle($prompt, $metadata);
    }
}
