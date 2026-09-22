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

    /**
     * A separate profile for received email, which is nothing like editor content: it is built
     * out of nested tables with inline styles, and stripping that leaves an unreadable column of
     * words. Staff compare what they see here against Gmail, so it has to look like the message
     * that was sent.
     *
     * Everything that can execute is refused: no script, no iframe, no object, no svg, no event
     * handlers, and only http, https and mailto links. The rendered output is additionally put
     * inside a sandboxed frame, so this is the first of two barriers rather than the only one.
     */
    public function cleanEmail(string|null $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $config = $this->baseConfig();

        $config->set('Cache.DefinitionImpl', null);
        $config->set('AutoFormat.RemoveEmpty', false);
        $config->set('HTML.Allowed', implode(',', [
            'a', 'b', 'strong', 'i', 'em', 'u', 's', 'br', 'hr', 'p', 'div', 'span', 'center',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd',
            'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col',
            'img', 'font', 'small', 'big', 'sub', 'sup',
        ]));

        $config->set('HTML.AllowedAttributes', [
            '*.style', '*.class', '*.align', '*.width', '*.height',
            'a.href', 'a.title', 'a.target',
            'img.src', 'img.alt', 'img.title', 'img.width', 'img.height', 'img.border',
            'table.border', 'table.cellpadding', 'table.cellspacing', 'table.bgcolor',
            'td.colspan', 'td.rowspan', 'td.valign', 'td.bgcolor',
            'th.colspan', 'th.rowspan', 'th.valign', 'th.bgcolor',
            'tr.valign', 'tr.bgcolor',
            'col.span', 'colgroup.span',
            'font.color', 'font.face', 'font.size',
        ]);

        $config->set('CSS.AllowedProperties', [
            'color', 'background-color', 'background',
            'font-size', 'font-family', 'font-weight', 'font-style', 'line-height',
            'text-align', 'text-decoration', 'text-transform', 'letter-spacing',
            'width', 'height', 'max-width', 'min-width',
            'margin', 'margin-top', 'margin-bottom', 'margin-left', 'margin-right',
            'padding', 'padding-top', 'padding-bottom', 'padding-left', 'padding-right',
            'border', 'border-top', 'border-bottom', 'border-left', 'border-right',
            'border-color', 'border-style', 'border-width', 'border-collapse',
            'vertical-align', 'float', 'clear',
            // border-radius and display are absent from this purifier's css definition and it
            // refuses to start when asked to allow them.
        ]);

        $config->set('URI.AllowedSchemes', [
            'http'   => true,
            'https'  => true,
            'mailto' => true,
        ]);

        $config->set('HTML.SafeIframe', false);
        $config->set('HTML.ForbiddenElements', ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'form', 'input', 'button', 'link', 'meta', 'base']);

        // A stranger's link opens without handing them the page it came from.
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.TargetNoreferrer', true);
        $config->set('HTML.TargetNoopener', true);

        return $this->dropTrackingPixels((new HTMLPurifier($config))->purify($html));
    }

    /**
     * A one pixel image exists to tell the sender the message was opened. Nothing is lost by
     * refusing to fetch it, and the agent is not reporting for a stranger.
     */
    private function dropTrackingPixels(string $html): string
    {
        return preg_replace(
            // The value must be the whole number, or height="220" reads as a 2 and a real
            // picture is thrown out with the pixels.
            '/<img\\b[^>]*\\b(?:width|height)\\s*=\\s*(?:"[0-2]"|\'[0-2]\'|[0-2](?=[\\s>]))[^>]*>/i',
            '',
            $html
        ) ?? $html;
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
