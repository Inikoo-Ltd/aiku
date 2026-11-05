<?php

namespace App\Models\Production;

use App\Enums\Production\JobOrderItem\JobOrderItemStateEnum;
use App\Enums\Production\JobOrderItem\JobOrderItemStatusEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $job_order_id
 * @property int $artefact_id
 * @property string|null $slug
 * @property string|null $reference
 * @property JobOrderItemStatusEnum $status
 * @property JobOrderItemStateEnum $state
 * @property string|null $notes
 * @property int $quantity
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property string|null $source_id
 * @property-read \App\Models\Production\Artefact|null $artefact
 * @property-read Group|null $group
 * @property-read \App\Models\Production\JobOrder|null $jobOrder
 * @property-read Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Production\JobOrderItemTask> $tasks
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItem onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItem withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItem withoutTrashed()
 * @mixin \Eloquent
 */

class JobOrderItem extends Model
{
    use HasSlug;
    use SoftDeletes;

    protected $guarded = [];
    protected $casts   = [
        'data'                     => 'array',
        'state'                    => JobOrderItemStateEnum::class,
        'status'                   => JobOrderItemStatusEnum::class,
        'set_as_not_received_at'   => 'datetime',
        'received_at'              => 'datetime',
        'booking_in_at'            => 'datetime',
        'booked_in_at'             => 'datetime',
        'storing_at'               => 'datetime',
        'requested_for_return_at'  => 'datetime',
        'picking_at'               => 'datetime',
        'picked_at'                => 'datetime',
        'set_as_incident_at'       => 'datetime',
        'dispatched_at'            => 'datetime',

    ];

    protected $attributes = [
        'data'  => '{}',
        'notes' => '',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('reference')
            ->doNotGenerateSlugsOnUpdate()
            ->doNotGenerateSlugsOnCreate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function carriedTo(): ?JobOrder
    {
        $carriedToId = Arr::get($this->data, 'carried_to');

        return $carriedToId ? JobOrder::whereIn('state', JobOrderStateEnum::open())->find($carriedToId) : null;
    }

    /**
     * Where a line closed short sent its rest and where it came from, so nobody reads a carried
     * line as made short and orders the rest again (HELP-4499). A line is the item and its splits.
     *
     * @param  Collection<int, JobOrderItem>  $lineItems
     * @return array{ordered_quantity: int, carried_to: list<array{reference: string, slug: string, quantity: int}>, carried_from: array{reference: string, slug: string, ordered_quantity: int}|null}|null
     */
    public static function carryOver(Collection $lineItems): ?array
    {
        $head         = $lineItems->firstWhere('split_from_id', null) ?? $lineItems->first();
        $carriedLines = self::carriedLines($lineItems);
        $isCarriedLine = Arr::has($head->jobOrder->data ?? [], 'carried_from')
            && $head->id == JobOrderItem::where('job_order_id', $head->job_order_id)->where('artefact_id', $head->artefact_id)->whereNull('split_from_id')->min('id');
        $source       = $isCarriedLine ? self::carriedFromItem($head) : null;
        if ($carriedLines->isEmpty() && !$source) {
            return null;
        }

        $root = $source;
        while ($root && $previous = self::carriedFromItem($root)) {
            $root = $previous;
        }

        return [
            'ordered_quantity' => self::orderedQuantity($lineItems),
            'carried_to'       => $carriedLines->map(fn (JobOrderItem $line) => [
                'reference' => $line->jobOrder->reference,
                'slug'      => $line->jobOrder->slug,
                'quantity'  => self::orderedQuantity($line->withSplits()),
            ])->values()->all(),
            'carried_from'     => $source ? [
                'reference'        => $source->jobOrder->reference,
                'slug'             => $source->jobOrder->slug,
                'ordered_quantity' => self::orderedQuantity(($root->splitFrom ?? $root)->withSplits()),
            ] : null,
        ];
    }

    /**
     * @param  array{ordered_quantity: int, carried_to: list<array{reference: string, slug: string, quantity: int}>, carried_from: array{reference: string, slug: string, ordered_quantity: int}|null}|null  $carryOver
     */
    public static function carryOverLabel(?array $carryOver): ?string
    {
        if (!$carryOver) {
            return null;
        }

        $parts = [];
        if ($carryOver['carried_from']) {
            $parts[] = __('carried from :reference (ordered :quantity)', ['reference' => $carryOver['carried_from']['reference'], 'quantity' => $carryOver['carried_from']['ordered_quantity']]);
        }
        foreach ($carryOver['carried_to'] as $carried) {
            $parts[] = __(':quantity carried to :reference', ['quantity' => $carried['quantity'], 'reference' => $carried['reference']]);
        }
        if ($carryOver['carried_to']) {
            array_unshift($parts, __('ordered :quantity', ['quantity' => $carryOver['ordered_quantity']]));
        }

        return implode(' · ', $parts);
    }

    /**
     * @return Collection<int, JobOrderItem>
     */
    public function withSplits(): Collection
    {
        return JobOrderItem::where('id', $this->id)->orWhere('split_from_id', $this->id)->get();
    }

    /**
     * @param  Collection<int, JobOrderItem>  $lineItems
     */
    private static function orderedQuantity(Collection $lineItems): int
    {
        return (int) $lineItems->sum('quantity') + self::carriedLines($lineItems)->sum(fn (JobOrderItem $line) => self::orderedQuantity($line->withSplits()));
    }

    /**
     * @param  Collection<int, JobOrderItem>  $lineItems
     * @return Collection<int, JobOrderItem>
     */
    private static function carriedLines(Collection $lineItems): Collection
    {
        $carriedToIds = $lineItems->map(fn (JobOrderItem $item) => Arr::get($item->data, 'carried_to'))->filter()->unique();
        if ($carriedToIds->isEmpty()) {
            return collect();
        }

        return JobOrderItem::whereIn('job_order_id', $carriedToIds)
            ->whereIn('artefact_id', $lineItems->pluck('artefact_id')->unique())
            ->whereNull('split_from_id')
            ->whereHas('jobOrder', fn ($query) => $query->where('state', '!=', JobOrderStateEnum::NOT_RECEIVED))
            ->with('jobOrder')
            ->orderBy('id')
            ->get()
            ->unique('job_order_id')
            ->values();
    }

    private static function carriedFromItem(JobOrderItem $item): ?JobOrderItem
    {
        return JobOrderItem::where('data->carried_to', $item->job_order_id)
            ->where('artefact_id', $item->artefact_id)
            ->whereHas('jobOrder')
            ->with(['jobOrder', 'splitFrom'])
            ->orderBy('id')
            ->first();
    }

    /**
     * This artefact on the whole order: the job order it started on and every job order its rest
     * was carried to, splits included, so work made on one part counts for the others (HELP-4509).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, JobOrderItem>
     */
    public function orderItems(): \Illuminate\Database\Eloquent\Collection
    {
        $root = (int) Arr::get($this->jobOrder->data, 'carried_from', $this->job_order_id);

        return JobOrderItem::where('artefact_id', $this->artefact_id)
            ->whereHas('jobOrder', fn ($query) => $query->where('state', '!=', JobOrderStateEnum::NOT_RECEIVED)
                ->where(fn ($query) => $query->where('id', $root)->orWhere('data->carried_from', $root)))
            ->with(['tasks.manufactureTask', 'tasks.jobOrderItem', 'jobOrder'])
            ->orderBy('id')
            ->get();
    }

    public function artefact(): BelongsTo
    {
        return $this->belongsTo(Artefact::class)->withTrashed();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function artisanId(): ?int
    {
        return $this->employee_id ?? $this->jobOrder->employee_id;
    }

    public function artisan(): ?Employee
    {
        return $this->employee_id ? $this->employee : $this->jobOrder->employee;
    }

    public function splitFrom(): BelongsTo
    {
        return $this->belongsTo(JobOrderItem::class, 'split_from_id');
    }

    public function splits(): HasMany
    {
        return $this->hasMany(JobOrderItem::class, 'split_from_id');
    }

    public function finalTask(): ?JobOrderItemTask
    {
        $recipeTaskIds = $this->artefact->manufactureTasks->pluck('id');
        $inRecipe      = $this->tasks->filter(fn (JobOrderItemTask $task) => $recipeTaskIds->contains($task->manufacture_task_id));

        return ($inRecipe->isNotEmpty() ? $inRecipe : $this->tasks)->last();
    }

    public function isFinished(): bool
    {
        return !$this->tasks->contains(fn (JobOrderItemTask $task) => $task->state !== JobOrderItemTaskStateEnum::DONE);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(JobOrderItemTask::class)->orderBy('position')->orderBy('id');
    }
}
