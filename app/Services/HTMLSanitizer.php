<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class HTMLSanitizer
{
    private function baseConfig(): HTMLPurifier_Config
    {
        $config = HTMLPurifier_Config::createDefault();

        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set(
            'Cache.SerializerPath',
            storage_path('app/htmlpurifier')
        );

        if (!is_dir(storage_path('app/htmlpurifier'))) {
            mkdir(storage_path('app/htmlpurifier'), 0755, true);
        }

        return $config;
    }

    public function cleanHTML(string|null $html): string
    {
        if ($html === null) {
            $html = '';
        }

        $config = $this->baseConfig();

        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.Allowed', implode(',', [
            'h1',
            'h2',
            'h3',
            'p',
            'span',
            'strong',
            'em',
            'u',
            'mark',
            'ul',
            'ol',
            'li',
            'blockquote',
            'hr',
            'table',
            'thead',
            'tbody',
            'tr',
            'th',
            'td',
            'colgroup',
            'col',
            'div',
            'iframe',
            'img'
        ]));

        $config->set('HTML.AllowedAttributes', [
            '*.style',
            // table attributes
            'th.colspan',
            'th.rowspan',
            'td.colspan',
            'td.rowspan',
            // iFrame attributes
            'iframe.src',
            'iframe.width',
            'iframe.height',
            'iframe.allowfullscreen',
            'iframe.disablekbcontrols',
            'iframe.enableiframeapi',
            'iframe.loop',
            'iframe.start',
            'iframe.endtime',
            'iframe.ivloadpolicy',
            'iframe.rel',
            'iframe.modestbranding',
            'iframe.origin',
            'iframe.playlist',
            // IMG attributes
            'img.src',
            'img.alt',
            'img.width',
            'img.height',
            // Mark attributes
            'mark.data-color',
            // Div attributes
            'div.data-youtube-video'
        ]);

        $config->set('URI.AllowedSchemes', [
            'http'  => true,
            'https' => true
        ]);

        $config->set('CSS.AllowedProperties', [
            'color',
            'background-color',
            'font-size',
            'font-family',
            'text-align',
            'min-width',
            'width',
            'height'
        ]);

        $config->set('HTML.SafeIframe', true);
        $config->set(
            'URI.SafeIframeRegexp',
            '#^https://(www\.)?youtube\.com/embed/#'
        );

        $config->set('URI.AllowedSchemes', [
            'http'  => true,
            'https' => true
        ]);

        $config->set('HTML.ForbiddenElements', ['svg']);

        if ($def = $config->getHTMLDefinition(true)) {
            $def->addAttribute('iframe', 'allowfullscreen', 'Bool');
            $def->addAttribute('iframe', 'autoplay', 'Bool');
            $def->addAttribute('iframe', 'disablekbcontrols', 'Bool');
            $def->addAttribute('iframe', 'enableiframeapi', 'Bool');
            $def->addAttribute('iframe', 'loop', 'Bool');
            $def->addAttribute('iframe', 'start', 'Number');
            $def->addAttribute('iframe', 'endtime', 'Number');
            $def->addAttribute('iframe', 'width', 'Number');
            $def->addAttribute('iframe', 'height', 'Number');
            $def->addAttribute('iframe', 'ivloadpolicy', 'Number');
            $def->addAttribute('iframe', 'rel', 'Number');
            $def->addAttribute('iframe', 'modestbranding', 'Text');
            $def->addAttribute('iframe', 'origin', 'Text');
            $def->addAttribute('iframe', 'playlist', 'Text');

            $def->addAttribute('img', 'src', 'URI');
            $def->addAttribute('img', 'alt', 'Text');
            $def->addAttribute('img', 'width', 'Number');
            $def->addAttribute('img', 'height', 'Number');

            $def->addAttribute('div', 'data-youtube-video', 'Text');

            $def->addElement(
                'mark',
                'Inline',
                'Inline',
                'Common',
                [
                    'style'      => 'Text',
                    'data-color' => 'Text',
                ]
            );
        }

        return (new HTMLPurifier($config))->purify($html);
    }
}
