<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\ModelHasWebBlocks;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Console\Commands\FetchMetaSocialInsights;
use App\Enums\CRM\SocialMedia\SocialMediaPlatformEnum;
use App\Enums\CRM\SocialMedia\SocialMediaPostTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\SocialMediaPost;
use App\Models\Dropshipping\ModelHasWebBlocks;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

/**
 * Turns a pasted Instagram reel or TikTok link into a video source for the video carousel block.
 *
 * Instagram has no player other websites can control, so the reel's MP4 and cover are copied to R2
 * through the Graph API with the token of the shop whose account posted it (the same token
 * FetchMetaSocialInsights uses), sent as a header so it never appears in a URL or an error. Only the
 * media_url Meta returns is downloaded, never a URL from the request, and only for a synced reel or
 * video whose permalink has exactly the pasted shortcode. Timeouts keep the whole import inside
 * Octane's 45 s request limit, and a download stops as soon as it passes MAX_VIDEO_BYTES. The reel is found among the posts already synced for the group, else in the latest posts
 * of the block's own shop. Meta withholds media_url for some reels (licensed music); those are
 * refused with a request to upload the file.
 *
 * TikTok links keep the video id for TikTok's embed player; only the cover is copied.
 */
class ImportSocialVideoToModelHasWebBlocks extends OrgAction
{
    use WithWebEditAuthorisation;

    public const int MAX_VIDEO_BYTES = 100 * 1024 * 1024;

    public const int LATEST_MEDIA_PAGES = 3;

    public const int GRAPH_TIMEOUT = 6;

    public const int VIDEO_TIMEOUT = 20;

    public const int POSTER_TIMEOUT = 5;

    /**
     * @return array{platform: string, video_url: string|null, poster_url: string|null, permalink: string, tiktok_id?: string}
     */
    public function handle(Shop $shop, string $url): array
    {
        if ($tiktokId = $this->tiktokId($url)) {
            return $this->fromTikTok($url, $tiktokId);
        }

        if ($shortcode = $this->instagramShortcode($url)) {
            return $this->fromInstagram($shop, $shortcode);
        }

        throw ValidationException::withMessages(['url' => __('Paste the link of an Instagram reel or of a TikTok video.')]);
    }

    public function tiktokId(string $url): ?string
    {
        return preg_match('#^https?://(?:www\.|m\.)?tiktok\.com/@[^/]+/video/(\d+)#i', trim($url), $matches) ? $matches[1] : null;
    }

    public function instagramShortcode(string $url): ?string
    {
        return preg_match('#^https?://(?:www\.)?instagram\.com/(?:[^/]+/)?(?:reel|reels|p|tv)/([A-Za-z0-9_-]{8,})(?:[/?\#]|$)#i', trim($url), $matches) ? $matches[1] : null;
    }

    private function fromTikTok(string $url, string $tiktokId): array
    {
        $posterUrl = null;
        try {
            $thumbnail = Http::timeout(self::GRAPH_TIMEOUT)->get('https://www.tiktok.com/oembed', ['url' => $url])->json('thumbnail_url');
            if (is_string($thumbnail) && $thumbnail !== '') {
                $posterUrl = $this->copyToR2($thumbnail, 'jpg', self::POSTER_TIMEOUT);
            }
        } catch (Throwable) {
            $posterUrl = null;
        }

        return [
            'platform'   => 'tiktok',
            'tiktok_id'  => $tiktokId,
            'video_url'  => null,
            'poster_url' => $posterUrl,
            'permalink'  => $url,
        ];
    }

    private function fromInstagram(Shop $shop, string $shortcode): array
    {
        $media = $this->instagramMedia($shop, $shortcode);

        if (!$media) {
            throw ValidationException::withMessages(['url' => __('This reel is not on an Instagram account connected to our shops. Upload the video file instead.')]);
        }

        if (($media['media_type'] ?? null) !== 'VIDEO') {
            throw ValidationException::withMessages(['url' => __('This Instagram post is not a video. Paste the link of a reel.')]);
        }

        if (!filled($media['media_url'] ?? null)) {
            throw ValidationException::withMessages(['url' => __('Instagram does not share the video of this reel (it may use licensed music). Upload the video file instead.')]);
        }

        return [
            'platform'   => 'instagram',
            'video_url'  => $this->copyToR2($media['media_url'], 'mp4', self::VIDEO_TIMEOUT),
            'poster_url' => $this->posterFromInstagram($media['thumbnail_url'] ?? null),
            'permalink'  => $media['permalink'] ?? 'https://www.instagram.com/reel/'.$shortcode.'/',
        ];
    }

    private function instagramMedia(Shop $shop, string $shortcode): ?array
    {
        $fields = 'id,media_type,permalink,media_url,thumbnail_url';

        $post = SocialMediaPost::query()
            ->whereHas('socialMediaAccount', fn ($query) => $query
                ->where('group_id', $shop->group_id)
                ->where('platform', SocialMediaPlatformEnum::INSTAGRAM))
            ->whereIn('type', [SocialMediaPostTypeEnum::REEL, SocialMediaPostTypeEnum::VIDEO])
            ->where('permalink', 'like', '%/'.addcslashes($shortcode, '_%\\').'/%')
            ->with('socialMediaAccount.shop')
            ->latest('published_at')
            ->get()
            ->first(fn (SocialMediaPost $post) => $this->instagramShortcode((string) $post->permalink) === $shortcode);

        if ($post) {
            return $this->graph($post->external_id, ['fields' => $fields], FetchMetaSocialInsights::token($post->socialMediaAccount->shop));
        }

        $instagramId = data_get($shop->settings, 'meta_social.instagram_account_id');
        if (!$instagramId) {
            return null;
        }

        $token = FetchMetaSocialInsights::token($shop);
        $after = null;
        for ($page = 0; $page < self::LATEST_MEDIA_PAGES; $page++) {
            $response = $this->graph($instagramId.'/media', array_filter(['fields' => $fields, 'limit' => 50, 'after' => $after]), $token);

            foreach ($response['data'] ?? [] as $media) {
                if ($this->instagramShortcode((string) ($media['permalink'] ?? '')) === $shortcode) {
                    return $media;
                }
            }

            $after = data_get($response, 'paging.cursors.after');
            if (!$after || !data_get($response, 'paging.next')) {
                break;
            }
        }

        return null;
    }

    private function graph(string $path, array $query, string $token): array
    {
        try {
            $response = Http::withToken($token)->timeout(self::GRAPH_TIMEOUT)->get(
                'https://graph.facebook.com/'.config('services.meta_ads.api_version').'/'.$path,
                $query
            );
        } catch (Throwable) {
            throw ValidationException::withMessages(['url' => __('Instagram did not answer in time. Please try again.')]);
        }

        if ($response->failed()) {
            throw ValidationException::withMessages(['url' => __('Instagram did not answer: :message', ['message' => data_get($response->json(), 'error.message', $response->status())])]);
        }

        return $response->json() ?? [];
    }

    private function posterFromInstagram(?string $thumbnailUrl): ?string
    {
        if (!filled($thumbnailUrl)) {
            return null;
        }

        try {
            return $this->copyToR2($thumbnailUrl, 'jpg', self::POSTER_TIMEOUT);
        } catch (ValidationException) {
            return null;
        }
    }

    private function copyToR2(string $sourceUrl, string $extension, int $timeout): string
    {
        $path = tempnam(sys_get_temp_dir(), 'web-video-');

        try {
            try {
                $response = Http::timeout($timeout)
                    ->withOptions(['progress' => function (int $total, int $downloaded): void {
                        if ($total > self::MAX_VIDEO_BYTES || $downloaded > self::MAX_VIDEO_BYTES) {
                            throw new \RuntimeException('too big');
                        }
                    }])
                    ->sink($path)
                    ->get($sourceUrl);
            } catch (Throwable) {
                $response = null;
            }

            clearstatcache(true, $path);
            if (!$response || $response->failed() || filesize($path) === 0) {
                throw ValidationException::withMessages(['url' => __('The video could not be copied. Upload the video file instead.')]);
            }

            return StoreWebBlockVideoFile::run($path, $extension);
        } finally {
            @unlink($path);
        }
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:1000'],
        ];
    }

    public function asController(ModelHasWebBlocks $modelHasWebBlocks, ActionRequest $request): array
    {
        $this->initialisationFromShop($modelHasWebBlocks->shop, $request);

        return $this->handle($modelHasWebBlocks->shop, $this->validatedData['url']);
    }
}
