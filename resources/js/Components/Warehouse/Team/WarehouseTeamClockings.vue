<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Oct 2026 18:00:00 British Summer Time, Sheffield, UK
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { format, addDays, parseISO } from "date-fns"
import { formatInTimeZone } from "date-fns-tz"
import DatePicker from "primevue/datepicker"
import ToggleSwitch from "primevue/toggleswitch"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { hoursLabel, timeIn } from "@/Components/Warehouse/Team/format"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronLeft, faChevronRight, faPlus, faPencil, faTrashAlt, faExclamationTriangle, faSignInAlt, faSignOutAlt } from "@fal"

library.add(faChevronLeft, faChevronRight, faPlus, faPencil, faTrashAlt, faExclamationTriangle, faSignInAlt, faSignOutAlt)

export interface ClockingRow {
    id: number
    clocked_at: string
    type: string
    machine: string | null
    added_by: string | null
    is_late: boolean
    notes: string | null
}

export interface ClockingsPerson {
    id: number
    slug: string
    name: string
    worked_seconds: number
    breaks_seconds: number
    is_open: boolean
    clockings: ClockingRow[]
}

export interface ClockingsData {
    date: string
    timezone: string
    people: ClockingsPerson[]
    open_previous_days: { employee_id: number; name: string; started_at: string; clocking_id: number | null }[]
}

const props = defineProps<{
    data?: ClockingsData
    deleteRoute: { name: string; parameters: Record<string, number> }
}>()

const emit = defineEmits<{
    (e: "add-clocking", employeeId: number | null, day?: string): void
    (e: "edit-clocking", clocking: ClockingRow & { employee_id: number }): void
}>()

const tz = computed(() => props.data?.timezone ?? "UTC")
const todayIso = computed(() => formatInTimeZone(new Date(), tz.value, "yyyy-MM-dd"))
const isToday = computed(() => props.data?.date === todayIso.value)

const day = ref<Date | null>(props.data ? parseISO(props.data.date) : null)
watch(() => props.data?.date, (value) => { day.value = value ? parseISO(value) : null })
const goTo = (date: Date) => {
    const iso = format(date, "yyyy-MM-dd")
    if (iso !== props.data?.date) {
        router.reload({ data: { date: iso }, only: ["clockings"] })
    }
}
watch(day, (value) => { if (value) goTo(value) })
const shift = (days: number) => props.data && goTo(addDays(parseISO(props.data.date), days))

const showEveryone = ref(false)
const peopleWithClockings = computed(() => (props.data?.people ?? []).filter((person) => person.clockings.length))
const peopleWithout = computed(() => (props.data?.people ?? []).filter((person) => !person.clockings.length))
const visiblePeople = computed(() => (showEveryone.value ? props.data?.people ?? [] : peopleWithClockings.value))

const totalWorked = computed(() => peopleWithClockings.value.reduce((sum, person) => sum + person.worked_seconds, 0))
const lateCount = computed(() => peopleWithClockings.value.reduce((sum, person) => sum + (person.clockings[0]?.is_late ? 1 : 0), 0))
const manualCount = computed(() => peopleWithClockings.value.reduce((sum, person) => sum + person.clockings.filter((c) => c.type === "manual").length, 0))

const sourceLabel = (clocking: ClockingRow) => {
    if (clocking.added_by) return ctrans("Added by :name", { name: clocking.added_by })
    if (clocking.machine) return clocking.machine
    return clocking.type === "manual" ? ctrans("Added by hand") : capitalize(clocking.type.replace("-", " "))
}
const deleteRouteFor = (clocking: ClockingRow) => ({
    name: props.deleteRoute.name,
    parameters: { ...props.deleteRoute.parameters, clocking: clocking.id },
})
</script>

<template>
    <div v-if="!data" class="px-4 py-4 space-y-3 animate-pulse" aria-busy="true">
        <div class="h-10 w-96 rounded bg-gray-200" />
        <div v-for="i in 6" :key="i" class="h-14 rounded-lg bg-gray-200" />
    </div>

    <div v-else class="px-4 py-4 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-1">
                <Button type="tertiary" size="xs" :icon="faChevronLeft" v-tooltip="ctrans('Previous day')" @click="shift(-1)" />
                <DatePicker v-model="day" dateFormat="D d M yy" :maxDate="new Date()" showIcon iconDisplay="input" class="w-44" />
                <Button type="tertiary" size="xs" :icon="faChevronRight" :disabled="isToday" v-tooltip="ctrans('Next day')" @click="shift(1)" />
                <Button v-if="!isToday" type="tertiary" size="xs" :label="ctrans('Today')" @click="goTo(new Date())" />
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600">
                <span>{{ ctrans("Clocked: :n", { n: peopleWithClockings.length }) }}</span>
                <span>{{ ctrans(":hours worked", { hours: hoursLabel(totalWorked, "0m") }) }}</span>
                <span v-if="lateCount" class="text-amber-700">{{ ctrans(":n late", { n: lateCount }) }}</span>
                <span v-if="manualCount">{{ ctrans(":n added by hand", { n: manualCount }) }}</span>
                <label class="flex items-center gap-2 text-xs text-gray-500">
                    <ToggleSwitch v-model="showEveryone" />
                    {{ ctrans("Show everyone (:n without clockings)", { n: peopleWithout.length }) }}
                </label>
            </div>
        </div>

        <div v-if="data.open_previous_days.length" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800">
            <div class="flex items-center gap-2 font-medium">
                <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                {{ ctrans("Missing clock-outs from earlier days") }}
            </div>
            <ul class="mt-1.5 flex flex-wrap gap-2">
                <li v-for="open in data.open_previous_days" :key="open.employee_id + open.started_at">
                    <button
                        type="button"
                        class="rounded-full border border-amber-300 bg-white px-2.5 py-0.5 text-xs hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-[--app-accent]"
                        v-tooltip="ctrans('Press to add the clock-out on that day')"
                        @click="emit('add-clocking', open.employee_id, timeIn(open.started_at, tz, 'yyyy-MM-dd'))"
                    >
                        {{ open.name }} · {{ ctrans("in :when, no clock-out", { when: timeIn(open.started_at, tz, "EEE d MMM HH:mm") }) }}
                    </button>
                </li>
            </ul>
        </div>

        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
            <li v-for="person in visiblePeople" :key="person.id" class="flex flex-wrap items-start gap-x-4 gap-y-2 px-4 py-2.5 sm:flex-nowrap">
                <div class="w-48 shrink-0">
                    <div class="flex items-center gap-2 font-medium">
                        <span class="h-2 w-2 shrink-0 rounded-full" :class="person.is_open ? 'bg-green-500' : 'bg-gray-300'" aria-hidden="true" />
                        {{ person.name }}
                    </div>
                    <div class="text-xs text-gray-500 tabular-nums">
                        <template v-if="person.worked_seconds || person.is_open">
                            {{ hoursLabel(person.worked_seconds, "0m") }}
                            <span v-if="person.breaks_seconds"> · {{ ctrans(":t break", { t: hoursLabel(person.breaks_seconds) }) }}</span>
                            <span v-if="person.is_open && isToday" class="text-green-600"> · {{ ctrans("on site") }}</span>
                            <span v-else-if="person.is_open" class="text-amber-700"> · {{ ctrans("no clock-out") }}</span>
                        </template>
                        <template v-else>{{ ctrans("No clockings") }}</template>
                    </div>
                </div>
                <ol class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5">
                    <li v-for="(clocking, index) in person.clockings" :key="clocking.id" class="group flex items-center gap-1 rounded-md border border-gray-200 bg-gray-50 py-0.5 pl-2 pr-0.5 text-sm" :class="{ 'border-amber-300 bg-amber-50': clocking.is_late && index === 0 }">
                        <FontAwesomeIcon :icon="index % 2 === 0 ? faSignInAlt : faSignOutAlt" fixed-width aria-hidden="true" class="text-xs" :class="index % 2 === 0 ? 'text-green-600' : 'text-gray-500'" />
                        <span class="tabular-nums" v-tooltip="sourceLabel(clocking) + (clocking.notes ? ' — ' + clocking.notes : '')">{{ timeIn(clocking.clocked_at, tz) }}</span>
                        <span v-if="clocking.is_late && index === 0" class="text-[10px] font-medium uppercase text-amber-700">{{ ctrans("late") }}</span>
                        <span v-if="clocking.type === 'manual'" class="text-[10px] text-gray-400" v-tooltip="sourceLabel(clocking)">✎</span>
                        <span class="flex items-center opacity-40 group-hover:opacity-100">
                            <Button type="transparent" size="xs" :icon="faPencil" v-tooltip="ctrans('Edit')" @click="emit('edit-clocking', { ...clocking, employee_id: person.id })" />
                            <ModalConfirmationDelete
                                :routeDelete="deleteRouteFor(clocking)"
                                :title="ctrans('Delete this clocking?')"
                                :description="ctrans('The working time it opened or closed is recalculated. This cannot be undone.')"
                            >
                                <template #default="{ changeModel }">
                                    <Button type="transparent" size="xs" :icon="faTrashAlt" v-tooltip="ctrans('Delete')" @click="changeModel(true)" />
                                </template>
                            </ModalConfirmationDelete>
                        </span>
                    </li>
                    <li>
                        <Button type="tertiary" size="xs" :icon="faPlus" v-tooltip="ctrans('Add clocking on this day')" @click="emit('add-clocking', person.id, data.date)" />
                    </li>
                </ol>
            </li>
            <li v-if="!visiblePeople.length" class="px-4 py-10 text-center text-sm text-gray-500">
                {{ ctrans("Nobody clocked on this day") }}
                <div class="mt-2"><Button type="tertiary" size="xs" :label="ctrans('Add a clocking')" :icon="faPlus" @click="emit('add-clocking', null, data.date)" /></div>
            </li>
        </ul>
    </div>
</template>
