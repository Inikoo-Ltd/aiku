<?php

use App\Actions\Catalogue\Shop\Seeders\SeedShopOutboxes;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Comms\Email\SendAppointmentEmail;
use App\Actions\Comms\Email\SendAppointmentEmailToStaff;
use App\Actions\Comms\EmailTemplate\StoreEmailTemplate;
use App\Actions\Comms\Outbox\PublishOutbox;
use App\Actions\Comms\Ses\SendSesEmail;
use App\Actions\CRM\Appointment\StoreAppointment;
use App\Actions\CRM\Appointment\SyncAppointmentStaffTask;
use App\Actions\CRM\Appointment\UpdateAppointment;
use App\Actions\CRM\AppointmentType\StoreAppointmentType;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\UI\Grp\Layout\GetDigitalAgencyOrganisationNavigation;
use App\Actions\UI\Grp\Layout\GetShopNavigation;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Email\EmailBuilderEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Enums\Comms\Outbox\OutboxBuilderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\CRM\Appointment\AppointmentSourceEnum;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Enums\CRM\AppointmentType\AppointmentTypeMeetingModeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Outbox;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Lorisleiva\Actions\Decorators\JobDecorator;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list($this->organisation, $this->user, $this->shop) = createShop();

    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->user);
});

test('appointment emails are edited in the outbox workshop and sent with their merge tags', function () {
    $layout = json_decode(file_get_contents(database_path('seeders/datasets/email-templates/beefree_appointment_accepted/beefree_appointment_accepted.json')), true);

    StoreEmailTemplate::make()->action($this->organisation->group, [
        'name'        => 'Appointment accepted '.Str::random(6),
        'layout'      => $layout,
        'is_seeded'   => true,
        'builder'     => 'beefree',
        'state'       => EmailTemplateStateEnum::ACTIVE,
        'active_at'   => now(),
        'language_id' => $this->shop->language_id,
        'data'        => ['outboxes' => [OutboxCodeEnum::APPOINTMENT_ACCEPTED->value]],
    ], strict: false);

    /** @var Outbox $outbox */
    $outbox = $this->shop->outboxes()->where('code', OutboxCodeEnum::APPOINTMENT_ACCEPTED->value)->first();
    $outbox->update(['builder' => OutboxBuilderEnum::BLADE, 'state' => OutboxStateEnum::ACTIVE, 'is_applicable' => true]);
    $outbox->emailOngoingRun->email->update(['builder' => EmailBuilderEnum::BLADE]);

    SeedShopOutboxes::run($this->shop);

    $outbox->refresh();
    $email = $outbox->emailOngoingRun->email->refresh();

    expect($outbox->builder)->toBe(OutboxBuilderEnum::BEEFREE)
        ->and($email->builder)->toBe(EmailBuilderEnum::BEEFREE)
        ->and($email->unpublishedSnapshot->layout)->toBe($layout)
        ->and($email->liveSnapshot->compiled_layout)->toBeNull();

    $this->get(route('grp.org.shops.show.dashboard.comms.outboxes.workshop', [$this->organisation->slug, $this->shop->slug, $outbox->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Web/Workshop/Outbox/OutboxWorkshop')
            ->where('mergeTags', fn ($tags) => collect($tags)->pluck('value')->contains('[Appointment Date]')));

    Queue::fake();
    $appointmentType = StoreAppointmentType::make()->action($this->shop, [
        'name'             => 'Workshop email '.Str::random(6),
        'meeting_mode'     => AppointmentTypeMeetingModeEnum::STORE_VISIT->value,
        'location'         => 'Showroom, Sheffield',
        'duration_minutes' => 30,
    ]);
    $appointment = StoreAppointment::make()->action($this->shop, [
        'appointment_type_id' => $appointmentType->id,
        'starts_at'           => now()->addDays(3)->format('Y-m-d').' 10:00',
        'contact_name'        => '<b>Workshop visitor</b>',
        'email'               => 'workshop-'.Str::lower(Str::random(8)).'@example.com',
        'state'               => AppointmentStateEnum::ACCEPTED->value,
    ]);

    $sentHtml = [];
    SendSesEmail::shouldRun()->andReturnUsing(function (string $subject, string $emailHtmlBody, DispatchedEmail $dispatchedEmail) use (&$sentHtml) {
        $sentHtml[] = $emailHtmlBody;

        return $dispatchedEmail;
    });

    SendAppointmentEmail::make()->handle($appointment->id, OutboxCodeEnum::APPOINTMENT_ACCEPTED->value);

    PublishOutbox::make()->action($outbox, [
        'layout'          => $layout,
        'compiled_layout' => '<p>Dear [Customer Name], see you on [Appointment Date] at [Appointment Time] in [Shop Name].</p>[Appointment Details]',
    ]);

    SendAppointmentEmail::make()->handle($appointment->id, OutboxCodeEnum::APPOINTMENT_ACCEPTED->value);

    $startsAt = $appointment->starts_at->copy()->setTimezone($this->shop->timezone?->name ?? 'UTC');

    expect($sentHtml)->toHaveCount(2)
        ->and($sentHtml[0])->toContain('is confirmed')
        ->and($sentHtml[1])->toContain('Dear &lt;b&gt;Workshop visitor&lt;/b&gt;')
        ->and($sentHtml[1])->toContain($startsAt->translatedFormat('l j F Y').' at '.$startsAt->format('H:i'))
        ->and($sentHtml[1])->toContain('Showroom, Sheffield')
        ->and($sentHtml[1])->not->toContain('[Appointment Date]')
        ->and($sentHtml[1])->not->toContain('<b>Workshop visitor</b>');
});

test('the staff who get an appointment task are emailed one by one when it is booked on the website, given to them, moved or cancelled', function () {
    $staffMember = fn (string $email): User => tap(
        StoreGuest::make()->action($this->organisation->group, array_merge(Guest::factory()->definition(), ['positions' => []]))->getUser()
    )->update(['email' => $email]);

    $suffix  = Str::lower(Str::random(8));
    $anna    = $staffMember("anna-$suffix@example.com");
    $ben     = $staffMember("ben-$suffix@example.com");
    $noEmail = $staffMember("carl-$suffix@example.com");
    $noEmail->update(['email' => '']);

    $appointmentType = StoreAppointmentType::make()->action($this->shop, [
        'name'             => 'Staff email '.Str::random(6),
        'meeting_mode'     => AppointmentTypeMeetingModeEnum::STORE_VISIT->value,
        'location'         => 'Showroom, Sheffield',
        'duration_minutes' => 30,
    ]);
    $appointmentType->attendees()->sync([$anna->id, $ben->id, $noEmail->id]);

    $day  = now()->addDays(4)->format('Y-m-d');
    $book = fn (string $time, AppointmentSourceEnum $source, array $extra = []) => StoreAppointment::make()->action($this->shop, array_merge([
        'appointment_type_id' => $appointmentType->id,
        'starts_at'           => $day.' '.$time,
        'contact_name'        => 'Staff email visitor',
        'email'               => 'staff-email-'.Str::lower(Str::random(8)).'@example.com',
        'source'              => $source->value,
        'state'               => AppointmentStateEnum::REQUESTED->value,
    ], $extra));

    $staffEmails = fn (int $appointmentId): array => Queue::pushed(JobDecorator::class, fn ($job) => $job->displayName() === SendAppointmentEmailToStaff::class)
        ->filter(fn ($job) => $job->getParameters()[0] === $appointmentId)
        ->map(fn ($job) => [$job->getParameters()[1], $job->getParameters()[2]])
        ->values()
        ->all();

    Queue::fake();

    $requested = $book('10:00', AppointmentSourceEnum::WEBSITE);
    $byStaff   = $book('12:00', AppointmentSourceEnum::STAFF);

    expect($staffEmails($requested->id))->toEqualCanonicalizing([[$anna->id, 'booked'], [$ben->id, 'booked']])
        ->and($staffEmails($byStaff->id))->toBe([]);

    Queue::fake();
    UpdateAppointment::make()->action($requested, ['state' => AppointmentStateEnum::ACCEPTED->value, 'user_id' => $ben->id]);
    expect($staffEmails($requested->id))->toBe([[$ben->id, 'assigned']]);

    Queue::fake();
    UpdateAppointment::make()->action($requested->refresh(), ['starts_at' => $day.' 15:00']);
    expect($staffEmails($requested->id))->toBe([[$ben->id, 'rescheduled']]);

    Queue::fake();
    UpdateAppointment::make()->action($requested->refresh(), ['state' => AppointmentStateEnum::CANCELLED->value, 'state_reason' => 'Cancelled by the visitor']);
    expect($staffEmails($requested->id))->toBe([[$ben->id, 'cancelled']]);

    $openTaskOf = fn (int $appointmentId): ?StaffTask => StaffTask::open()
        ->where('data->kind', SyncAppointmentStaffTask::TASK_KIND)
        ->where('data->appointment_id', $appointmentId)
        ->first();

    $handedOver = $book('09:00', AppointmentSourceEnum::STAFF, ['state' => AppointmentStateEnum::ACCEPTED->value, 'user_id' => $ben->id])->load('user');
    expect($openTaskOf($handedOver->id)?->assignee_id)->toBe($ben->id);

    Queue::fake();
    UpdateAppointment::make()->action($handedOver, ['user_id' => $anna->id]);
    expect($staffEmails($handedOver->id))->toBe([[$anna->id, 'assigned']])
        ->and($openTaskOf($handedOver->id)?->assignee_id)->toBe($anna->id);

    actingAs($anna);
    $annaMoves = $book('11:00', AppointmentSourceEnum::STAFF, ['state' => AppointmentStateEnum::ACCEPTED->value, 'user_id' => $anna->id]);
    Queue::fake();
    UpdateAppointment::make()->action($annaMoves, ['starts_at' => $day.' 16:00']);
    expect($staffEmails($annaMoves->id))->toBe([]);
    actingAs($this->user);

    $this->patchJson(route('grp.models.org.shop.update', [$this->shop->organisation_id, $this->shop->id]), ['appointment_notify_staff' => false])->assertOk();
    Queue::fake();
    $whileOff = $book('13:00', AppointmentSourceEnum::WEBSITE);
    expect($staffEmails($whileOff->id))->toBe([])
        ->and(SendAppointmentEmailToStaff::isEnabled($this->shop->refresh()))->toBeFalse();
    $this->patchJson(route('grp.models.org.shop.update', [$this->shop->organisation_id, $this->shop->id]), ['appointment_notify_staff' => true])->assertOk();

    /** @var Outbox $outbox */
    $outbox = $this->shop->outboxes()->where('code', OutboxCodeEnum::NEW_APPOINTMENT->value)->first();
    $outbox->update(['builder' => OutboxBuilderEnum::BEEFREE, 'state' => OutboxStateEnum::ACTIVE, 'is_applicable' => true]);
    $outbox->emailOngoingRun->email->liveSnapshot->update(['compiled_layout' => '<p>[Shop Name]</p>']);

    $sent = [];
    SendSesEmail::shouldRun()->andReturnUsing(function (string $subject, string $emailHtmlBody, DispatchedEmail $dispatchedEmail) use (&$sent) {
        $sent[] = [$dispatchedEmail->emailAddress->email, $subject];

        return $dispatchedEmail;
    });

    SendAppointmentEmailToStaff::make()->handle($whileOff->id, $anna->id, 'booked');
    SendAppointmentEmailToStaff::make()->handle($requested->id, $ben->id, 'cancelled');
    SendAppointmentEmailToStaff::make()->handle($requested->id, $noEmail->id, 'cancelled');

    expect($sent)->toHaveCount(2)
        ->and($sent[0][0])->toBe($anna->email)
        ->and($sent[1][0])->toBe($ben->email)
        ->and($sent[1][1])->toStartWith('Appointment cancelled: Staff email visitor')
        ->and($anna->refresh()->dispatchedEmails()->where('outbox_id', $outbox->id)->exists())->toBeTrue();
});

test('an external shop takes widget bookings, so it gets the appointment emails and the comms menu to edit them', function () {
    $externalShopData         = Shop::factory()->definition();
    $externalShopData['type'] = ShopTypeEnum::EXTERNAL->value;
    $externalShop             = StoreShop::run($this->organisation, $externalShopData);

    SeedShopOutboxes::run($externalShop);

    $appointmentCodes = [
        OutboxCodeEnum::APPOINTMENT_REQUESTED->value,
        OutboxCodeEnum::APPOINTMENT_ACCEPTED->value,
        OutboxCodeEnum::APPOINTMENT_DECLINED->value,
        OutboxCodeEnum::APPOINTMENT_RESCHEDULED->value,
        OutboxCodeEnum::APPOINTMENT_CANCELLED->value,
        OutboxCodeEnum::APPOINTMENT_REMINDER->value,
        OutboxCodeEnum::NEW_APPOINTMENT->value,
    ];

    $outboxes = $externalShop->outboxes()->whereIn('code', $appointmentCodes)->get();

    expect($outboxes->pluck('code')->map(fn (OutboxCodeEnum $code) => $code->value)->sort()->values()->all())->toBe(collect($appointmentCodes)->sort()->values()->all())
        ->and($outboxes->every(fn (Outbox $outbox) => $outbox->state === OutboxStateEnum::ACTIVE))->toBeTrue()
        ->and($externalShop->outboxes()->where('code', OutboxCodeEnum::ORDER_CONFIRMATION->value)->exists())->toBeFalse();

    $navigation = GetShopNavigation::run($externalShop, $this->user->fresh());

    expect(collect(data_get($navigation, 'dashboard.topMenu.subSections'))->filter()->pluck('route.name')->all())
        ->toContain('grp.org.shops.show.dashboard.comms.dashboard');

    $this->get(route('grp.org.shops.show.settings.edit', [$this->organisation->slug, $externalShop->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('EditModel')
            ->where('formData.blueprint', fn ($blueprint) => collect($blueprint)->pluck('label')->intersect(['Appointment settings', 'Zoom', 'Google Calendar & Meet'])->count() === 3));

    $this->get(route('grp.org.shops.show.crm.appointments.settings', [$this->organisation->slug, $externalShop->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('EditModel')
            ->where('formData.blueprint', fn ($blueprint) => collect($blueprint)->pluck('label')->values()->all() === ['Appointment settings', 'Zoom', 'Google Calendar & Meet', 'Email'])
            ->where('formData.blueprint.3.fields', fn ($fields) => array_keys(collect($fields)->all()) === ['email', 'mailbox', 'mailbox_sender_name'])
            ->where('formData.blueprint.3.fields.mailbox.type', 'mailbox_connect')
            ->where('formData.blueprint.3.fields.mailbox.value.connect_url', fn (string $url) => str_contains($url, 'from=appointments'))
            ->has('formData.blueprint.3.fields.mailbox_sender_name')
            ->where('formData.blueprint.2.fields.google_calendar.value.connect_url', fn (string $url) => str_contains($url, 'from=appointments'))
            ->where('pageHead.subNavigation', fn ($tabs) => collect($tabs)->pluck('route.name')->contains('grp.org.shops.show.crm.appointments.settings')));
});

test('a digital agency organisation has its shops appointments and outboxes in the sidebar, without the shop menus', function () {
    $user = $this->user->fresh();

    $navigation = GetDigitalAgencyOrganisationNavigation::run($user, $this->organisation);

    expect($navigation)->not->toHaveKey('shops_fulfilments_navigation')
        ->and(data_get($navigation, 'appointments_'.$this->shop->slug.'.route.name'))->toBe('grp.org.shops.show.crm.appointments.index')
        ->and(data_get($navigation, 'outboxes_'.$this->shop->slug.'.route.name'))->toBe('grp.org.shops.show.dashboard.comms.outboxes.index')
        ->and($navigation)->not->toHaveKey('appointment_settings_'.$this->shop->slug);

    $this->shop->update(['state' => ShopStateEnum::CLOSED]);

    expect(GetDigitalAgencyOrganisationNavigation::run($user->fresh(), $this->organisation))->not->toHaveKey('appointments_'.$this->shop->slug);

    $this->shop->update(['state' => ShopStateEnum::OPEN]);
});
