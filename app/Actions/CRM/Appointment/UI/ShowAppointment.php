<?php

namespace App\Actions\CRM\Appointment\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Http\Resources\CRM\AppointmentResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Appointment;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAppointment extends OrgAction
{
    use WithCRMAuthorisation;

    public function handle(Appointment $appointment): Appointment
    {
        return $appointment;
    }

    public function asController(Organisation $organisation, Shop $shop, Appointment $appointment, ActionRequest $request): Appointment
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($appointment);
    }

    public function htmlResponse(Appointment $appointment, ActionRequest $request): Response
    {
        $appointment->loadMissing(['appointmentType', 'visitor', 'user', 'createdBy', 'shop.timezone']);

        $actions = [];
        if ($this->canEdit) {
            $actions[] = [
                'type'  => 'button',
                'style' => 'edit',
                'route' => [
                    'name'       => 'grp.org.shops.show.crm.appointments.edit',
                    'parameters' => $request->route()->originalParameters(),
                ],
            ];
        }

        return Inertia::render(
            'Org/Shop/CRM/Appointment',
            [
                'title'       => __('Appointment'),
                'breadcrumbs' => $this->getBreadcrumbs($appointment, $request->route()->originalParameters()),
                'pageHead'    => [
                    'title'   => $appointment->contact_name,
                    'model'   => __('Appointment'),
                    'icon'    => [
                        'title' => __('Appointment'),
                        'icon'  => 'fal fa-calendar',
                    ],
                    'actions' => $actions,
                ],
                'appointment' => AppointmentResource::make($appointment)->resolve(),
                'history'     => $this->history($appointment),
                'canEdit'     => $this->canEdit,
            ]
        );
    }

    /**
     * Read off the audits, so every move and every change of state keeps its own line and its own
     * time, whoever made it and from wherever.
     *
     * @return array<int, array{at: mixed, icon: string, icon_class: string, text: string, by: string|null}>
     */
    public function history(Appointment $appointment): array
    {
        $timezone = $appointment->shop->timezone?->name ?? 'UTC';
        $when     = fn (string $value) => Carbon::parse($value, 'UTC')->setTimezone($timezone)->translatedFormat('D j M Y H:i');

        $events = [[
            'at'         => $appointment->created_at,
            'icon'       => 'fal fa-calendar-plus',
            'icon_class' => 'text-gray-400',
            'text'       => $appointment->source->historyText($appointment->createdBy?->chatName()),
            'by'         => null,
        ]];

        foreach ($appointment->audits()->with('user')->where('event', 'updated')->orderBy('id')->get() as $audit) {
            $by = Appointment::auditAuthorName($audit, $appointment->contact_name);

            $movedFrom = $audit->old_values['starts_at'] ?? null;
            $movedTo   = $audit->new_values['starts_at'] ?? null;
            if ($movedFrom && $movedTo) {
                $events[] = [
                    'at'         => $audit->created_at,
                    'icon'       => 'fal fa-calendar-edit',
                    'icon_class' => 'text-gray-400',
                    'text'       => __('Rescheduled from :from to :to', ['from' => $when($movedFrom), 'to' => $when($movedTo)]),
                    'by'         => $by,
                ];
            }

            $state = AppointmentStateEnum::tryFrom((string) ($audit->new_values['state'] ?? ''));
            if ($state) {
                $reason   = in_array($state, AppointmentStateEnum::closed(), true) ? ($audit->new_values['state_reason'] ?? null) : null;
                $by       ??= $state === AppointmentStateEnum::CANCELLED ? $appointment->contact_name : null;
                $events[] = [
                    'at'         => $audit->created_at,
                    'icon'       => $state->icon()['icon'],
                    'icon_class' => $state->icon()['class'],
                    'text'       => $reason ? $state->label().': '.$reason : $state->label(),
                    'by'         => $by,
                ];
            }
        }

        return $events;
    }

    public function getBreadcrumbs(Appointment $appointment, array $routeParameters): array
    {
        return array_merge(
            IndexAppointments::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.crm.appointments.show',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $appointment->contact_name,
                    ],
                ],
            ]
        );
    }
}
