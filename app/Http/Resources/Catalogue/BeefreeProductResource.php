<?php

/*
 * Author: eka yudinata <ekayudintha@gmail.com>
 * Created: Thu, 24 Apr 2026
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $description
 * @property mixed $web_images
 */
class BeefreeProductResource extends JsonResource
{
    private function truncateHtml(string $html, int $maxChars): string
    {
        $textOnly = strip_tags($html);
        if (mb_strlen($textOnly) <= $maxChars) {
            return $html;
        }

        $charCount = 0;
        $result = '';
        $tagStack = [];
        $i = 0;
        $length = mb_strlen($html);

        while ($i < $length && $charCount < $maxChars) {
            $char = mb_substr($html, $i, 1);

            if ($char === '<') {
                // Find end of tag
                $tagEnd = mb_strpos($html, '>', $i);
                if ($tagEnd === false) {
                    $result .= $char;
                    $i++;
                    continue;
                }

                $tag = mb_substr($html, $i, $tagEnd - $i + 1);
                $result .= $tag;

                // Parse tag name
                $inner = mb_substr($tag, 1, mb_strlen($tag) - 2);
                $isClosing = mb_substr($inner, 0, 1) === '/';
                $isSelfClosing = mb_substr($inner, -1) === '/' || preg_match('/^(br|hr|img|input|meta|link)(\s|$)/i', ltrim($inner, '/'));

                if (!$isSelfClosing) {
                    preg_match('/([a-zA-Z][a-zA-Z0-9]*)/', $inner, $matches);
                    $tagName = isset($matches[1]) ? strtolower($matches[1]) : '';

                    if ($tagName) {
                        if ($isClosing) {
                            // Pop from stack
                            $pos = array_search($tagName, array_reverse($tagStack, true));
                            if ($pos !== false) {
                                array_splice($tagStack, $pos, 1);
                            }
                        } else {
                            $tagStack[] = $tagName;
                        }
                    }
                }

                $i = $tagEnd + 1;
            } else {
                $result .= $char;
                $charCount++;
                $i++;
            }
        }

        // Close any open tags
        foreach (array_reverse($tagStack) as $openTag) {
            $result .= "</{$openTag}>";
        }

        return $result . "...";
    }

    public function toArray($request): array
    {
        // Handle web_images JSON decoding (same as ProductsWebpageResource)
        if (!is_array($this->web_images)) {
            $webImages = json_decode(trim($this->web_images, '"'), true) ?? [];
        } else {
            $webImages = $this->web_images;
        }

        $productImage = Arr::get($this?->imageSources(200, 200), 'png', '');

        return [
            'id'            => $this->id,
            'slug'          => $this->slug,
            'code'          => $this->code,
            'name'          => $this->name,
            'description'   => $this->truncateHtml($this->description ?? '', 300),
            'web_images'    => $webImages,
            'product_image' => $productImage,
            'url'           => $this->webpage?->getCanonicalUrl(),
        ];
    }
}
