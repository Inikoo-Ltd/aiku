<?php

use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\SysAdmin\User\StoreUser;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Str;

$agentUsers = [
    ['handle' => 'Coco',       'name' => 'Coco',       'org' => 'china', 'aurora' => '1:218824', 'hash' => 'e5cafcaf69f7b65228585a9ce85c58095e0fb290b92db474d11699940cc90777'],
    ['handle' => 'ringo',      'name' => 'Ringo',      'org' => 'indo',  'aurora' => '1:238202', 'hash' => 'e5cafcaf69f7b65228585a9ce85c58095e0fb290b92db474d11699940cc90777'],
    ['handle' => 'drishtanta', 'name' => 'Drishtanta', 'org' => 'india', 'aurora' => '1:298696', 'hash' => 'eddcfdcb7cc18e339a68c6091aff35f2e1fbc6ec2dedd2db792d5a38ba160656'],
];

foreach ($agentUsers as $agentUser) {
    $username = Str::kebab(Str::lower($agentUser['handle']));
    if (User::where('username', $username)->exists()) {
        echo "skip $username: user exists\n";
        continue;
    }

    $organisation = Organisation::where('slug', $agentUser['org'])->where('type', 'agent')->firstOrFail();
    $orgAdmin     = JobPosition::where('organisation_id', $organisation->id)->where('code', 'org-admin')->firstOrFail();

    $employee = Employee::where('organisation_id', $organisation->id)->where('alias', $username)->first()
        ?? StoreEmployee::make()->action($organisation, [
            'worker_number'   => $username,
            'alias'           => $username,
            'contact_name'    => $agentUser['name'],
            'state'           => 'working',
            'type'            => 'employee',
            'employment_type' => 'full-time',
            'positions'       => [['slug' => $orgAdmin->slug, 'scopes' => []]],
        ]);

    $user = StoreUser::make()->action($employee, [
        'username'        => $username,
        'password'        => Str::random(64),
        'contact_name'    => $agentUser['name'],
        'status'          => true,
        'reset_password'  => false,
        'auth_type'       => 'aurora',
        'legacy_password' => app()->isLocal() ? hash('sha256', 'hello') : $agentUser['hash'],
        'source_id'       => $agentUser['aurora'],
        'language_id'     => $organisation->language_id,
    ], strict: false);

    echo "created $username -> {$organisation->slug} employee {$employee->id} user {$user->id} roles: ".$user->getRoleNames()->implode(',')."\n";
}
