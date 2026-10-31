<?php

/*
 * Author Louis Perez
 * Created on 16-10-2026-10h-50m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Goods\ProductDevelopment\StoreProductDevelopment;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\SysAdmin\Organisation\Seeders\SeedJobPositions;
use App\Actions\SysAdmin\Organisation\Seeders\SeedOrganisationPermissions;
use App\Actions\SysAdmin\User\StoreUser;
use App\Actions\UI\Grp\Layout\GetGroupNavigation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\HumanResources\Employee\EmploymentTypeEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\HumanResources\JobPosition;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->group      = createGroup();
    $this->adminGuest = createAdminGuest($this->group);
    $this->staff      = $this->adminGuest->getUser();
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->staff);
});

function newAgentWithUser(object $test): array
{
    $agent        = StoreAgent::make()->action($test->group, Agent::factory()->definition());
    $organisation = $agent->organisation;
    SeedOrganisationPermissions::run($organisation);
    SeedJobPositions::run($organisation);

    $suffix   = Str::lower(Str::random(6));
    $position = JobPosition::where('organisation_id', $organisation->id)->where('code', 'agt-m')->firstOrFail();
    $employee = StoreEmployee::make()->action($organisation, [
        'worker_number'   => 'pd-agent-'.$suffix,
        'alias'           => 'pd-agent-'.$suffix,
        'contact_name'    => 'Agent '.$suffix,
        'state'           => EmployeeStateEnum::WORKING,
        'type'            => EmployeeTypeEnum::EMPLOYEE,
        'employment_type' => EmploymentTypeEnum::FULL_TIME,
        'positions'       => [['slug' => $position->slug, 'scopes' => []]],
    ]);
    $user = StoreUser::make()->action($employee, [
        'username'       => 'pd-agent-'.$suffix,
        'password'       => Str::random(32),
        'status'         => true,
        'reset_password' => false,
    ]);

    return [$agent, $user];
}

function newFreelancer(object $test): User
{
    $guest = StoreGuest::make()->action($test->group, [...Guest::factory()->definition(), 'positions' => []]);

    return $guest->getUser();
}

function newDevelopment(object $test, array $modelData): ProductDevelopment
{
    $suffix = Str::upper(Str::random(5));

    return StoreProductDevelopment::make()->action($test->group, $test->staff, [
        'name'  => 'Access check '.$suffix,
        'type'  => 'new',
        'items' => [['code' => 'PDA-'.$suffix, 'name' => 'Access check SKU']],
        ...$modelData,
    ]);
}

test('an agent sees the developments of their own agent only, and the menu offers them', function () {
    [$agent, $agentUser]         = newAgentWithUser($this);
    [$otherAgent, $otherUser]    = newAgentWithUser($this);
    $ownDevelopment              = newDevelopment($this, ['agent_id' => $agent->id]);
    $otherAgentsDevelopment      = newDevelopment($this, ['agent_id' => $otherAgent->id]);

    actingAs($agentUser);

    get(route('grp.product_developments.show', ['productDevelopment' => $ownDevelopment->slug]))->assertOk();
    get(route('grp.product_developments.show', ['productDevelopment' => $otherAgentsDevelopment->slug]))->assertForbidden();
    get(route('grp.product_developments.index', ['perPage' => 500]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.data', fn ($rows) => collect($rows)->pluck('code')->contains($ownDevelopment->code)
                && !collect($rows)->pluck('code')->contains($otherAgentsDevelopment->code)));

    expect(array_keys(GetGroupNavigation::run($agentUser)))->toContain('product-developments');

    actingAs($otherUser);
    get(route('grp.product_developments.show', ['productDevelopment' => $ownDevelopment->slug]))->assertForbidden();
    post(route('grp.models.product_development.files.upload', ['productDevelopment' => $ownDevelopment->id]), [
        'files' => [UploadedFile::fake()->createWithContent('artwork.pdf', 'other agent '.Str::random(20))],
        'scope' => 'artwork_draft',
    ])->assertForbidden();
});

test('a freelancer sees only the developments they are a member of, never cost items, and cannot move or edit them', function () {
    $freelancer   = newFreelancer($this);
    $assigned     = newDevelopment($this, ['members' => [['user_id' => $freelancer->id, 'role' => ProductDevelopmentMemberRoleEnum::DESIGNER->value]]]);
    $notAssigned  = newDevelopment($this, []);
    $costLabel    = __('Supplier cost');

    get(route('grp.product_developments.show', ['productDevelopment' => $assigned->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('overview.waiting_on', fn ($rows) => collect($rows)->pluck('label')->contains($costLabel)));

    actingAs($freelancer);

    get(route('grp.product_developments.show', ['productDevelopment' => $notAssigned->slug]))->assertForbidden();
    get(route('grp.product_developments.show', ['productDevelopment' => $assigned->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('overview.waiting_on', fn ($rows) => !collect($rows)->pluck('label')->contains($costLabel))
            ->where('overview.products', fn ($rows) => collect($rows)->every(fn ($row) => !in_array($costLabel, $row['still_missing'], true)))
            ->where('stageMove.is_allowed', false)
            ->where('header.can_edit', false)
            ->where('editor', null));

    get(route('grp.product_developments.index', ['perPage' => 500]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.data', fn ($rows) => collect($rows)->pluck('code')->all() === [$assigned->code]));

    patch(route('grp.models.product_development.next_stage', ['productDevelopment' => $assigned->id]))->assertForbidden();
    patch(route('grp.models.product_development.members.sync', ['productDevelopment' => $assigned->id]), ['members' => []])->assertForbidden();
    patch(route('grp.models.product_development.stage.due_date', ['productDevelopmentStage' => $assigned->stages()->first()->id]), ['due_at' => '2026-12-01'])->assertForbidden();
    get(route('grp.tasks.list', ['view' => 'model', 'model_type' => 'ProductDevelopment', 'model_id' => $assigned->id]))->assertOk();
    get(route('grp.tasks.list', ['view' => 'model', 'model_type' => 'ProductDevelopment', 'model_id' => $notAssigned->id]))->assertForbidden();
    patch(route('grp.models.product_development.update', ['productDevelopment' => $assigned->id]), ['target_online_date' => '2026-12-01'])->assertForbidden();
    get(route('grp.product_developments.create'))->assertForbidden();

    post(route('grp.models.product_development.files.upload', ['productDevelopment' => $assigned->id]), [
        'files' => [UploadedFile::fake()->createWithContent('artwork-draft.pdf', 'freelancer draft '.Str::random(20))],
        'scope' => 'artwork_draft',
    ])->assertRedirect();
    expect($assigned->files()->where('scope', 'artwork_draft')->count())->toBe(1);

    expect(array_keys(GetGroupNavigation::run($freelancer)))->toContain('product-developments');
});

test('staff see every development, and a user with no access and no membership is refused', function () {
    $development = newDevelopment($this, []);

    get(route('grp.product_developments.show', ['productDevelopment' => $development->slug]))->assertOk();

    $outsider = newFreelancer($this);
    actingAs($outsider);

    get(route('grp.product_developments.index'))->assertForbidden();
    get(route('grp.product_developments.show', ['productDevelopment' => $development->slug]))->assertForbidden();
    expect(array_keys(GetGroupNavigation::run($outsider)))->not->toContain('product-developments');
});

test('mentions notify only people who can see the development, and outsiders cannot comment', function () {
    $freelancer  = newFreelancer($this);
    $outsider    = newFreelancer($this);
    $development = newDevelopment($this, ['members' => [['user_id' => $freelancer->id, 'role' => ProductDevelopmentMemberRoleEnum::DESIGNER->value]]]);

    $freelancerBefore = $freelancer->notifications()->count();
    $outsiderBefore   = $outsider->notifications()->count();

    post(route('grp.models.product_development.comments.store', ['productDevelopment' => $development->id]), [
        'body'   => "Please check the dieline @{$freelancer->username} and @{$outsider->username}",
        'images' => [UploadedFile::fake()->createWithContent('dieline-notes.pdf', '%PDF-1.4 notes '.Str::random(20))],
    ])->assertRedirect();

    $comment = $development->comments()->latest('id')->first();
    expect($comment->mentions)->toBe([$freelancer->id])
        ->and($freelancer->notifications()->count())->toBe($freelancerBefore + 1)
        ->and($outsider->notifications()->count())->toBe($outsiderBefore);

    actingAs($freelancer);
    post(route('grp.models.product_development.comments.store', ['productDevelopment' => $development->id]), [
        'body'      => 'On it',
        'parent_id' => $comment->id,
    ])->assertRedirect();

    get(route('grp.product_developments.show', ['productDevelopment' => $development->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('comments.threads', 1)
            ->has('comments.threads.0.replies', 1)
            ->has('comments.threads.0.attachments', 1)
            ->where('comments.mentionables', fn ($people) => collect($people)->pluck('id')->contains($freelancer->id)));

    $attachmentUlid = $comment->getMedia('ticket_attachments')->first()?->ulid;
    expect($attachmentUlid)->not->toBeNull();
    get(route('grp.media.download', ['media' => $attachmentUlid]))->assertOk();

    actingAs($outsider);
    post(route('grp.models.product_development.comments.store', ['productDevelopment' => $development->id]), ['body' => 'hello'])->assertForbidden();
    get(route('grp.media.download', ['media' => $attachmentUlid]))->assertNotFound();
});
