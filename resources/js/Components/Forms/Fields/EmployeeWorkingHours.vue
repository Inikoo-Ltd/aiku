<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { get } from 'lodash-es'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import Checkbox from 'primevue/checkbox'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPlus, faTrash, faChevronCircleDown, faChevronCircleUp } from '@fal'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: any
}>()

/*
|--------------------------------------------------------------------------
| TIME HELPERS
|--------------------------------------------------------------------------
*/
const parseTime = (val: any) => {
    if (!val) return null

    if (val instanceof Date && !isNaN(val.getTime())) return val

    if (typeof val === 'string') {
        const timePart = val.includes('T') ? val.split('T')[1] : val
        const clean = timePart.replace(/Z|[+-]\d{2}:\d{2}$/, '')
        const parts = clean.split(':')

        if (parts.length >= 2) {
            const h = Number(parts[0])
            const m = Number(parts[1])
            if (isNaN(h) || isNaN(m)) return null

            const d = new Date()
            d.setHours(h, m, 0, 0)
            return d
        }
    }

    return null
}

const formatTime = (val: Date | null) => {
    if (!val) return null

    const h = String(val.getHours()).padStart(2, '0')
    const m = String(val.getMinutes()).padStart(2, '0')

    return `${h}:${m}`
}

const sameTime = (a: Date | null, b: Date | null) => formatTime(a) === formatTime(b)

const cloneBreak = (b: BreakRow): BreakRow => ({ name: b.name, start: b.start, end: b.end, paid: b.paid })

const breaksEqual = (a: BreakRow[], b: BreakRow[]) => {
    if (a.length !== b.length) return false

    return a.every((x, i) => x.name === b[i].name && sameTime(x.start, b[i].start) && sameTime(x.end, b[i].end) && x.paid === b[i].paid)
}

/*
|--------------------------------------------------------------------------
| STATE
|--------------------------------------------------------------------------
*/
const dayColumnWidth = '150px'
const gridStyle = {
    display: 'grid',
    gridTemplateColumns: `${dayColumnWidth} 1fr 1fr`,
    columnGap: '12px',
}

const dayLabels: Record<number, string> = {
    1: 'Monday', 2: 'Tuesday', 3: 'Wednesday', 4: 'Thursday', 5: 'Friday', 6: 'Saturday', 7: 'Sunday',
}
const allIsos = [1, 2, 3, 4, 5, 6, 7]

type BreakRow = { name: string; start: Date | null; end: Date | null; paid: boolean }
type DayRow = { start: Date | null; end: Date | null; breaks: BreakRow[]; working: boolean }
type GroupKey = 'weekday' | 'weekend'

const groupDefs: { key: GroupKey; label: string; isos: number[] }[] = [
    { key: 'weekday', label: 'Weekdays', isos: [1, 2, 3, 4, 5] },
    { key: 'weekend', label: 'Weekend', isos: [6, 7] },
]

const days: Record<number, DayRow> = reactive(
    Object.fromEntries(allIsos.map(iso => [iso, { start: null, end: null, breaks: [], working: true }]))
)

// The organisation's hours, shown until this employee is given their own.
const isInherited = computed(() => !!props.form[props.fieldName]?.inherited)

// Days the schedule already had something to say about, plus any the user switches by hand.
// Anything else is left out of the payload, so opening the form does not mark it as edited
// and a day nobody has an opinion on stays unwritten.
const stated = reactive(new Set<number>())

const ui = reactive<Record<GroupKey, boolean>>({ weekday: false, weekend: false })

const group = reactive<Record<GroupKey, { start: Date | null; end: Date | null }>>({
    weekday: { start: null, end: null },
    weekend: { start: null, end: null },
})

const groupBreaks = reactive<Record<GroupKey, BreakRow[]>>({
    weekday: [],
    weekend: [],
})

let hydrating = false
let suppressNextHydrate = false
let syncingGroupFromDays = false
let syncingGroupBreaksFromDays = false

// Only the days that are worked have a say in what the group row shows: a switched off Friday
// should not blank out the Weekdays row for the four days that are worked.
const workedIsos = (isos: number[]) => isos.filter(iso => days[iso].working)

const syncGroupFromDays = (key: GroupKey, isos: number[]) => {
    const worked = workedIsos(isos)
    const first = worked.length ? days[worked[0]] : null
    const consistent = !!first && worked.every(iso => sameTime(days[iso].start, first.start) && sameTime(days[iso].end, first.end))

    syncingGroupFromDays = true
    group[key].start = consistent ? first!.start : null
    group[key].end = consistent ? first!.end : null
    setTimeout(() => syncingGroupFromDays = false)
}

const syncGroupBreaksFromDays = (key: GroupKey, isos: number[]) => {
    const worked = workedIsos(isos)
    const first = worked.length ? days[worked[0]].breaks : null
    const consistent = !!first && worked.every(iso => breaksEqual(days[iso].breaks, first))

    syncingGroupBreaksFromDays = true
    groupBreaks[key] = consistent ? first!.map(cloneBreak) : []
    setTimeout(() => syncingGroupBreaksFromDays = false)
}

const initFromForm = (val: any) => {
    hydrating = true

    const data = val?.data ?? {}

    allIsos.forEach((iso) => {
        const dayData = data[String(iso)] ?? data[iso]

        days[iso].start = parseTime(dayData?.s ?? null)
        days[iso].end = parseTime(dayData?.e ?? null)
        // A day without hours is not a day that is worked, whether it was switched off or
        // simply never filled in.
        days[iso].working = dayData ? (dayData.w ?? true) && !!(dayData.s && dayData.e) : false
        if (dayData) {
            stated.add(iso)
        }
        days[iso].breaks = Array.isArray(dayData?.b)
            ? dayData.b.map((b: any) => ({
                name: b.n ?? '',
                start: parseTime(b.s ?? null),
                end: parseTime(b.e ?? null),
                paid: !!b.p,
            }))
            : []
    })

    groupDefs.forEach(({ key, isos }) => {
        syncGroupFromDays(key, isos)
        syncGroupBreaksFromDays(key, isos)
    })

    setTimeout(() => hydrating = false)
}

initFromForm(props.form[props.fieldName])

watch(
    () => props.form[props.fieldName],
    (v) => {
        if (suppressNextHydrate) {
            suppressNextHydrate = false
            return
        }
        initFromForm(v)
    },
    { deep: true }
)

// Editing a group's Start/Finish applies it to every day in that group at once,
// so setting "Weekdays" fills Monday through Friday without touching each one.
groupDefs.forEach(({ key, isos }) => {
    watch(() => [group[key].start, group[key].end], () => {
        if (hydrating || syncingGroupFromDays) return

        workedIsos(isos).forEach(iso => {
            days[iso].start = group[key].start
            days[iso].end = group[key].end
        })
    })
})

// Same idea for breaks: adding/editing/removing a break on the group row
// replaces every day's break list in that group with the group's own list.
groupDefs.forEach(({ key, isos }) => {
    watch(() => groupBreaks[key], () => {
        if (hydrating || syncingGroupBreaksFromDays) return

        workedIsos(isos).forEach(iso => {
            days[iso].breaks = groupBreaks[key].map(cloneBreak)
        })
    }, { deep: true })
})

// A group's switch reads as on only when every day under it is worked, and flipping it sets
// all of them: the quick way to say "no weekends" or "this one does not work Fridays".
const groupWorking = (key: GroupKey) => groupDefs.find(g => g.key === key)!.isos.every(iso => days[iso].working)

const setGroupWorking = (key: GroupKey, working: boolean) => {
    groupDefs.find(g => g.key === key)!.isos.forEach(iso => setDayWorking(iso, working))
}

const setDayWorking = (iso: number, working: boolean) => {
    stated.add(iso)
    days[iso].working = working
}

const addBreak = (iso: number) => {
    days[iso].breaks.push({ name: '', start: null, end: null, paid: false })
}

const addGroupBreak = (key: GroupKey) => {
    groupBreaks[key].push({ name: '', start: null, end: null, paid: false })
}

const removeGroupBreak = (key: GroupKey, index: number) => {
    groupBreaks[key].splice(index, 1)
}

const removeBreak = (iso: number, index: number) => {
    days[iso].breaks.splice(index, 1)
}

const buildPayload = () => {
    const result: any = { data: {} }

    allIsos.forEach((iso) => {
        const day = days[iso]
        const s = formatTime(day.start)
        const e = formatTime(day.end)

        if (!day.working || !s || !e) {
            if (stated.has(iso)) {
                result.data[iso] = { s: null, e: null, w: false, b: [] }
            }

            return
        }

        result.data[iso] = {
            s,
            e,
            w: true,
            b: day.breaks
                .filter(b => b.start && b.end)
                .map(b => ({
                    s: formatTime(b.start),
                    e: formatTime(b.end),
                    n: b.name || null,
                    p: b.paid,
                })),
        }
    })

    return result
}

watch(days, () => {
    if (hydrating) return

    groupDefs.forEach(({ key, isos }) => {
        syncGroupFromDays(key, isos)
        syncGroupBreaksFromDays(key, isos)
    })

    suppressNextHydrate = true
    props.form[props.fieldName] = buildPayload()
}, { deep: true })
</script>

<template>
    <div class="space-y-3">
        <p v-if="isInherited" class="flex items-start gap-1.5 rounded-md bg-blue-50 px-3 py-2 text-xs text-blue-800">
            {{ trans("These are the organisation's hours. Saving gives this employee their own copy, which stops following the organisation's later changes.") }}
        </p>

        <div class="border rounded-xl overflow-hidden bg-white">
            <div
                class="items-center px-4 py-2.5 bg-gray-50 text-[11px] font-semibold text-gray-500 uppercase tracking-wide border-b"
                :style="gridStyle"
            >
                <div></div>
                <div>{{ trans('Start') }}</div>
                <div>{{ trans('Finish') }}</div>
            </div>

            <template v-for="g in groupDefs" :key="g.key">
                <div class="border-t first:border-t-0 bg-gray-50/60">
                    <div class="items-center px-4 py-3" :style="gridStyle">
                        <div class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                            <button
                                type="button"
                                class="text-gray-400 hover:text-gray-700"
                                :aria-label="trans('Show individual days')"
                                @click="ui[g.key] = !ui[g.key]"
                            >
                                <FontAwesomeIcon :icon="ui[g.key] ? faChevronCircleUp : faChevronCircleDown" fixed-width />
                            </button>
                            <ToggleSwitch
                                :modelValue="groupWorking(g.key)"
                                v-tooltip="groupWorking(g.key) ? trans('Worked') : trans('Not worked')"
                                :aria-label="trans('Worked')"
                                @update:modelValue="setGroupWorking(g.key, $event)"
                            />
                            {{ trans(g.label) }}
                        </div>

                        <div class="pr-3">
                            <DatePicker
                                v-model="group[g.key].start"
                                timeOnly fluid :showClear="true"
                                :disabled="!groupWorking(g.key)"
                                :placeholder="trans('Start')"
                                inputClass="text-sm"
                            />
                        </div>

                        <div>
                            <DatePicker
                                v-model="group[g.key].end"
                                timeOnly fluid :showClear="true"
                                :disabled="!groupWorking(g.key)"
                                :placeholder="trans('Finish')"
                                inputClass="text-sm"
                            />
                        </div>
                    </div>

                    <div v-if="groupWorking(g.key)" class="px-4 pb-3 space-y-2" :style="{ marginLeft: dayColumnWidth }">
                        <div
                            v-for="(b, index) in groupBreaks[g.key]"
                            :key="index"
                            class="flex items-center gap-2 bg-white border rounded-md px-2.5 py-2"
                        >
                            <InputText
                                v-model="b.name"
                                :placeholder="trans('Break name (e.g. Lunch)')"
                                class="text-sm flex-1 min-w-0"
                            />
                            <DatePicker v-model="b.start" timeOnly fluid :placeholder="trans('Start')" inputClass="text-sm" class="w-24 shrink-0" />
                            <span class="text-gray-300 shrink-0">–</span>
                            <DatePicker v-model="b.end" timeOnly fluid :placeholder="trans('End')" inputClass="text-sm" class="w-24 shrink-0" />
                            <label class="flex items-center gap-1.5 text-xs text-gray-500 whitespace-nowrap shrink-0 px-1">
                                <Checkbox v-model="b.paid" :binary="true" />
                                {{ trans('Paid') }}
                            </label>
                            <Button type="transparent" size="xs" :icon="faTrash" @click="removeGroupBreak(g.key, index)" />
                        </div>

                        <button
                            type="button"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-800 flex items-center gap-1.5 py-1"
                            @click="addGroupBreak(g.key)"
                        >
                            <FontAwesomeIcon :icon="faPlus" class="w-2.5 h-2.5" fixed-width />
                            {{ trans('Add break') }}
                        </button>
                    </div>
                </div>

                <div v-if="ui[g.key]">
                    <div v-for="iso in g.isos" :key="iso" class="border-t">
                        <div class="items-center px-4 py-2.5" :style="gridStyle">
                            <div class="flex items-center gap-2 pl-6 text-sm" :class="days[iso].working ? 'text-gray-600' : 'text-gray-400'">
                                <ToggleSwitch
                                    :modelValue="days[iso].working"
                                    v-tooltip="days[iso].working ? trans('Worked') : trans('Not worked')"
                                    :aria-label="trans('Worked')"
                                    @update:modelValue="setDayWorking(iso, $event)"
                                />
                                {{ trans(dayLabels[iso]) }}
                            </div>

                            <div v-if="days[iso].working" class="pr-3">
                                <DatePicker
                                    v-model="days[iso].start"
                                    timeOnly fluid :showClear="true"
                                    :placeholder="trans('Start')"
                                    inputClass="text-sm"
                                />
                            </div>

                            <div v-if="days[iso].working">
                                <DatePicker
                                    v-model="days[iso].end"
                                    timeOnly fluid :showClear="true"
                                    :placeholder="trans('Finish')"
                                    inputClass="text-sm"
                                />
                            </div>

                            <div v-else class="col-span-2 text-sm italic text-gray-400">
                                {{ trans('Not a working day') }}
                            </div>

                            <div v-if="days[iso].working && !(days[iso].start && days[iso].end)" class="col-span-3 pl-6 pt-1 text-xs text-gray-400">
                                {{ trans('Without both a start and a finish this day is saved as not worked.') }}
                            </div>
                        </div>

                        <div v-if="days[iso].working" class="px-4 pb-3 space-y-2" :style="{ marginLeft: dayColumnWidth }">
                            <div
                                v-for="(b, index) in days[iso].breaks"
                                :key="index"
                                class="flex items-center gap-2 bg-gray-50 rounded-md px-2.5 py-2"
                            >
                                <InputText
                                    v-model="b.name"
                                    :placeholder="trans('Break name (e.g. Lunch)')"
                                    class="text-sm flex-1 min-w-0"
                                />
                                <DatePicker v-model="b.start" timeOnly fluid :placeholder="trans('Start')" inputClass="text-sm" class="w-24 shrink-0" />
                                <span class="text-gray-300 shrink-0">–</span>
                                <DatePicker v-model="b.end" timeOnly fluid :placeholder="trans('End')" inputClass="text-sm" class="w-24 shrink-0" />
                                <label class="flex items-center gap-1.5 text-xs text-gray-500 whitespace-nowrap shrink-0 px-1">
                                    <Checkbox v-model="b.paid" :binary="true" />
                                    {{ trans('Paid') }}
                                </label>
                                <Button type="transparent" size="xs" :icon="faTrash" @click="removeBreak(iso, index)" />
                            </div>

                            <button
                                type="button"
                                class="text-xs font-medium text-indigo-600 hover:text-indigo-800 flex items-center gap-1.5 py-1"
                                @click="addBreak(iso)"
                            >
                                <FontAwesomeIcon :icon="faPlus" class="w-2.5 h-2.5" fixed-width />
                                {{ trans('Add break') }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <p v-if="get(form, ['errors', fieldName])" class="text-sm text-red-600">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>
