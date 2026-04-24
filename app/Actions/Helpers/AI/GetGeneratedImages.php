<?php

namespace App\Actions\Helpers\AI;

use App\Actions\Catalogue\Product\UploadImagesToProduct;
use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\OrgAction;
use App\Actions\Traits\WithBase64FileConverter;
use App\Actions\Traits\WithUploadModelImages;
use App\Events\GenerateGptImagesProgressEvent;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use OpenAI;

class GetGeneratedImages extends OrgAction
{
    use WithUploadModelImages;
    use WithBase64FileConverter;

    public int $jobTries = 3;
    public string $jobQueue = 'default-long';

    public function handle(Product $model, string $prompt, array $metadata = []): void
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
                ->timeout(30)
                ->withToken($apiKey)
                ->post('images/edits', [
                'model' => 'gpt-image-1.5',
                'images' => $processedImages,
                'prompt' => $prompt,
                'n' => $n,
                'size' => $size
            ])->json();
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

        $result = collect($response['data'])->map(function ($image) use ($model) {
            return $this->convertBase64ToFile($image['b64_json'], $model);
        })->toArray();

        $uploadedImages = UploadImagesToProduct::run($model, 'images', [
            'images' => $result
        ]);

        GenerateGptImagesProgressEvent::dispatch($uploadedImages, $model->exclusive_for_customer_id);
    }

    private function prepareImage(string $imageId): string
    {
        $media = Media::find($imageId);
        $imageUrl = GetImgProxyUrl::run($media->getImage());

        if (app()->isLocal()) {
            $imageUrl = 'https://media.aiku.io/Odwk6eyl1y8D32Y9yQn98Ant9rD09RSGOcyrXEPeOhU/rs::0:600::/bG9jYWw6Ly9tZWRpYS8xUi9DRC82MFIzMEMxRzZNVktDRDFSL2Y5NGJhNDQ1LmpwZWc';
        }

        return $imageUrl;
    }

    public $commandSignature = 'ai:generate-images {product_id} {media_id}';

    public function asCommand(Command $command): void
    {
        $mediaId = $command->argument('media_id');
        $productId = $command->argument('product_id');
        $model = Product::findOrFail($productId);

        $result = $this->handle($model, 'make this image better with stunning background', [
            'images' => [$mediaId]
        ]);

        dd($result);
    }

    public function action(Product $model, string $prompt, array $metadata = []): void
    {
        $this->asAction = true;

        $this->handle($model, $prompt, $metadata);
    }
}
