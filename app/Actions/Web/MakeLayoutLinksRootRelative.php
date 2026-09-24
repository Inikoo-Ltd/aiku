<?php

/*
 * Created: Tue, 22 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web;

use Lorisleiva\Actions\Concerns\AsObject;

class MakeLayoutLinksRootRelative
{
    use AsObject;

    /**
     * Workshop editors save link hrefs as typed, so a page is often stored as `faq`.
     * The browser resolves that against the current directory, which on a retina page
     * such as /app/orders/ro007958 asks for /app/orders/faq instead of /faq.
     */
    public function handle(array $data): array
    {
        return $this->walk($data);
    }

    private function walk(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_contains($value, 'href=') ? $this->rewriteHrefs($value) : $value;
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->walk($item), $value);
        }

        return $value;
    }

    private function rewriteHrefs(string $html): string
    {
        return preg_replace_callback(
            '#(href\s*=\s*)(["\'])(.*?)\2#i',
            fn (array $matches) => $matches[1].$matches[2].$this->rootRelative($matches[3]).$matches[2],
            $html
        );
    }

    /**
     * Anything already anchored, querying, absolute, protocol relative or carrying a
     * scheme such as mailto: and tel: is left exactly as the editor wrote it.
     */
    public function rootRelative(string $href): string
    {
        $trimmed = trim($href);

        if ($trimmed === '' || preg_match('#^([a-z][a-z0-9+.-]*:|//|/|\#|\?)#i', $trimmed)) {
            return $href;
        }

        return '/'.$trimmed;
    }
}
