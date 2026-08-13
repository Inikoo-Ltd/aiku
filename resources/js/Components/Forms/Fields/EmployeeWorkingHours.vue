<script setup lang="ts">
import { reactive, watch } from 'vue'
import { get } from 'lodash-es'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import Checkbox from 'primevue/checkbox'
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
type DayRow = { start: Date | null; end: Date | null; breaks: BreakRow[] }
type GroupKey = 'weekday' | 'weekend'

const groupDefs: { key: GroupKey; label: string; isos: number[] }[] = [
    { key: 'weekday', label: 'Weekdays', isos: [1, 2, 3, 4, 5] },
    { key: 'weekend', label: 'Weekend', isos: [6, 7] },
]

const days: Record<number, DayRow> = reactive(
    Object.fromEntries(allIsos.map(iso => [iso, { start: null, end: null, breaks: [] }]))
)

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

const syncGroupFromDays = (key: GroupKey, isos: number[]) => {
    const first = days[isos[0]]
    const consistent = isos.every(iso => sameTime(days[iso].start, first.start) && sameTime(days[iso].end, first.end))

    syncingGroupFromDays = true
    group[key].start = consistent ? first.start : null
    group[key].end = consistent ? first.end : null
    setTimeout(() => syncingGroupFromDays = false)
}

const syncGroupBreaksFromDays = (key: GroupKey, isos: number[]) => {
    const first = days[isos[0]].breaks
    const consistent = isos.every(iso => breaksEqual(days[iso].breaks, first))

    syncingGroupBreaksFromDays = true
    groupBreaks[key] = consistent ? first.map(cloneBreak) : []
    setTimeout(() => syncingGroupBreaksFromDays = false)
}

const initFromForm = (val: any) => {
    hydrating = true

    const data = val?.data ?? {}

    allIsos.forEach((iso) => {
        const dayData = data[String(iso)] ?? data[iso]

        days[iso].start = parseTime(dayData?.s ?? null)
        days[iso].end = parseTime(dayData?.e ?? null)
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

        isos.forEach(iso => {
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

        isos.forEach(iso => {
            days[iso].breaks = groupBreaks[key].map(cloneBreak)
        })
    }, { deep: true })
})

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

        if (!s && !e && day.breaks.length === 0) return

        result.data[iso] = {
            s,
            e,
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
                                <FontAwesomeIcon :icon="ui[g.key] ? faChevronCircleUp : faChevronCircleDown" />
                            </button>
                            {{ trans(g.label) }}
                        </div>

                        <div class="pr-3">
                            <DatePicker
                                v-model="group[g.key].start"
                                timeOnly fluid :showClear="true"
                                :placeholder="trans('Start')"
                                inputClass="text-sm"
                            />
                        </div>

                        <div>
                            <DatePicker
                                v-model="group[g.key].end"
                                timeOnly fluid :showClear="true"
                                :placeholder="trans('Finish')"
                                inputClass="text-sm"
                            />
                        </div>
                    </div>

                    <div class="px-4 pb-3 space-y-2" :style="{ marginLeft: dayColumnWidth }">
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
                            <FontAwesomeIcon :icon="faPlus" class="w-2.5 h-2.5" />
                            {{ trans('Add break') }}
                        </button>
                    </div>
                </div>

                <div v-if="ui[g.key]">
                    <div v-for="iso in g.isos" :key="iso" class="border-t">
                        <div class="items-center px-4 py-2.5" :style="gridStyle">
                            <div class="text-sm text-gray-600 pl-6">{{ trans(dayLabels[iso]) }}</div>

                            <div class="pr-3">
                                <DatePicker
                                    v-model="days[iso].start"
                                    timeOnly fluid :showClear="true"
                                    :placeholder="trans('Start')"
                                    inputClass="text-sm"
                                />
                            </div>

                            <div>
                                <DatePicker
                                    v-model="days[iso].end"
                                    timeOnly fluid :showClear="true"
                                    :placeholder="trans('Finish')"
                                    inputClass="text-sm"
                                />
                            </div>
                        </div>

                        <div class="px-4 pb-3 space-y-2" :style="{ marginLeft: dayColumnWidth }">
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
                                <FontAwesomeIcon :icon="faPlus" class="w-2.5 h-2.5" />
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
