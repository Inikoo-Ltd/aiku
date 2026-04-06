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

class GetGeneratedProductTitle extends OrgAction
{
    public function handle(string $prompt, array $metadata = []): string
    {
        $client   = OpenAI::client(config('services.openai.api_key'));

        $language = $metadata['language'] ?? 'English';
        $tone     = $metadata['tone'] ?? 'professional';
        $images   = $metadata['images'] ?? [];

        $systemPrompt = "You are a product copywriter. Generate ONLY a short product title in {$language} with a {$tone} tone. No explanation, no punctuation at the end, just the title.";

        $userContent = [];

        foreach ($images as $image) {
            $userContent[] = [
                'type'      => 'image_url',
                'image_url' => ['url' => $this->prepareImageContent($image)],
            ];
        }

        $userContent[] = [
            'type' => 'text',
            'text' => $prompt ?: 'Generate a product title based on the provided images.',
        ];

        $response = $client->chat()->create([
            'model'      => 'gpt-4o',
            'messages'   => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userContent],
            ],
            'max_tokens' => 100,
        ]);

        return trim($response->choices[0]->message->content);
    }

    private function prepareImageContent(string $image): string
    {
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        if (file_exists($image)) {
            $mime = mime_content_type($image);
            $data = base64_encode(file_get_contents($image));
            return "data:{$mime};base64,{$data}";
        }

        return "data:image/png;base64,{$image}";
    }

    public function action(string $prompt, array $metadata = []): string
    {
        $this->asAction = true;

        return $this->handle($prompt, $metadata);
    }
}
