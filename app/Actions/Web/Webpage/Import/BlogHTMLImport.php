<?php

/*
 * Author Louis Perez
 * Created on 30-07-2026-15h-16m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\Webpage\Import;

use App\Actions\Web\ModelHasWebBlocks\UpdateModelHasWebBlocks;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Actions\Web\Webpage\UpdateWebpage;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Web\Webpage;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Maatwebsite\Excel\Concerns\ToCollection;

class BlogHTMLImport implements ToCollection
{
    use RemembersRowNumber;

    private Shop $shop;

    public function __construct(Shop $shop)
    {
        $this->shop = $shop;
    }

    /**
     * @throws \Throwable
     */
    public function collection(Collection $collection): void
    {
        $website = $this->shop->website;
        $first = true;
        foreach ($collection as $row) {
            if ($first) {
                $first = false;

                // To get column number, handling different csv format, just edge cases
                $arrayRow = $row->toArray();
                $columnNumber = array_flip($arrayRow);


                $entryIdColumnPos           = data_get($columnNumber, 'feedentry_id', data_get($columnNumber, 'entry_id')); // Source
                $entryTypeColumnPos         = data_get($columnNumber, 'entry_type'); // need, filter by POST
                $statusColumnPos            = data_get($columnNumber, 'status'); // for status, live or not
                $titleColumnPos             = data_get($columnNumber, 'title'); // Need
                // $authorColumnPos            = data_get($columnNumber, 'author_name'); // ignore
                // author_uri,
                // author_type,
                $createdColumnPos           = data_get($columnNumber, 'created'); // need
                $publishedColumnPos         = data_get($columnNumber, 'published'); // need
                // $updatedColumnPos           = data_get($columnNumber, 'updated'); // ignore
                $filename                   = data_get($columnNumber, 'filename'); // fallback for title
                // $metaDescColumnPos          = data_get($columnNumber, 'meta_description'); // ignore
                // location,
                $seoCategoryColumnPos       = data_get($columnNumber, 'categories'); // need, for safekeeping
                // links,
                // enclosures,
                // parent,
                // in_reply_to,
                // trashed,
                $contentColumnPos           = data_get($columnNumber, 'content_html'); // need
                // content_text

                continue;
            }

            if (strtoupper($row[$entryTypeColumnPos] ?? '') !== 'POST') {
                continue;
            }

            $title = $row[$titleColumnPos];
            if (!$title) {
                preg_match('#/\d{4}/\d{2}/(.*?)\.html$#', $row[$filename], $matches);
                $title = $matches[1] ?? null;
            }

            if (!$title) {
                continue;
            }

            $dateParser = function ($item) {
                return Carbon::parse($item);
            };

            $url      = Carbon::parse($row[$createdColumnPos])->format('m-d-Y');
            $sourceId = $row[$entryIdColumnPos];

            $content = $row[$contentColumnPos];

            $imgPreviewLink = null;
            // used ai for this regex to get src of the first image as a preview
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches)) {
                $imgPreviewLink = $matches[1];
            }

            $modelData = [
                'title'         => $title,
                'code'          => $url,
                'url'           => $url,
                'type'          => WebpageTypeEnum::BLOG,
                'sub_type'      => WebpageSubTypeEnum::NEWSLETTERS,
                'fieldValue'    => [
                    'title'                         => $row[$titleColumnPos],
                    'published_date'                => $row[$publishedColumnPos],
                    'image'                         => [
                        'source'    => null,
                        'alt'       => null,
                    ],
                    'content'                       => $content,
                    'third_party_image_preview'     => $imgPreviewLink,
                ],
                'seo_data'      => [
                    'structured_data'   => array_map('trim', explode('|', $row[$seoCategoryColumnPos]))
                ],
                // Required w/o strict
                'source_id'     => $sourceId,
                'created_at'    => $dateParser($row[$createdColumnPos]),
                'published_at'  => $dateParser($row[$publishedColumnPos]),
                'fetched_at'    => now(),
                'fromCSV'       => true,
            ];

            $existingWebpage = Webpage::where('website_id', $website->id)
                ->where(function ($query) use ($sourceId, $url) {
                    $query->where('source_id', $sourceId)
                        ->orWhere('url', $url);
                })
                ->first();

            $webpage = $existingWebpage
                ? $this->replaceWebpage($existingWebpage, $modelData)
                : StoreWebpage::make()->action($website, $modelData, strict: false);

            if (strtoupper($row[$statusColumnPos]) == 'LIVE') {
                PublishWebpage::make()->action($webpage, [
                    'comment'   => 'Read from CSV and auto published'
                ]);
            }
        }
    }

    /**
     * @throws \Throwable
     */
    private function replaceWebpage(Webpage $webpage, array $modelData): Webpage
    {
        $webpage->update(['seo_data' => $modelData['seo_data']]);

        $updateData = [
            'title'    => $modelData['title'],
            'sub_type' => $modelData['sub_type'],
        ];

        if (!$webpage->source_id) {
            $updateData['source_id'] = $modelData['source_id'];
        }

        $webpage = UpdateWebpage::make()->action($webpage, $updateData, strict: false);

        $blogModelHasWebBlock = $webpage->modelHasWebBlocks()
            ->whereHas('webBlock.webBlockType', function ($query) {
                $query->where('code', 'blog');
            })
            ->first();

        if ($blogModelHasWebBlock) {
            UpdateModelHasWebBlocks::make()->action($blogModelHasWebBlock, [
                'layout' => [
                    'data' => [
                        'fieldValue' => $modelData['fieldValue']
                    ]
                ]
            ]);
        }

        return $webpage;
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
