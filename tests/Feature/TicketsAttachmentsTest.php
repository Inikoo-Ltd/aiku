<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Support\Facades\Process;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\Helpers\Ticket\StoreTicketComment;
use App\Actions\Retina\Dropshipping\Ticket\StoreRetinaTicket;
use Illuminate\Support\Facades\DB;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Mcp\Servers\AikuServer;
use App\Models\SysAdmin\User;
use App\Mcp\Tools\TicketAttachmentTool;
use App\Models\Helpers\TicketComment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeAll(function () {
    loadDB();
});

/* Laravel writes datetimes as naive UTC strings, so a Postgres session on any other zone
   stores them shifted and hands them back shifted: a waiting_at written a second ago reads
   hours old, and a fixture written at 11:48 comes back at another time entirely. Pinned for
   this file only, and put back afterwards so no other suite inherits it. */
beforeEach(function () {
    DB::statement("set time zone 'UTC'");

    list($this->organisation, $this->user, $this->shop) = createShop();
    $this->group    = $this->organisation->group;
    $this->website  = createWebsite($this->shop);
    $this->website->update(['status' => true]);
    $this->customer = createCustomer($this->shop);
    $this->webUser  = createWebUser($this->customer);

    app()->instance('group', $this->group);
    setPermissionsTeamId($this->group->id);
    $this->user->assignRole('help-desk-supervisor');
    $this->user->forgetWildcardPermissionIndex();
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->user);
});

afterEach(function () {
    DB::statement('set time zone default');
});

test('screenshots can be attached to tickets and comments', function () {
    $ticket = StoreTicket::make()->action($this->group, [
        'subject' => 'Broken layout, see screenshot',
        'images'  => [UploadedFile::fake()->image('shot.png', 400, 300)],
    ]);
    $comment = StoreTicketComment::make()->action($ticket, $this->user, [
        'images' => [UploadedFile::fake()->image('one.png'), UploadedFile::fake()->image('two.jpg')],
    ]);

    expect($ticket->getMedia('ticket_images'))->toHaveCount(1)
        ->and($comment->body)->toBe('')
        ->and($comment->getMedia('ticket_images'))->toHaveCount(2)
        ->and($ticket->ticketImageSources()[0])->toHaveKey('original');

    post(route('grp.models.ticket.comment.store', $ticket->id), [])->assertSessionHasErrors('body');
});

test('pdf, word, excel, csv and zip files can be attached to tickets and comments', function () {
    $directory = sys_get_temp_dir().'/ticket_files_'.uniqid();
    mkdir($directory);

    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->getActiveSheet()->setCellValue('A1', 'qty');
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save("$directory/stock.xlsx");
    (new PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet))->save("$directory/legacy.xls");

    $docx = new ZipArchive();
    $docx->open("$directory/notes.docx", ZipArchive::CREATE);
    $docx->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $docx->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>');
    $docx->close();

    $bundle = new ZipArchive();
    $bundle->open("$directory/bundle.zip", ZipArchive::CREATE);
    $bundle->addFromString('logs/error.log', "boom\n");
    $bundle->close();

    $ticket = StoreTicket::make()->action($this->group, [
        'subject' => 'Invoice looks wrong',
        'images'  => [UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\n%%EOF\n")],
    ]);
    $comment = StoreTicketComment::make()->action($ticket, $this->user, [
        'images' => [
            new UploadedFile("$directory/stock.xlsx", 'stock.xlsx', null, null, true),
            new UploadedFile("$directory/legacy.xls", 'legacy.xls', null, null, true),
            new UploadedFile("$directory/notes.docx", 'notes.docx', null, null, true),
            UploadedFile::fake()->createWithContent('orders.csv', "reference,qty\nA-1,2\n"),
            UploadedFile::fake()->image('shot.png'),
        ],
    ]);
    $zipComment = StoreTicketComment::make()->action($ticket, $this->user, [
        'images' => [new UploadedFile("$directory/bundle.zip", 'bundle.zip', null, null, true)],
    ]);

    expect($ticket->getMedia('ticket_attachments'))->toHaveCount(1)
        ->and($ticket->ticketAttachments()[0])->toMatchArray(['name' => 'invoice.pdf', 'mime' => 'application/pdf'])
        ->and(collect($comment->ticketAttachments())->pluck('name')->sort()->values()->all())->toBe(['legacy.xls', 'notes.docx', 'orders.csv', 'stock.xlsx'])
        ->and($comment->getMedia('ticket_images'))->toHaveCount(1)
        ->and($zipComment->ticketAttachments()[0]['name'])->toBe('bundle.zip');

    foreach ([UploadedFile::fake()->createWithContent('notes.txt', "plain text\n")] as $rejectedFile) {
        expect(fn () => StoreTicketComment::make()->action($ticket, $this->user, ['images' => [$rejectedFile]]))
            ->toThrow(Illuminate\Validation\ValidationException::class);
    }
});

test('videos up to 50 MB can be attached, other files stay capped at 10 MB, and videos stream with range requests', function () {
    $mp4Header       = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";
    $movHeader       = "\x00\x00\x00\x14ftypqt  \x00\x00\x00\x00qt  ";
    $webmHeader      = "\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01\x42\xF7\x81\x01\x42\xF2\x81\x04\x42\xF3\x81\x08\x42\x82\x84webm\x42\x87\x81\x04\x42\x85\x81\x02";
    $elevenMegabytes = 11 * 1024 * 1024;

    $ticket = StoreTicket::make()->action($this->group, [
        'subject' => 'Screen recording of the bug',
        'images'  => [
            UploadedFile::fake()->createWithContent('recording.mp4', str_pad($mp4Header, $elevenMegabytes, "\0")),
            UploadedFile::fake()->createWithContent('iphone.mov', str_pad($movHeader, 2048, "\0")),
            UploadedFile::fake()->createWithContent('capture.webm', str_pad($webmHeader, 2048, "\0")),
        ],
    ]);

    expect(collect($ticket->ticketAttachments())->pluck('name')->sort()->values()->all())->toBe(['capture.webm', 'iphone.mov', 'recording.mp4']);

    expect(fn () => StoreTicketComment::make()->action($ticket, $this->user, [
        'images' => [UploadedFile::fake()->createWithContent('huge.pdf', str_pad("%PDF-1.4\n", $elevenMegabytes, ' ')."\n%%EOF\n")],
    ]))->toThrow(Illuminate\Validation\ValidationException::class);

    $recording = $ticket->getMedia('ticket_attachments')->firstWhere('name', 'recording.mp4');

    get(route('grp.tickets.attachments.show', ['ticket' => $ticket->reference, 'media' => $recording->ulid]), ['Range' => 'bytes=0-99'])
        ->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 0-99/'.$elevenMegabytes)
        ->assertHeader('Content-Type', 'video/mp4');
});

test('ticket attachments are served inline through the ticket, only to people who can see them', function () {
    $pdf = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%%EOF\n");

    $ticket          = StoreTicket::make()->action($this->group, ['subject' => 'Attachment route', 'images' => [$pdf('ticket.pdf')]]);
    $internalComment = StoreTicketComment::make()->action($ticket, $this->user, ['images' => [$pdf('internal.pdf')]]);
    $internalComment->update(['is_internal' => true]);
    $otherTicket = StoreTicket::make()->action($this->group, ['subject' => 'Someone else', 'images' => [$pdf('other.pdf')]]);

    $ticketPdf   = $ticket->getMedia('ticket_attachments')->first();
    $internalPdf = $internalComment->getMedia('ticket_attachments')->first();
    $otherPdf    = $otherTicket->getMedia('ticket_attachments')->first();
    $urlFor      = fn ($media) => route('grp.tickets.attachments.show', ['ticket' => $ticket->reference, 'media' => $media->ulid]);

    expect($ticket->ticketAttachments()[0]['url'])->toBe($urlFor($ticketPdf));

    $response = get($urlFor($ticketPdf))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline');
    get($urlFor($internalPdf))->assertOk();
    get($urlFor($otherPdf))->assertNotFound();

    $ticket->update(['is_confidential' => true]);
    actingAs(User::factory()->create(['group_id' => $this->group->id]));
    get($urlFor($ticketPdf))->assertForbidden();
    actingAs($this->user);

    Storage::disk($ticketPdf->disk)->delete($ticketPdf->getPathRelativeToRoot());
    get($urlFor($ticketPdf))->assertNotFound();
});

test('customers get attachments of their ticket through retina, never those of internal comments', function () {
    $pdf = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%%EOF\n");

    $ticket          = StoreRetinaTicket::make()->action($this->webUser, ['subject' => 'Invoice attached']);
    $customerComment = StoreTicketComment::make()->action($ticket, $this->webUser, ['images' => [$pdf('invoice.pdf')]]);
    $internalComment = StoreTicketComment::make()->action($ticket, $this->user, ['images' => [$pdf('internal.pdf')]]);
    $internalComment->update(['is_internal' => true]);

    $attachmentUrl = fn ($comment) => 'http://'.$this->website->domain.'/app/dropshipping/support/'.$ticket->reference.'/attachments/'.$comment->getMedia('ticket_attachments')->first()->ulid;

    $this->actingAs($this->webUser, 'retina')->get($attachmentUrl($customerComment))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($this->webUser, 'retina')->get($attachmentUrl($internalComment))->assertNotFound();
});

test('the attachment gallery lists ticket and visible comment files newest first, with thumbnails for images', function () {
    $pdf = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%%EOF\n");

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Gallery', 'images' => [UploadedFile::fake()->image('screen.png', 200, 100), $pdf('spec.pdf')]]);
    StoreTicketComment::make()->action($ticket, $this->user, ['images' => [$pdf('reply.pdf')]]);
    StoreTicketComment::make()->action($ticket, $this->user, ['images' => [$pdf('internal.pdf')]])->update(['is_internal' => true]);

    $gallery = collect($ticket->attachmentGalleryFor($this->user));
    $specPdf = $ticket->getMedia('ticket_attachments')->first();

    expect($gallery->pluck('name')->all())->toBe(['internal.pdf', 'reply.pdf', 'spec.pdf', 'screen.png'])
        ->and($gallery->firstWhere('name', 'screen.png')['thumbnail'])->toHaveKey('original')
        ->and($gallery->firstWhere('name', 'spec.pdf')['thumbnail'])->toBeNull()
        ->and($gallery->firstWhere('name', 'spec.pdf')['url'])->toBe(route('grp.tickets.attachments.show', ['ticket' => $ticket->reference, 'media' => $specPdf->ulid]));

    setPermissionsTeamId($this->group->id);
    $clerk = User::factory()->create(['group_id' => $this->group->id]);
    $clerk->assignRole('help-desk-clerk');
    expect(collect($ticket->attachmentGalleryFor($clerk))->pluck('name')->all())->toBe(['internal.pdf', 'reply.pdf', 'spec.pdf', 'screen.png']);

    $ticket->comments()->where('is_internal', true)->update(['is_lead_only' => true]);
    expect(collect($ticket->attachmentGalleryFor($clerk))->pluck('name')->all())->toBe(['reply.pdf', 'spec.pdf', 'screen.png'])
        ->and(collect($ticket->attachmentGalleryFor($this->user))->pluck('name')->all())->toContain('internal.pdf');

    get(route('grp.tickets.attachments.show', ['ticket' => $ticket->reference, 'media' => $ticket->getMedia('ticket_images')->first()->ulid]))->assertOk();
    get(route('grp.json.ticket.controls', $ticket->id))->assertOk()->assertJsonCount(4, 'attachment_gallery');
});

test('jira ticket attachments missing from the ticket and comment media are copied once, tagged with their jira id', function () {
    User::factory()->create(['group_id' => $this->group->id, 'settings' => ['jira' => ['base_url' => 'https://jira.test/', 'email' => 'bot@test', 'api_token' => 'token']]]);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Imported from Jira']);
    $ticket->update(['data' => ['jira_key' => 'HELP-9001']]);
    $comment = TicketComment::create(['ticket_id' => $ticket->id, 'body' => 'screenshot', 'is_internal' => false]);
    $existingFile = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($existingFile, "%PDF-1.4\n%%EOF\n");
    $comment->attachTicketFile($existingFile, 'already.pdf', 'application/pdf', ['jira_attachment_id' => '500']);

    $resolvedLongAgo = StoreTicket::make()->action($this->group, ['subject' => 'Resolved three weeks ago']);
    $resolvedLongAgo->update(['data' => ['jira_key' => 'HELP-9003'], 'status' => TicketStatusEnum::RESOLVED, 'resolved_at' => now()->subWeeks(3), 'closed_at' => now()->subWeeks(3)]);

    Config::set('media-library.max_file_size', 10);

    Http::fake([
        'jira.test/rest/api/3/issue/HELP-9001*' => Http::response(['fields' => ['attachment' => [
            ['id' => '500', 'filename' => 'already.pdf', 'mimeType' => 'application/pdf', 'size' => 15, 'content' => 'https://jira.test/rest/api/3/attachment/content/500'],
            ['id' => 501, 'filename' => 'invoice.pdf', 'mimeType' => 'application/pdf', 'size' => 15, 'content' => 'https://jira.test/rest/api/3/attachment/content/501'],
        ]]]),
        'jira.test/rest/api/3/issue/HELP-9003*' => Http::response(['fields' => ['attachment' => [
            ['id' => '601', 'filename' => 'recording.mp4', 'mimeType' => 'video/mp4', 'size' => 15, 'content' => 'https://jira.test/rest/api/3/attachment/content/601'],
        ]]]),
        'jira.test/rest/api/3/attachment/content/501' => Http::response("%PDF-1.4\n%%EOF\n"),
    ]);

    expect(\App\Actions\Helpers\Ticket\ImportJiraTicketAttachments::make()->handle($resolvedLongAgo->fresh()))->toBe(0)
        ->and($resolvedLongAgo->fresh()->getMedia('ticket_attachments'))->toHaveCount(0);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'content/601'));

    $action = \App\Actions\Helpers\Ticket\ImportJiraTicketAttachments::make();

    expect($action->handle($ticket))->toBe(1)
        ->and(\App\Actions\Helpers\Ticket\ImportJiraTicketAttachments::make()->handle($ticket->fresh()))->toBe(0);

    $media = $ticket->fresh()->getMedia('ticket_attachments');

    expect($media)->toHaveCount(1)
        ->and($media->first()->name)->toBe('invoice.pdf')
        ->and($media->first()->getCustomProperty('source'))->toBe(['jira_attachment_id' => '501'])
        ->and($comment->fresh()->getMedia('ticket_attachments'))->toHaveCount(1)
        ->and(config('media-library.max_file_size'))->toBe(10);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'content/500'));
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('bot@test:token')));
});

test('jira comment authors are repaired from the jira reporter or the jira author email, leaving unknown authors empty', function () {
    User::factory()->create(['group_id' => $this->group->id, 'settings' => ['jira' => ['base_url' => 'https://jira.test', 'email' => 'bot@test', 'api_token' => 'token']]]);

    $reporter  = User::factory()->create(['group_id' => $this->group->id]);
    $colleague = User::factory()->create(['group_id' => $this->group->id, 'email' => 'colleague-'.uniqid().'@test.com']);

    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Imported comments']);
    $ticket->update(['data' => ['jira_key' => 'HELP-9002'], 'reporter_type' => 'User', 'reporter_id' => $reporter->id]);

    $byReporter  = TicketComment::create(['ticket_id' => $ticket->id, 'body' => 'from reporter', 'is_internal' => false, 'created_at' => '2026-09-14 11:48:58']);
    $byColleague = TicketComment::create(['ticket_id' => $ticket->id, 'body' => 'from colleague', 'is_internal' => false, 'created_at' => '2026-09-14 12:00:00']);
    $byStranger  = TicketComment::create(['ticket_id' => $ticket->id, 'body' => 'from stranger', 'is_internal' => false, 'created_at' => '2026-09-14 13:00:00']);

    $developer         = User::factory()->create(['group_id' => $this->group->id, 'username' => 'dev'.strtolower(\Illuminate\Support\Str::random(8)), 'status' => true]);
    $byDeveloper       = TicketComment::create(['ticket_id' => $ticket->id, 'body' => 'from developer', 'is_internal' => false, 'created_at' => '2026-09-14 14:00:00']);
    $byCustomerNamed   = TicketComment::create(['ticket_id' => $ticket->id, 'body' => 'from customer account named like a user', 'is_internal' => false, 'created_at' => '2026-09-14 15:00:00']);
    $byJiraAutomation  = TicketComment::create(['ticket_id' => $ticket->id, 'body' => 'No reply for 14 days', 'is_internal' => false, 'created_at' => '2026-09-14 16:00:00']);

    Http::fake(['jira.test/rest/api/3/issue/HELP-9002*' => Http::response(['fields' => [
        'reporter' => ['accountId' => 'acc-reporter'],
        'comment'  => ['comments' => [
            ['created' => '2026-09-14T11:48:58.135+0200', 'author' => ['accountId' => 'acc-reporter', 'emailAddress' => 'shared@inbox.test']],
            ['created' => '2026-09-14T12:00:00.000+0200', 'author' => ['accountId' => 'acc-colleague', 'emailAddress' => strtoupper($colleague->email)]],
            ['created' => '2026-09-14T13:00:00.000+0200', 'author' => ['accountId' => 'acc-stranger', 'emailAddress' => 'nobody@nowhere.test']],
            ['created' => '2026-09-14T14:00:00.000+0200', 'author' => ['accountId' => 'acc-developer', 'accountType' => 'atlassian', 'displayName' => ucfirst($developer->username).' Surname']],
            ['created' => '2026-09-14T15:00:00.000+0200', 'author' => ['accountId' => 'acc-customer', 'accountType' => 'customer', 'displayName' => $developer->username]],
            ['created' => '2026-09-14T16:00:00.000+0200', 'author' => ['accountId' => 'acc-automation', 'accountType' => 'app', 'displayName' => 'Automation for Jira']],
        ]],
    ]])]);

    expect(\App\Actions\Helpers\Ticket\RepairJiraTicketCommentAuthors::make()->handle($ticket->fresh()))->toBe(3)
        ->and($byReporter->fresh()->author_id)->toBe($reporter->id)
        ->and($byColleague->fresh()->author_id)->toBe($colleague->id)
        ->and($byStranger->fresh()->author_id)->toBeNull()
        ->and($byDeveloper->fresh()->author_id)->toBe($developer->id)
        ->and($byCustomerNamed->fresh()->author_id)->toBeNull()
        ->and($byJiraAutomation->fresh()->author_id)->toBeNull();
});

test('zip attachments list their contents for the preview and still download on a plain open', function () {
    $directory = sys_get_temp_dir().'/ticket_zip_'.uniqid();
    mkdir($directory);
    $zip = new ZipArchive();
    $zip->open("$directory/logs.zip", ZipArchive::CREATE);
    $zip->addEmptyDir('logs');
    $zip->addFromString('logs/error.log', "boom\n");
    $zip->addFromString('readme.txt', 'hi');
    $zip->close();

    $ticket = StoreTicket::make()->action($this->group, [
        'subject' => 'Logs attached',
        'images'  => [new UploadedFile("$directory/logs.zip", 'logs.zip', null, null, true)],
    ]);
    $url = $ticket->ticketAttachments('grp.tickets.attachments.show')[0]['url'];

    get($url.'?contents=1')->assertOk()
        ->assertJsonPath('total', 3)
        ->assertJsonFragment(['name' => 'logs/', 'is_directory' => true])
        ->assertJsonFragment(['name' => 'logs/error.log', 'size' => 5, 'is_directory' => false]);

    expect(get($url)->assertOk()->headers->get('content-disposition'))->toStartWith('attachment');
});

test('rejected ticket files get readable messages that name the file', function () {
    $ticket = StoreTicket::make()->action($this->group, ['subject' => 'Readable upload errors']);

    $messageFor = function (array $files) use ($ticket): string {
        try {
            StoreTicketComment::make()->action($ticket, $this->user, ['images' => $files]);
        } catch (Illuminate\Validation\ValidationException $exception) {
            return collect($exception->errors())->flatten()->implode(' ');
        }

        return '';
    };

    expect($messageFor([UploadedFile::fake()->create('huge.pdf', 11 * 1024, 'application/pdf')]))
        ->toContain('"huge.pdf" is too big')
        ->not->toContain('images.0')
        ->and($messageFor([UploadedFile::fake()->createWithContent('notes.txt', "plain text\n")]))
        ->toContain('"notes.txt" cannot be attached')
        ->and($messageFor(array_map(fn (int $number) => UploadedFile::fake()->image("shot$number.png"), range(1, 6))))
        ->toContain('You can attach up to 5 files at a time.');
});

test('attachment previews are open to engineers, QA, lead engineers and the reporter only', function () {
    setPermissionsTeamId($this->group->id);
    $reporter  = User::factory()->create(['group_id' => $this->group->id]);
    $engineer  = User::factory()->create(['group_id' => $this->group->id]);
    $qa        = User::factory()->create(['group_id' => $this->group->id]);
    $colleague = User::factory()->create(['group_id' => $this->group->id]);
    $engineer->assignRole('help-desk-clerk');
    $qa->assignRole('qa');

    $ticket = StoreTicket::make()->action($this->group, [
        'subject'       => 'Screenshots inside',
        'reporter_type' => 'User',
        'reporter_id'   => $reporter->id,
        'images'        => [UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\n%%EOF\n")],
    ]);
    $url = route('grp.tickets.attachments.show', ['ticket' => $ticket->reference, 'media' => $ticket->getMedia('ticket_attachments')->first()->ulid]);

    foreach ([$reporter, $engineer, $qa, $this->user] as $allowedViewer) {
        actingAs($allowedViewer);
        get($url)->assertOk();
        get(route('grp.json.ticket.controls', $ticket->id))->assertOk()->assertJsonPath('can_preview_attachments', true);
    }

    actingAs($colleague);
    get(route('grp.tickets.show', $ticket->reference))->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->where('can_preview_attachments', false)
    );
    get($url)->assertForbidden();
});

test('rar and 7z attachments are accepted and list their contents through bsdtar', function () {
    Process::fake([
        'bsdtar --version' => Process::result('bsdtar 3.7.2 - libarchive 3.7.2'),
        'bsdtar -tvf *'    => Process::result("drwxr-xr-x  0 1000   1000        0 Sep 15 10:00 logs/\n-rw-r--r--  0 1000   1000        5 Sep 15 10:00 logs/error log.txt\n"),
    ]);

    $ticket = StoreTicket::make()->action($this->group, [
        'subject' => 'Archives attached',
        'images'  => [
            UploadedFile::fake()->createWithContent('logs.rar', "Rar!\x1A\x07\x01\x00".str_repeat("\0", 64)),
            UploadedFile::fake()->createWithContent('logs.7z', "7z\xBC\xAF\x27\x1C\x00\x04".str_repeat("\0", 64)),
        ],
    ]);
    $urls = collect($ticket->ticketAttachments('grp.tickets.attachments.show'))->pluck('url', 'name');

    foreach (['logs.rar', 'logs.7z'] as $name) {
        get($urls[$name].'?contents=1')->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonFragment(['name' => 'logs/', 'size' => 0, 'is_directory' => true])
            ->assertJsonFragment(['name' => 'logs/error log.txt', 'size' => 5, 'is_directory' => false]);
        expect(get($urls[$name])->assertOk()->headers->get('content-disposition'))->toStartWith('attachment');
    }

    Process::fake(['bsdtar --version' => Process::result(exitCode: 127)]);

    get($urls['logs.rar'].'?contents=1')->assertStatus(422)
        ->assertJsonPath('message', 'This server cannot read RAR files yet. Ask an administrator to install libarchive-tools.');
});

test('assistant reads ticket attachments through MCP, within the ticket visibility rules', function () {
    $pdf = new Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
    $pdf->WriteHTML('<p>Invoice total 42.50 GBP</p>');

    $directory = sys_get_temp_dir().'/ticket_mcp_'.uniqid();
    mkdir($directory);
    $docx = new ZipArchive();
    $docx->open("$directory/notes.docx", ZipArchive::CREATE);
    $docx->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $docx->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Customer &amp; wants</w:t></w:r></w:p><w:p><w:r><w:t>a refund</w:t></w:r></w:p></w:body></w:document>');
    $docx->close();
    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->getActiveSheet()->setTitle('Products')->fromArray([['Code', 'Cost'], ['TROUSER-01', 4.5]]);
    $spreadsheet->getActiveSheet()->setCellValue('B3', '=B2*2');
    $spreadsheet->createSheet()->setTitle('Order')->setCellValue('A4', 'ES');
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save("$directory/stock.xlsx");

    $ticket  = StoreTicket::make()->action($this->group, ['subject' => 'Read my files', 'images' => [UploadedFile::fake()->createWithContent('invoice.pdf', $pdf->OutputBinaryData())]]);
    $comment = StoreTicketComment::make()->action($ticket, $this->user, ['images' => [new UploadedFile("$directory/notes.docx", 'notes.docx', null, null, true), UploadedFile::fake()->image('shot.png', 4, 4)]]);
    $comment->update(['is_lead_only' => true]);
    $stock = StoreTicketComment::make()->action($ticket, $this->user, ['images' => [new UploadedFile("$directory/stock.xlsx", 'stock.xlsx', null, null, true)]]);

    $tool = fn (User $user, string $attachment) => AikuServer::actingAs($user)->tool(TicketAttachmentTool::class, ['reference' => strtolower($ticket->reference), 'attachment' => $attachment]);

    $tool($this->user, 'invoice.pdf')->assertOk()->assertSee('Invoice total 42.50 GBP');
    $tool($this->user, $ticket->getMedia('ticket_attachments')->first()->ulid)->assertOk()->assertSee('invoice.pdf');
    $tool($this->user, 'notes.docx')->assertOk()->assertSee(json_encode("Customer & wants\na refund"));
    $tool($this->user, 'shot.png')->assertOk()->assertSee(base64_encode(Storage::disk($comment->getMedia('ticket_images')->first()->disk)->get($comment->getMedia('ticket_images')->first()->getPathRelativeToRoot())));
    $tool($this->user, 'stock.xlsx')->assertOk()->assertSee(json_encode("## Sheet: Products\n1\tCode\tCost\n2\tTROUSER-01\t4.5\n3\t\t9\n\n## Sheet: Order\n4\tES"));
    $tool($this->user, 'missing.pdf')->assertHasErrors(['Attachment not found on this ticket.']);

    $qa = User::factory()->create(['group_id' => $this->group->id]);
    $qa->assignRole('qa');
    $tool($qa, 'invoice.pdf')->assertOk();
    $tool($qa, 'notes.docx')->assertHasErrors(['Attachment not found on this ticket.']);

    $ticket->update(['is_confidential' => true]);
    $tool($qa, 'invoice.pdf')->assertHasErrors(['Ticket not found or not visible to you.']);
    expect($stock)->toBeInstanceOf(TicketComment::class);
});
