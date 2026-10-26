<script setup lang="ts">
import { Head, Link, router, useForm } from "@inertiajs/vue3"
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue"
import { usePrimeVue } from "primevue/config"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Textarea from "primevue/textarea"
import DatePicker from "primevue/datepicker"
import ToggleSwitch from "primevue/toggleswitch"
import MultiSelect from "primevue/multiselect"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCalendarAlt, faCalendarDay, faChevronLeft, faChevronRight, faPlus, faCheck, faTimes, faTrashAlt, faExclamationTriangle, faMapMarkerAlt, faUsers, faClock, faStore } from "@fal"

library.add(faCalendarAlt, faCalendarDay, faChevronLeft, faChevronRight, faPlus, faCheck, faTimes, faTrashAlt, faExclamationTriangle, faMapMarkerAlt, faUsers, faClock, faStore)

type Participant = {
    id: number
    name: string
}

type CalendarEvent = {
    id: number
    title: string
    description: string | null
    location: string | null
    is_all_day: boolean
    time: string | null
    starts_at: string
    ends_at: string
    participants: Participant[]
    created_by_name: string | null
}

type CalendarAppointment = {
    id: number
    time: string
    end_time: string
    contact_name: string | null
    appointment_type_name: string | null
    staff_name: string | null
    shop_code: string
    state: "requested" | "accepted" | "completed" | "no_show"
    state_label: string
    url: string | null
}

type CalendarBlock = {
    id: number
    is_all_day: boolean
    time: string | null
    end_time: string | null
    reason: string | null
    appointment_type_name: string | null
    shop_code: string
}

type CalendarDay = {
    date: string
    day: number
    in_month: boolean
    is_today: boolean
    events: CalendarEvent[]
    appointments: CalendarAppointment[]
    blocks: CalendarBlock[]
}

type DayItem =
    | { kind: "event"; key: string; sortTime: string; event: CalendarEvent }
    | { kind: "appointment"; key: string; sortTime: string; appointment: CalendarAppointment }
    | { kind: "block"; key: string; sortTime: string; block: CalendarBlock }

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    calendar: {
        month: string
        label: string
        previous: string
        next: string
        current: string
        now: string
        timezone: string
        weekdays: string[]
        total: number
        weeks: CalendarDay[][]
    }
    can_edit: boolean
    participant_options: Participant[]
    routes: {
        calendar: routeType
        store: routeType
        update: routeType
        delete: routeType
    }
}>()

const locale = useLocaleStore()
const loadingMonth = ref("")

const goTo = (month: string) => {
    loadingMonth.value = month
    router.get(route(props.routes.calendar.name, { ...props.routes.calendar.parameters, month }), {}, {
        preserveScroll: true,
        preserveState: true,
        only: ["calendar"],
        onFinish: () => (loadingMonth.value = ""),
    })
}

const appointmentStateClass = (state: CalendarAppointment["state"]) => ({
    requested: "border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100",
    accepted: "border-green-200 bg-green-50 text-green-800 hover:bg-green-100",
    completed: "border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100",
    no_show: "border-gray-200 bg-white text-gray-400 line-through hover:bg-gray-50",
}[state])

const eventClass = "border-[--app-accent-muted] bg-[--app-accent-soft] text-[--app-accent-deep] hover:border-[--app-accent]"

const legend = computed(() => [
    { key: "event", label: ctrans("Event"), class: eventClass },
    { key: "requested", label: ctrans("Appointment requested"), class: appointmentStateClass("requested") },
    { key: "accepted", label: ctrans("Appointment accepted"), class: appointmentStateClass("accepted") },
    { key: "completed", label: ctrans("Appointment completed"), class: appointmentStateClass("completed") },
    { key: "block", label: ctrans("Shop blocked for bookings"), class: blockClass },
])

const blockClass = "border-dashed border-gray-300 bg-gray-100 text-gray-600 hover:bg-gray-200"

const dayItems = (day: CalendarDay): DayItem[] => [
    ...day.events.map(event => ({ kind: "event" as const, key: `event-${event.id}`, sortTime: event.time ?? "", event })),
    ...day.appointments.map(appointment => ({ kind: "appointment" as const, key: `appointment-${appointment.id}`, sortTime: appointment.time, appointment })),
    ...day.blocks.map(block => ({ kind: "block" as const, key: `block-${block.id}`, sortTime: block.time ?? "", block })),
].sort((a, b) => a.sortTime.localeCompare(b.sortTime))

const daysWithItems = computed(() => props.calendar.weeks.flat().filter(day => day.in_month && dayItems(day).length))

const blockLabel = (block: CalendarBlock) => [
    ctrans("Blocked"),
    block.shop_code,
    block.appointment_type_name,
].filter(Boolean).join(" · ")

const blockTooltip = (block: CalendarBlock) => [
    block.is_all_day || (!block.time && !block.end_time) ? ctrans("All day") : `${block.time ?? ""}–${block.end_time ?? ""}`,
    blockLabel(block),
    block.reason,
].filter(Boolean).join(" · ")

const appointmentTooltip = (appointment: CalendarAppointment) => [
    `${appointment.time}–${appointment.end_time} ${appointment.contact_name ?? ctrans("Appointment")}`,
    appointment.appointment_type_name,
    appointment.shop_code,
    appointment.staff_name,
    appointment.state_label,
].filter(Boolean).join(" · ")

const eventTooltip = (event: CalendarEvent) => [
    event.is_all_day ? ctrans("All day") : event.time,
    event.title,
    event.participants.map(participant => participant.name).join(", "),
].filter(Boolean).join(" · ")

const pad = (value: number) => String(value).padStart(2, "0")
const toWallTime = (date: Date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
const fromWallTime = (value: string) => {
    const [datePart, timePart = "00:00"] = value.split(" ")
    const [year, month, day] = datePart.split("-").map(Number)
    const [hours, minutes] = timePart.split(":").map(Number)
    return new Date(year, month - 1, day, hours, minutes)
}
const longDateTime = (value: string, withTime: boolean) => {
    const date = fromWallTime(value)
    const language = locale.language.code
    const weekday = date.toLocaleDateString(language, { weekday: "short" })
    const month = date.toLocaleDateString(language, { month: "short" })
    const day = `${weekday} ${date.getDate()} ${month} ${date.getFullYear()}`

    return withTime ? `${day}, ${pad(date.getHours())}:${pad(date.getMinutes())}` : day
}

const isDialogOpen = ref(false)
const editingEvent = ref<CalendarEvent | null>(null)
const isConfirmingDelete = ref(false)
const isDeleting = ref(false)

const form = useForm({
    title: "",
    description: "",
    location: "",
    is_all_day: false,
    starts_at: "",
    ends_at: "",
    participants: [] as number[],
    allow_overlap: false,
})

const isReadOnly = computed(() => !props.can_edit)

const wallTimeModel = (key: "starts_at" | "ends_at") => computed<Date | null>({
    get: () => (form[key] ? fromWallTime(form[key]) : null),
    set: (value) => {
        form[key] = value ? toWallTime(value) : ""
        if (key === "starts_at" && value && form.ends_at && fromWallTime(form.ends_at) <= value) {
            form.ends_at = toWallTime(new Date(value.getTime() + 60 * 60 * 1000))
        }
    },
})
const startsAtModel = wallTimeModel("starts_at")
const endsAtModel = wallTimeModel("ends_at")

const openNewEvent = (date?: string) => {
    if (!props.can_edit) {
        return
    }
    const start = fromWallTime(date ? `${date} 09:00` : props.calendar.now)
    if (!date) {
        start.setMinutes(0, 0, 0)
        start.setHours(start.getHours() + 1)
    }
    editingEvent.value = null
    form.clearErrors()
    form.title = ""
    form.description = ""
    form.location = ""
    form.is_all_day = false
    form.participants = []
    form.allow_overlap = false
    form.starts_at = toWallTime(start)
    form.ends_at = toWallTime(new Date(start.getTime() + 60 * 60 * 1000))
    isConfirmingDelete.value = false
    isDialogOpen.value = true
}

const openEvent = (event: CalendarEvent) => {
    editingEvent.value = event
    form.clearErrors()
    form.title = event.title
    form.description = event.description ?? ""
    form.location = event.location ?? ""
    form.is_all_day = event.is_all_day
    form.starts_at = event.starts_at
    form.ends_at = event.ends_at
    form.participants = event.participants.map(participant => participant.id)
    form.allow_overlap = false
    isConfirmingDelete.value = false
    isDialogOpen.value = true
}

const onAllDayToggled = (isAllDay: boolean) => {
    form.is_all_day = isAllDay
    if (isAllDay || !form.starts_at || !form.ends_at) {
        return
    }
    const firstDay = form.starts_at.slice(0, 10)
    const lastDay = form.ends_at.slice(0, 10)
    form.starts_at = `${firstDay} 09:00`
    form.ends_at = lastDay === firstDay ? `${firstDay} 10:00` : `${lastDay} 17:00`
}

watch(() => [form.participants, form.starts_at, form.ends_at, form.is_all_day], () => form.clearErrors("overlap" as keyof typeof form.data), { deep: true })

const overlapLines = computed(() => (form.errors as Record<string, string>).overlap?.split("\n") ?? [])

const submit = (allowOverlap = false) => {
    form.allow_overlap = allowOverlap
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            isDialogOpen.value = false
            notify({ title: ctrans("Saved"), text: ctrans("The calendar is updated."), type: "success" })
        },
    }

    if (editingEvent.value) {
        form.patch(route(props.routes.update.name, { ...props.routes.update.parameters, calendarEvent: editingEvent.value.id }), options)
    } else {
        form.post(route(props.routes.store.name, props.routes.store.parameters), options)
    }
}

const deleteEvent = () => {
    if (!editingEvent.value) {
        return
    }
    isDeleting.value = true
    router.delete(route(props.routes.delete.name, { ...props.routes.delete.parameters, calendarEvent: editingEvent.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isDialogOpen.value = false
            notify({ title: ctrans("Deleted"), text: ctrans("The event is removed from the calendar."), type: "success" })
        },
        onFinish: () => (isDeleting.value = false),
    })
}

const dialogTitle = computed(() => {
    if (!editingEvent.value) {
        return ctrans("New event")
    }

    return props.can_edit ? ctrans("Edit event") : ctrans("Event")
})

const primeVue = usePrimeVue()
const appFirstDayOfWeek = primeVue.config.locale?.firstDayOfWeek
onMounted(() => {
    if (primeVue.config.locale) {
        primeVue.config.locale.firstDayOfWeek = 1
    }
})
onBeforeUnmount(() => {
    if (primeVue.config.locale) {
        primeVue.config.locale.firstDayOfWeek = appFirstDayOfWeek
    }
})

const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"
const dateFormat = "d M yy"
</script>

<template>
    <div>
        <Head :title="capitalize(title)" />
        <PageHeading :data="pageHead" />

        <div class="space-y-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <Button type="tertiary" :label="ctrans('Previous')" :loading="loadingMonth === calendar.previous" @click="goTo(calendar.previous)">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-chevron-left" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button type="tertiary" :label="ctrans('Today')" :disabled="calendar.month === calendar.current" :loading="loadingMonth === calendar.current" @click="goTo(calendar.current)">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-calendar-day" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button type="tertiary" :label="ctrans('Next')" :loading="loadingMonth === calendar.next" @click="goTo(calendar.next)">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-chevron-right" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button v-if="can_edit" type="tertiary" :label="ctrans('New event')" @click="openNewEvent()">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-plus" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
                <div class="text-right">
                    <h2 class="text-lg font-semibold text-gray-800">{{ calendar.label }}</h2>
                    <div class="text-xs text-gray-500">
                        {{ ctrans(":count events and appointments", { count: locale.number(calendar.total) }) }} · {{ calendar.timezone }}
                    </div>
                </div>
            </div>

            <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600">
                <li v-for="item in legend" :key="item.key" class="flex items-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded-sm border" :class="item.class" aria-hidden="true" />
                    {{ item.label }}
                </li>
            </ul>

            <div class="hidden overflow-hidden rounded-lg border border-gray-200 bg-white md:block">
                <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <div v-for="weekday in calendar.weekdays" :key="weekday" class="px-2 py-2">{{ weekday }}</div>
                </div>
                <div v-for="(week, weekIndex) in calendar.weeks" :key="weekIndex" class="grid grid-cols-7 border-b border-gray-100 last:border-b-0">
                    <div
                        v-for="day in week"
                        :key="day.date"
                        class="group min-h-28 min-w-0 border-r border-gray-100 p-1.5 last:border-r-0"
                        :class="day.in_month ? 'bg-white' : 'bg-gray-50'">
                        <div class="mb-1 flex items-center justify-between">
                            <button
                                v-if="can_edit"
                                type="button"
                                class="flex h-6 w-6 items-center justify-center rounded text-xs text-gray-400 opacity-0 transition hover:bg-[--app-accent-soft] hover:text-[--app-accent] focus:opacity-100 focus:outline-none focus:ring-2 focus:ring-[--app-accent] group-hover:opacity-100"
                                :aria-label="ctrans('New event on :date', { date: day.date })"
                                v-tooltip="ctrans('New event')"
                                @click="openNewEvent(day.date)">
                                <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                            </button>
                            <span v-else />
                            <span
                                class="flex h-6 min-w-6 items-center justify-center rounded-full px-1 text-xs tabular-nums"
                                :class="day.is_today ? 'bg-[--app-accent] font-semibold text-[--app-accent-text]' : day.in_month ? 'text-gray-700' : 'text-gray-400'">
                                {{ day.day }}
                            </span>
                        </div>
                        <ul class="space-y-1">
                            <li v-for="item in dayItems(day)" :key="item.key">
                                <button
                                    v-if="item.kind === 'event'"
                                    type="button"
                                    v-tooltip="eventTooltip(item.event)"
                                    class="block w-full truncate rounded border px-1.5 py-0.5 text-left text-xs focus:outline-none focus:ring-2 focus:ring-[--app-accent]"
                                    :class="eventClass"
                                    @click="openEvent(item.event)">
                                    <span v-if="item.event.time" class="font-semibold tabular-nums">{{ item.event.time }}</span>
                                    {{ item.event.title }}
                                </button>
                                <component
                                    v-else-if="item.kind === 'appointment'"
                                    :is="item.appointment.url ? Link : 'span'"
                                    :href="item.appointment.url ?? undefined"
                                    v-tooltip="appointmentTooltip(item.appointment)"
                                    class="block truncate rounded border px-1.5 py-0.5 text-xs focus:outline-none focus:ring-2 focus:ring-[--app-accent]"
                                    :class="appointmentStateClass(item.appointment.state)">
                                    <span class="font-semibold tabular-nums">{{ item.appointment.time }}</span>
                                    {{ item.appointment.contact_name ?? ctrans("Appointment") }}
                                    <span class="opacity-70">· {{ item.appointment.shop_code }}</span>
                                </component>
                                <span
                                    v-else
                                    v-tooltip="blockTooltip(item.block)"
                                    class="block truncate rounded border px-1.5 py-0.5 text-xs"
                                    :class="blockClass">
                                    <span v-if="item.block.time" class="font-semibold tabular-nums">{{ item.block.time }}</span>
                                    {{ blockLabel(item.block) }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="md:hidden">
                <p v-if="!daysWithItems.length" class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-500">
                    {{ ctrans("Nothing in the calendar this month") }}
                </p>
                <ul v-else class="space-y-3">
                    <li v-for="day in daysWithItems" :key="day.date" class="rounded-lg border border-gray-200 bg-white p-3">
                        <div class="mb-2 text-sm font-semibold" :class="day.is_today ? 'text-[--app-accent]' : 'text-gray-700'">
                            {{ new Date(`${day.date}T00:00:00`).toLocaleDateString(undefined, { weekday: "long", day: "numeric", month: "long" }) }}
                        </div>
                        <ul class="space-y-1">
                            <li v-for="item in dayItems(day)" :key="item.key">
                                <button v-if="item.kind === 'event'" type="button" class="block w-full rounded border px-2 py-1 text-left text-sm focus:outline-none focus:ring-2 focus:ring-[--app-accent]" :class="eventClass" @click="openEvent(item.event)">
                                    <span class="font-semibold tabular-nums">{{ item.event.is_all_day ? ctrans("All day") : item.event.time }}</span>
                                    {{ item.event.title }}
                                    <span v-if="item.event.participants.length" class="block truncate text-xs opacity-80">{{ item.event.participants.map(participant => participant.name).join(", ") }}</span>
                                </button>
                                <component
                                    v-else-if="item.kind === 'appointment'"
                                    :is="item.appointment.url ? Link : 'span'"
                                    :href="item.appointment.url ?? undefined"
                                    class="block rounded border px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-[--app-accent]"
                                    :class="appointmentStateClass(item.appointment.state)">
                                    <span class="font-semibold tabular-nums">{{ item.appointment.time }}</span>
                                    {{ item.appointment.contact_name ?? ctrans("Appointment") }}
                                    <span class="block truncate text-xs opacity-80">{{ [item.appointment.appointment_type_name, item.appointment.shop_code, item.appointment.staff_name].filter(Boolean).join(" · ") }}</span>
                                </component>
                                <div v-else class="rounded border px-2 py-1 text-sm" :class="blockClass">
                                    <span class="font-semibold tabular-nums">{{ item.block.time ?? ctrans("All day") }}</span>
                                    {{ blockLabel(item.block) }}
                                    <span v-if="item.block.reason" class="block truncate text-xs opacity-80">{{ item.block.reason }}</span>
                                </div>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>

        <Dialog v-model:visible="isDialogOpen" modal :header="dialogTitle" :draggable="false" :style="{ width: '34rem', maxWidth: '95vw' }">
            <div v-if="isReadOnly && editingEvent" class="space-y-3 text-sm text-gray-700">
                <h3 class="text-base font-semibold text-gray-900">{{ editingEvent.title }}</h3>
                <div class="flex gap-2">
                    <FontAwesomeIcon icon="fal fa-clock" class="mt-0.5 text-gray-400" fixed-width aria-hidden="true" />
                    <span v-if="editingEvent.is_all_day">
                        {{ longDateTime(editingEvent.starts_at, false) }}<template v-if="editingEvent.ends_at.slice(0, 10) !== editingEvent.starts_at.slice(0, 10)"> – {{ longDateTime(editingEvent.ends_at, false) }}</template> · {{ ctrans("All day") }}
                    </span>
                    <span v-else>{{ longDateTime(editingEvent.starts_at, true) }} – {{ longDateTime(editingEvent.ends_at, true) }}</span>
                </div>
                <div v-if="editingEvent.location" class="flex gap-2">
                    <FontAwesomeIcon icon="fal fa-map-marker-alt" class="mt-0.5 text-gray-400" fixed-width aria-hidden="true" />
                    <span>{{ editingEvent.location }}</span>
                </div>
                <div v-if="editingEvent.participants.length" class="flex gap-2">
                    <FontAwesomeIcon icon="fal fa-users" class="mt-0.5 text-gray-400" fixed-width aria-hidden="true" />
                    <span>{{ editingEvent.participants.map(participant => participant.name).join(", ") }}</span>
                </div>
                <p v-if="editingEvent.description" class="whitespace-pre-line">{{ editingEvent.description }}</p>
                <p v-if="editingEvent.created_by_name" class="text-xs text-gray-400">{{ ctrans("Added by :name", { name: editingEvent.created_by_name }) }}</p>
            </div>

            <form v-else class="space-y-4 text-sm" @submit.prevent="submit(false)">
                <div>
                    <label for="calendar-event-title" class="mb-1 block font-semibold text-gray-700">{{ ctrans("Title") }} *</label>
                    <InputText id="calendar-event-title" v-model="form.title" class="w-full" :class="fieldFocusClass" :invalid="!!form.errors.title" autofocus />
                    <small v-if="form.errors.title" class="text-red-600">{{ form.errors.title }}</small>
                </div>

                <div class="flex items-center gap-2">
                    <ToggleSwitch :modelValue="form.is_all_day" inputId="calendar-event-all-day" @update:modelValue="onAllDayToggled" />
                    <label for="calendar-event-all-day" class="text-gray-700">{{ ctrans("All day") }}</label>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="calendar-event-starts" class="mb-1 block font-semibold text-gray-700">{{ ctrans("Starts") }} *</label>
                        <DatePicker
                            v-model="startsAtModel"
                            inputId="calendar-event-starts"
                            :showTime="!form.is_all_day"
                            hourFormat="24"
                            :stepMinute="15"
                            :dateFormat="dateFormat"
                            :manualInput="false"
                            showIcon
                            iconDisplay="input"
                            fluid
                            :class="fieldFocusClass"
                            :invalid="!!form.errors.starts_at" />
                        <small v-if="form.errors.starts_at" class="text-red-600">{{ form.errors.starts_at }}</small>
                    </div>
                    <div>
                        <label for="calendar-event-ends" class="mb-1 block font-semibold text-gray-700">{{ ctrans("Ends") }} *</label>
                        <DatePicker
                            v-model="endsAtModel"
                            inputId="calendar-event-ends"
                            :showTime="!form.is_all_day"
                            hourFormat="24"
                            :stepMinute="15"
                            :minDate="startsAtModel ?? undefined"
                            :dateFormat="dateFormat"
                            :manualInput="false"
                            showIcon
                            iconDisplay="input"
                            fluid
                            :class="fieldFocusClass"
                            :invalid="!!form.errors.ends_at" />
                        <small v-if="form.errors.ends_at" class="text-red-600">{{ form.errors.ends_at }}</small>
                    </div>
                </div>
                <p class="-mt-2 text-xs text-gray-500">{{ ctrans("Times are in :timezone.", { timezone: calendar.timezone }) }}</p>

                <div>
                    <label for="calendar-event-participants" class="mb-1 block font-semibold text-gray-700">{{ ctrans("Participants") }}</label>
                    <MultiSelect
                        v-model="form.participants"
                        inputId="calendar-event-participants"
                        :options="participant_options"
                        optionLabel="name"
                        optionValue="id"
                        display="chip"
                        filter
                        :placeholder="ctrans('Who takes part')"
                        class="w-full"
                        :class="fieldFocusClass"
                        :invalid="!!form.errors.participants" />
                    <small v-if="form.errors.participants" class="text-red-600">{{ form.errors.participants }}</small>
                </div>

                <div>
                    <label for="calendar-event-location" class="mb-1 block font-semibold text-gray-700">{{ ctrans("Location") }}</label>
                    <InputText id="calendar-event-location" v-model="form.location" class="w-full" :class="fieldFocusClass" :invalid="!!form.errors.location" />
                    <small v-if="form.errors.location" class="text-red-600">{{ form.errors.location }}</small>
                </div>

                <div>
                    <label for="calendar-event-description" class="mb-1 block font-semibold text-gray-700">{{ ctrans("Purpose and notes") }}</label>
                    <Textarea id="calendar-event-description" v-model="form.description" rows="3" autoResize class="w-full" :class="fieldFocusClass" :invalid="!!form.errors.description" />
                    <small v-if="form.errors.description" class="text-red-600">{{ form.errors.description }}</small>
                </div>

                <div v-if="overlapLines.length" class="rounded-md border border-amber-300 bg-amber-50 p-3 text-amber-900">
                    <div class="mb-1 flex items-center gap-2 font-semibold">
                        <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-amber-500" fixed-width aria-hidden="true" />
                        {{ ctrans("Some participants are already busy at this time") }}
                    </div>
                    <ul class="list-disc space-y-0.5 pl-6 text-xs">
                        <li v-for="line in overlapLines" :key="line">{{ line }}</li>
                    </ul>
                </div>

                <div v-if="isConfirmingDelete" class="rounded-md border border-amber-300 bg-amber-50 p-3 text-amber-900">
                    {{ ctrans("Delete this event? It is removed from the calendar for everyone.") }}
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                    <div>
                        <template v-if="editingEvent">
                            <Button v-if="!isConfirmingDelete" type="tertiary" :label="ctrans('Delete')" @click="isConfirmingDelete = true">
                                <template #icon>
                                    <FontAwesomeIcon icon="fal fa-trash-alt" class="text-red-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                            <Button v-else type="tertiary" :label="ctrans('Yes, delete')" :loading="isDeleting" @click="deleteEvent">
                                <template #icon>
                                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-amber-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                        </template>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button type="tertiary" :label="ctrans('Cancel')" @click="isConfirmingDelete ? (isConfirmingDelete = false) : (isDialogOpen = false)">
                            <template #icon>
                                <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                        <Button v-if="overlapLines.length && !isConfirmingDelete" type="tertiary" :label="ctrans('Save anyway')" :loading="form.processing && form.allow_overlap" @click="submit(true)">
                            <template #icon>
                                <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-amber-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                        <Button v-if="!isConfirmingDelete" type="tertiary" :label="ctrans('Save')" :loading="form.processing && !form.allow_overlap" @click="submit(false)">
                            <template #icon>
                                <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </div>
                </div>
            </form>
        </Dialog>
    </div>
</template>
