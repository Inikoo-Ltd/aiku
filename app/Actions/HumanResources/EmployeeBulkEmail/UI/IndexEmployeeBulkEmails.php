<?php

namespace App\Actions\HumanResources\EmployeeBulkEmail\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Http\Resources\HumanResources\EmployeeBulkEmailsResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeBulkEmail;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexEmployeeBulkEmails extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    public function handle(Organisation $organisation): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereAnyWordStartWith('employee_bulk_emails.subject', $value);
        });

        return QueryBuilder::for(EmployeeBulkEmail::class)
            ->where('employee_bulk_emails.organisation_id', $organisation->id)
            ->leftJoin('users', 'employee_bulk_emails.sender_id', '=', 'users.id')
            ->defaultSort('-created_at')
            ->select([
                'employee_bulk_emails.id',
                'employee_bulk_emails.subject',
                'employee_bulk_emails.body',
                'employee_bulk_emails.number_recipients',
                'employee_bulk_emails.attachments',
                'employee_bulk_emails.created_at',
                'users.contact_name as sender_name',
            ])
            ->allowedSorts(['created_at', 'subject', 'number_recipients'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withEmptyState(['title' => __('No bulk emails sent yet')])
                ->column(key: 'created_at', label: __('Sent'), sortable: true)
                ->column(key: 'subject', label: __('Subject'), sortable: true, searchable: true)
                ->column(key: 'sender_name', label: __('Sent by'))
                ->column(key: 'attachments', label: __('Attachments'))
                ->column(key: 'number_recipients', label: __('Recipients'), sortable: true, type: 'number')
                ->defaultSort('-created_at');
        };
    }

    public function htmlResponse(LengthAwarePaginator $employeeBulkEmails, ActionRequest $request): Response
    {
        $title = __('Bulk emails');

        return Inertia::render(
            'Org/HumanResources/EmployeeBulkEmails',
            [
                'breadcrumbs'     => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'           => $title,
                'pageHead'        => [
                    'icon'    => ['fal', 'fa-mail-bulk'],
                    'title'   => $title,
                    'actions' => $this->canEdit ? [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'key'   => 'new bulk email',
                            'label' => __('New bulk email'),
                            'icon'  => ['fal', 'fa-plus'],
                        ],
                    ] : [],
                ],
                'data'            => EmployeeBulkEmailsResource::collection($employeeBulkEmails),
                'sendRoute'       => [
                    'name'       => 'grp.org.hr.bulk_emails.store',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'employeeOptions' => $this->organisation->employees()
                    ->where('state', EmployeeStateEnum::WORKING)
                    ->where(fn ($query) => $query->whereNotNull('work_email')->orWhereNotNull('email'))
                    ->orderBy('contact_name')
                    ->get(['id', 'contact_name', 'alias'])
                    ->map(fn (Employee $employee): array => [
                        'value' => $employee->id,
                        'label' => $employee->contact_name ?: $employee->alias,
                    ]),
            ]
        )->table($this->tableStructure());
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.hr.bulk_emails.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Bulk emails'),
                        'icon'  => 'fal fa-mail-bulk',
                    ],
                ],
            ]
        );
    }
}
