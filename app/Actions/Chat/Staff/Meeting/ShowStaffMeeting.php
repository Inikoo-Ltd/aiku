<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Meeting;

use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffMeeting;
use App\Models\SysAdmin\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The page a meeting link opens, with no staff layout. A colleague logged in joins from here; anyone else may log in or
 * knock as a guest. It never says who is in the meeting.
 */
class ShowStaffMeeting
{
    use AsAction;

    public function asController(StaffMeeting $staffMeeting, Request $request): Response
    {
        abort_unless(StaffCall::isEnabled(), 404);

        /** @var User|null $user */
        $user = $request->user();

        if (!$user) {
            $request->session()->put('url.intended', $request->url());
        }

        return Inertia::render('Chat/StaffMeeting', [
            'meeting' => [
                'code'      => $staffMeeting->code,
                'title'     => $staffMeeting->title,
                'is_open'   => $staffMeeting->isOpen(),
                'is_locked' => $staffMeeting->locked_at !== null,
            ],
            'is_colleague' => $user && JoinStaffMeeting::mayJoin($staffMeeting, $user),
            'is_creator'   => $user && $user->id === $staffMeeting->created_by_user_id && !$staffMeeting->for_one_call,
            'login_url'    => route('grp.login.show'),
            'routes'       => [
                'join'  => route('grp.meet.join', $staffMeeting->code),
                'end'   => route('grp.chat.staff.meetings.destroy', $staffMeeting->code),
                'knock' => route('grp.meet.knock', $staffMeeting->code),
                'poll'  => route('grp.meet.guests.poll', [$staffMeeting->code, '__guest__']),
                'token' => route('grp.meet.guests.token', [$staffMeeting->code, '__guest__']),
                'leave' => route('grp.meet.guests.leave', [$staffMeeting->code, '__guest__']),
            ],
        ]);
    }
}
