<?php

namespace App\Actions\Web\Redirect\Traits;

use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

trait WithStoreRedirect
{
    abstract public function set(string $key, mixed $value): static;

    public function prepareForValidation(ActionRequest $request)
    {
        $initialFromUrl = $request->input('from_url') ?? $this->from_url;

        if ($initialFromUrl) {
            if ($request->website) {
                $website = $request->website;
            } elseif ($request->webpage) {
                $website = $request->webpage->website;
            } elseif ($this->shop) {
                $website = $this->shop->website;
            }

            $fromUrl = preg_replace('/\s+/', '', ($initialFromUrl)); // Sanitize whitespace
            $fromUrl = preg_replace('#/+#', '/', $fromUrl); // Collapse slash
            $fromUrl = trim($fromUrl, '/'); // Sanitize start & end slash
            $fromPath = array_last(explode('/', $fromUrl)); // Get last path

            $url = 'https://' . $website->domain . '/' . $fromUrl;

            $this->set('from_url', $url);
            $this->set('from_path', $fromPath);
        }
    }

    public function afterValidator(Validator $validator, ActionRequest $request)
    {
        if ($validator->errors()->has('from_path')) {
            foreach ($validator->errors()->get('from_path') as $message) {
                $validator->errors()->add('from_url', $message);
            }
        }
    }

    public function getValidationMessages(): array
    {
        return [
            'from_path.unique' => 'The last part of the URL (after "/") is already used on another redirect/webpage. Try a different ending',
        ];
    }

}
