<?php

namespace App\Actions\HumanResources\CalendarEvent;

use App\Actions\OrgAction;
use App\Models\HumanResources\CalendarEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteCalendarEvent extends OrgAction
{
    public function handle(CalendarEvent $calendarEvent): CalendarEvent
    {
        $calendarEvent->delete();

        return $calendarEvent;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return StoreCalendarEvent::canEditCalendar($request->user(), $this->organisation);
    }

    public function asController(CalendarEvent $calendarEvent, ActionRequest $request): CalendarEvent
    {
        $this->initialisation($calendarEvent->organisation, $request);

        return $this->handle($calendarEvent);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    public function action(CalendarEvent $calendarEvent): CalendarEvent
    {
        $this->asAction = true;
        $this->initialisation($calendarEvent->organisation, []);

        return $this->handle($calendarEvent);
    }
}
