<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Chat\Staff\Slack\GetSlackChannelMessages;
use App\Actions\Chat\Staff\UI\ShowSlackMessageFile;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackFile;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $web = Website::has('shop')->first();
    if (!$web) {
        [$this->organisation, $this->user, $this->shop] = createShop();
    } else {
        $this->organisation = $web->organisation;
        $this->user         = createAdminGuest($this->organisation->group)->getUser();
    }
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    Config::set('img-proxy.base_url', 'http://imgproxy.test');
    Config::set('img-proxy.internal_url', null);
    Config::set('filesystems.disks.staff-chat-r2.bucket', 'aiku-staff-chat');
    Storage::fake(config('media-library.disk_name'));
    Storage::fake('staff-chat-r2');
    Http::preventStrayRequests();

    $this->s     = Str::upper(Str::random(6));
    $this->group = $this->organisation->group;

    $this->newUser = fn (array $attributes = []) => User::factory()->create(array_merge(['group_id' => $this->group->id, 'language_id' => $this->user->language_id], $attributes));
    $this->viewer  = ($this->newUser)();
    SlackUser::create(['slack_id' => "UFV{$this->s}", 'user_id' => $this->viewer->id, 'email' => $this->viewer->email, 'real_name' => 'File Viewer']);

    $this->channel = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CFILES{$this->s}", 'name' => "files-{$this->s}", 'is_member' => true]);
    $this->ignored = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CFIGN{$this->s}", 'name' => "files-ignored-{$this->s}", 'is_ignored' => true]);
    $this->private = SlackChannel::create(['group_id' => $this->group->id, 'slack_id' => "CFPRIV{$this->s}", 'name' => "files-private-{$this->s}", 'is_public' => false]);

    $this->sequence = 0;
    $this->message  = function (SlackChannel $channel, array $files, string $text = 'see attached'): SlackMessage {
        $this->sequence++;

        return SlackMessage::create([
            'slack_channel_id' => $channel->id,
            'ts'               => sprintf('%d.%06d', now()->timestamp, $this->sequence),
            'text'             => $text,
            'sent_at'          => now()->addSeconds($this->sequence),
            'data'             => ['files' => $files],
        ]);
    };
    $this->entry = fn (string $key, string $name, string $mime, array $extra = []) => array_merge([
        'id'                   => "F{$this->s}".strtoupper($key),
        'name'                 => $name,
        'mimetype'             => $mime,
        'size'                 => 1234,
        'url_private'          => 'https://files.slack.com/files-pri/T1-F1/'.rawurlencode($name),
        'url_private_download' => 'https://files.slack.com/files-pri/T1-F1/download/'.rawurlencode($name),
        'thumb_360'            => 'https://files.slack.com/files-tmb/T1-F1/thumb_360.png',
        'permalink'            => 'https://example.slack.com/files/U1/F1/'.rawurlencode($name),
    ], $extra);
    $this->row = fn (array $file, string $state, array $extra = []) => SlackFile::create(array_merge([
        'group_id' => $this->group->id, 'slack_id' => $file['id'], 'name' => $file['name'], 'mime_type' => $file['mimetype'], 'size' => $file['size'], 'state' => $state, 'data' => $file,
    ], $extra));
    $this->copied = function (array $file, string $contents) {
        $path = tempnam(sys_get_temp_dir(), 'slack-file-test-');
        file_put_contents($path, $contents);
        $slackFile = ($this->row)($file, 'copied', ['size' => strlen($contents)]);
        $media     = StoreMediaFromFile::run($slackFile, ['path' => $path, 'originalName' => $file['name'], 'extension' => pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin', 'checksum' => md5_file($path)], 'slack_files', str_starts_with($file['mimetype'], 'image/') ? 'image' : 'file');
        $slackFile->update(['media_id' => $media->id]);
        unlink($path);

        return $slackFile;
    };
    $this->png = function (): string {
        $image = imagecreatetruecolor(40, 30);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    };
    $this->url = fn (SlackMessage $message, string $fileId, array $query = []) => route('grp.chat.staff.slack.files.show', ['slackMessage' => $message->id, 'slackFile' => $fileId] + $query);
});

test('the files of a Slack message are listed under it with their state, one query for the page, and nothing of Slack or of the large files disk in the page, INI-436', function () {
    $photo    = ($this->entry)('photo', 'parcel.png', 'image/png');
    $pdf      = ($this->entry)('pdf', 'prices.pdf', 'application/pdf');
    $video    = ($this->entry)('video', 'induction.mp4', 'video/mp4');
    $waiting  = ($this->entry)('waiting', 'later.png', 'image/png');
    $failed   = ($this->entry)('failed', 'failed.docx', 'application/msword');
    $huge     = ($this->entry)('huge', 'backup.zip', 'application/zip', ['size' => 3 * 1024 ** 3]);
    $gone     = ($this->entry)('gone', 'old.jpg', 'image/jpeg', ['mode' => 'tombstone']);
    $goneNoRow = ($this->entry)('gonenorow', 'older.jpg', 'image/jpeg', ['file_access' => 'file_not_found']);
    $doc      = ($this->entry)('doc', 'Returns procedure', 'application/vnd.google-apps.document', ['mode' => 'external', 'external_url' => 'https://docs.google.com/document/d/abc/edit', 'url_private' => 'https://docs.google.com/document/d/abc/edit']);
    $badLink  = ($this->entry)('bad', 'Odd link', 'text/plain', ['mode' => 'external', 'external_url' => 'javascript:alert(1)', 'url_private' => 'javascript:alert(1)']);
    $mailLink = ($this->entry)('mail', 'Mail link', 'text/plain', ['mode' => 'external', 'external_url' => 'mailto:someone@example.com', 'url_private' => null]);

    ($this->copied)($photo, ($this->png)());
    ($this->copied)($pdf, '%PDF-1.4 sample');
    ($this->row)($video, 'copied', ['disk' => 'staff-chat-r2', 'path' => "slack-files/{$video['id']}/induction.mp4", 'size' => 240 * 1024 ** 2]);
    ($this->row)($failed, 'failed', ['reason' => 'http 500']);
    ($this->row)($huge, 'skipped', ['reason' => 'larger than 1 GB']);
    ($this->row)($gone, 'not_available', ['reason' => 'Slack shows it as tombstone']);
    ($this->row)($doc, 'external', ['mode' => 'external']);
    ($this->row)($badLink, 'external', ['mode' => 'external']);

    $message = ($this->message)($this->channel, [$photo, $pdf, $video, $waiting, $failed, $huge, $gone, $goneNoRow, $doc, $badLink, $mailLink, ['name' => 'no-id.txt'], 'not a file']);
    ($this->message)($this->channel, [$photo], 'shared again');
    ($this->message)($this->channel, [], 'no files');

    $page = null;
    actingAs($this->viewer)->get(route('grp.chat.staff.slack.show', $this->channel->id))->assertOk()
        ->assertInertia(function (AssertableInertia $inertia) use (&$page) {
            $inertia->loadDeferredProps(function (AssertableInertia $reload) use (&$page) {
                $page = $reload->toArray()['props']['slack_messages'];
            });
        });

    $rows  = collect($page['data'])->keyBy('id');
    $files = collect($rows[$message->id]['files'])->keyBy('name');
    $url   = ($this->url)($message, $photo['id']);

    expect($rows[$message->id]['files'])->toHaveCount(12)
        ->and($files['parcel.png'])->toBe(['id' => $photo['id'], 'name' => 'parcel.png', 'type' => 'PNG', 'size' => strlen(($this->png)()), 'state' => 'ready', 'note' => null, 'is_image' => true, 'opens_in_tab' => false, 'url' => $url, 'thumbnail_url' => $url.'?variant=thumbnail', 'external_url' => null])
        ->and($files['prices.pdf'])->toMatchArray(['type' => 'PDF', 'state' => 'ready', 'is_image' => false, 'opens_in_tab' => true, 'url' => ($this->url)($message, $pdf['id']), 'thumbnail_url' => null])
        ->and($files['induction.mp4'])->toMatchArray(['state' => 'ready', 'is_image' => false, 'size' => 240 * 1024 ** 2, 'url' => ($this->url)($message, $video['id']), 'thumbnail_url' => null])
        ->and($files['later.png'])->toMatchArray(['state' => 'pending', 'note' => 'Not copied from Slack yet', 'is_image' => false, 'url' => null, 'thumbnail_url' => null])
        ->and($files['failed.docx'])->toMatchArray(['state' => 'pending', 'url' => null])
        ->and($files['backup.zip'])->toMatchArray(['state' => 'skipped', 'note' => 'Too large to keep a copy', 'url' => null, 'size' => 3 * 1024 ** 3])
        ->and($files['old.jpg'])->toMatchArray(['state' => 'not_available', 'note' => 'No longer available', 'url' => null])
        ->and($files['older.jpg'])->toMatchArray(['state' => 'not_available', 'url' => null])
        ->and($files['Returns procedure'])->toMatchArray(['state' => 'external', 'url' => null, 'external_url' => 'https://docs.google.com/document/d/abc/edit'])
        ->and($files['Odd link'])->toMatchArray(['state' => 'external', 'url' => null, 'external_url' => null])
        ->and($files['Mail link'])->toMatchArray(['state' => 'external', 'external_url' => null])
        ->and($files['no-id.txt'])->toMatchArray(['id' => null, 'state' => 'pending', 'url' => null])
        ->and(json_encode($page))->not->toContain('files.slack.com')->not->toContain('slack.com/files')->not->toContain('javascript:')
            ->not->toContain('slack-files/')->not->toContain('expiration')->not->toContain('X-Amz');

    $slackFileQueries = function (int $perPage) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        GetSlackChannelMessages::make()->handle($this->channel, $perPage);
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, '"slack_files"'))->count();
        DB::disableQueryLog();

        return $queries;
    };
    expect($slackFileQueries(50))->toBe(1)->and($slackFileQueries(1))->toBeLessThanOrEqual(1);
});

test('a person who may see the channel gets the copy: an image shown, anything else as a download that can never run in aiku, a thumbnail from the resizer, INI-436', function () {
    $photo  = ($this->entry)('photo', 'parcel.png', 'image/png');
    $html   = ($this->entry)('html', 'news.html', 'text/html');
    $svg    = ($this->entry)('svg', 'logo.svg', 'image/svg+xml');
    $fake   = ($this->entry)('fake', 'really-html.png', 'image/png');
    $tricky = ($this->entry)('tricky', "../../etc/pass\\wd \"x\"\r\nSet-Cookie: a=b <b>½ %41.txt", 'text/plain');
    $emoji  = ($this->entry)('emoji', '📦', 'text/plain');
    ($this->copied)($photo, ($this->png)());
    ($this->copied)($html, '<html><script>alert(document.cookie)</script></html>');
    ($this->copied)($svg, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    ($this->copied)($fake, '<html><script>alert(1)</script></html>');
    ($this->copied)($tricky, 'text');
    ($this->copied)($emoji, 'text');
    $pdf     = ($this->entry)('pdf', 'prices.pdf', 'application/pdf');
    $fakePdf = ($this->entry)('fakepdf', 'really-html.pdf', 'application/pdf');
    ($this->copied)($pdf, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
    ($this->copied)($fakePdf, '<html><script>alert(1)</script></html>');
    $message = ($this->message)($this->channel, [$photo, $html, $svg, $fake, $tricky, $emoji, $pdf, $fakePdf]);

    actingAs($this->viewer)->get(($this->url)($message, $pdf['id']))->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Disposition', 'inline; filename=prices.pdf');
    actingAs($this->viewer)->get(($this->url)($message, $pdf['id'], ['variant' => 'thumbnail']))->assertNotFound();

    $shown = actingAs($this->viewer)->get(($this->url)($message, $photo['id']))->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Disposition', 'inline; filename=parcel.png');
    expect($shown->streamedContent())->toBe(($this->png)())
        ->and($shown->headers->get('Content-Security-Policy'))->toContain('sandbox')
        ->and($shown->headers->get('Cache-Control'))->toContain('private');

    foreach ([[$html, 'news.html'], [$svg, 'logo.svg'], [$fake, 'really-html.png'], [$fakePdf, 'really-html.pdf']] as [$file, $name]) {
        $download = actingAs($this->viewer)->get(($this->url)($message, $file['id']))->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'attachment; filename='.$name);
        expect($download->headers->get('Content-Security-Policy'))->toContain('sandbox');
        actingAs($this->viewer)->get(($this->url)($message, $file['id'], ['variant' => 'thumbnail']))->assertNotFound();
    }

    $disposition = actingAs($this->viewer)->get(($this->url)($message, $tricky['id']))->assertOk()->headers->get('Content-Disposition');
    expect($disposition)->toStartWith('attachment; filename=')
        ->not->toContain('/')->not->toContain('\\')->not->toContain('"x"')->not->toContain("\r")->not->toContain("\n")
        ->and(actingAs($this->viewer)->get(($this->url)($message, $emoji['id']))->assertOk()->headers->get('Content-Disposition'))->toStartWith('attachment; filename=');

    Http::fake(['imgproxy.test/*' => Http::response('small-webp', 200, ['Content-Type' => 'image/webp'])]);
    $thumbnail = actingAs($this->viewer)->get(($this->url)($message, $photo['id'], ['variant' => 'thumbnail']))->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($thumbnail->getContent())->toBe('small-webp')
        ->and($thumbnail->headers->get('Cache-Control'))->toContain('private')->not->toContain('public');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'rs:fit:'.ShowSlackMessageFile::THUMBNAIL_PIXELS.':'.ShowSlackMessageFile::THUMBNAIL_PIXELS) || str_contains($request->url(), ShowSlackMessageFile::THUMBNAIL_PIXELS.':'.ShowSlackMessageFile::THUMBNAIL_PIXELS));

    Http::swap(new \Illuminate\Http\Client\Factory());
    Http::preventStrayRequests();
    Http::fake(['imgproxy.test/*' => Http::response('<html>error</html>', 200, ['Content-Type' => 'text/html'])]);
    actingAs($this->viewer)->get(($this->url)($message, $photo['id'], ['variant' => 'thumbnail']))->assertNotFound();
});

test('a copy on the large files disk is answered with a redirect to a signed link made at the click, never cached, INI-491', function () {
    $video = ($this->entry)('video', 'Induction 2026.mp4', 'video/mp4');
    $path  = "slack-files/{$video['id']}/induction-2026.mp4";
    Storage::disk('staff-chat-r2')->put($path, 'video-bytes');
    ($this->row)($video, 'copied', ['disk' => 'staff-chat-r2', 'path' => $path]);
    $elsewhere = ($this->entry)('elsewhere', 'other-disk.mp4', 'video/mp4');
    ($this->row)($elsewhere, 'copied', ['disk' => 'local', 'path' => '../.env']);
    $message = ($this->message)($this->channel, [$video, $elsewhere]);

    $this->travelTo(now()->startOfSecond());
    $redirect = actingAs($this->viewer)->get(($this->url)($message, $video['id']))->assertStatus(302);
    $location = $redirect->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($location)->toContain($path)
        ->and((int) ($query['expiration'] ?? 0))->toBe(now()->addMinutes(ShowSlackMessageFile::SIGNED_LINK_MINUTES)->timestamp)
        ->and(ShowSlackMessageFile::SIGNED_LINK_MINUTES)->toBeLessThanOrEqual(5)
        ->and($redirect->headers->get('Cache-Control'))->toContain('no-store')
        ->and($redirect->headers->get('Referrer-Policy'))->toBe('no-referrer');

    actingAs($this->viewer)->get(($this->url)($message, $video['id'], ['variant' => 'thumbnail']))->assertNotFound();
    actingAs($this->viewer)->get(($this->url)($message, $elsewhere['id']))->assertNotFound();

    Config::set('filesystems.disks.staff-chat-r2', ['driver' => 's3', 'key' => 'key', 'secret' => 'secret', 'region' => 'auto', 'endpoint' => 'https://account.r2.cloudflarestorage.com', 'bucket' => 'aiku-staff-chat', 'use_path_style_endpoint' => true, 'throw' => true]);
    Storage::forgetDisk('staff-chat-r2');
    SlackFile::where('slack_id', $video['id'])->update(['name' => 'Vidéo d’accueil "2026".mp4']);
    $signed = actingAs($this->viewer)->get(($this->url)($message, $video['id']))->assertStatus(302)->headers->get('Location');
    parse_str((string) parse_url($signed, PHP_URL_QUERY), $signedQuery);
    expect($signed)->toStartWith('https://account.r2.cloudflarestorage.com/aiku-staff-chat/'.$path.'?')
        ->and($signedQuery['X-Amz-Expires'])->toBe((string) (ShowSlackMessageFile::SIGNED_LINK_MINUTES * 60))
        ->and($signedQuery['response-content-type'])->toBe('application/octet-stream')
        ->and($signedQuery['response-content-disposition'])->toStartWith('attachment; filename=')->toContain("filename*=utf-8''Vid%C3%A9o")->not->toContain('"2026"');

    Config::set('filesystems.disks.staff-chat-r2.bucket', null);
    actingAs($this->viewer)->get(($this->url)($message, $video['id']))->assertNotFound();
});

test('a Slack file is refused unless reached through a live message of a channel the person may see that really lists it, INI-436', function () {
    $photo  = ($this->entry)('photo', 'parcel.png', 'image/png');
    $secret = ($this->entry)('secret', 'payroll.png', 'image/png');
    $shared = ($this->entry)('shared', 'shared.png', 'image/png');
    $later  = ($this->entry)('later', 'later.png', 'image/png');
    $failed = ($this->entry)('failed', 'failed.png', 'image/png');
    ($this->copied)($photo, ($this->png)());
    ($this->copied)($secret, ($this->png)());
    $sharedCopy = ($this->copied)($shared, ($this->png)());
    ($this->row)($failed, 'failed', ['reason' => 'http 500']);

    $inPrivate = ($this->message)($this->private, [$secret, $shared]);
    $inIgnored = ($this->message)($this->ignored, [$secret]);
    $visible   = ($this->message)($this->channel, [$photo, $shared, $later, $failed]);
    $other     = ($this->message)($this->channel, [], 'no files here');
    $deleted   = ($this->message)($this->channel, [$photo]);
    $deleted->delete();
    $sharedCopy->update(['slack_message_id' => $inPrivate->id]);

    $stranger = ($this->newUser)();
    $guest    = ($this->newUser)();
    $borrowed = ($this->newUser)();
    $leaver   = ($this->newUser)();
    $inactive = ($this->newUser)(['status' => false]);
    SlackUser::create(['slack_id' => "UFG{$this->s}", 'user_id' => $guest->id, 'email' => $guest->email, 'data' => ['is_restricted' => true]]);
    SlackUser::create(['slack_id' => "UFB{$this->s}", 'user_id' => $borrowed->id, 'email' => 'someone.else@example.com']);
    SlackUser::create(['slack_id' => "UFL{$this->s}", 'user_id' => $leaver->id, 'email' => $leaver->email, 'is_deleted' => true]);
    SlackUser::create(['slack_id' => "UFO{$this->s}", 'user_id' => $inactive->id, 'email' => $inactive->email]);

    $refused = function (User $user, SlackMessage $message, string $fileId) {
        foreach ([[], ['variant' => 'thumbnail']] as $query) {
            actingAs($user)->get(($this->url)($message, $fileId, $query))->assertNotFound();
        }
    };

    Http::fake(['imgproxy.test/*' => Http::response('small-webp', 200, ['Content-Type' => 'image/webp'])]);
    actingAs($this->viewer)->get(($this->url)($visible, $photo['id']))->assertOk();
    actingAs($this->viewer)->get(($this->url)($visible, $shared['id']))->assertOk();
    actingAs($this->viewer)->get(($this->url)($visible, $shared['id'], ['variant' => 'thumbnail']))->assertOk();

    $refused($this->viewer, $inPrivate, $secret['id']);
    $refused($this->viewer, $inPrivate, $shared['id']);
    $refused($this->viewer, $inIgnored, $secret['id']);
    $refused($this->viewer, $visible, $secret['id']);
    $refused($this->viewer, $other, $photo['id']);
    $refused($this->viewer, $deleted, $photo['id']);
    $refused($this->viewer, $visible, $later['id']);
    $refused($this->viewer, $visible, $failed['id']);
    $refused($this->viewer, $visible, 'FDOESNOTEXIST');
    $refused($this->viewer, $visible, strtolower($photo['id']));

    foreach ([$stranger, $guest, $borrowed, $leaver] as $person) {
        $refused($person, $visible, $photo['id']);
    }
    expect(actingAs($inactive)->get(($this->url)($visible, $photo['id']))->status())->toBeIn([302, 403, 404]);

    $this->channel->update(['deleted_in_slack_at' => now()]);
    $refused($this->viewer, $visible, $photo['id']);
    $this->channel->update(['deleted_in_slack_at' => null, 'is_public' => false]);
    $refused($this->viewer, $visible, $photo['id']);
    $this->channel->update(['is_public' => true]);

    $base = Str::before(($this->url)($visible, $photo['id']), '/files/');
    foreach (['..%2F'.$photo['id'], $photo['id'].'%00', $photo['id'].'/..', '%2e%2e', $photo['id'].'.png'] as $odd) {
        actingAs($this->viewer)->get($base.'/files/'.$odd)->assertNotFound();
    }
    actingAs($this->viewer)->get(str_replace('/messages/'.$visible->id.'/', '/messages/'.$visible->id.'%20or%201=1/', ($this->url)($visible, $photo['id'])))->assertNotFound();
    actingAs($this->viewer)->get(str_replace('/messages/'.$visible->id.'/', '/messages/999999999/', ($this->url)($visible, $photo['id'])))->assertNotFound();

    auth()->logout();
    get(($this->url)($visible, $photo['id']))->assertRedirect();
});
