<script setup lang="ts">
import { computed, ref } from "vue"
import Select from "primevue/select"
import DatePicker from "primevue/datepicker"
import ToggleSwitch from "primevue/toggleswitch"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus, faTrashAlt, faCalendarPlus } from "@fal"
import { ctrans } from "@/Composables/useTrans"

type HoursRange = { from: string, to: string }
type AvailabilityDate = { date: string, hours: HoursRange[] }
type Availability = { weekly: Record<string, HoursRange[]>, dates: AvailabilityDate[] }

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: {
        minuteStep?: number
    }
}>()

const normaliseAvailability = (value: Partial<Availability> | null | undefined): Availability => {
    const weekly: Record<string, HoursRange[]> = {}
    for (let dayOfWeek = 1; dayOfWeek <= 7; dayOfWeek++) {
        weekly[dayOfWeek] = [...(value?.weekly?.[dayOfWeek] ?? [])]
    }
    return { weekly, dates: [...(value?.dates ?? [])] }
}

props.form[props.fieldName] = normaliseAvailability(props.form[props.fieldName])
const availability = computed<Availability>(() => props.form[props.fieldName])

const errorMessages = computed<string[]>(() => Object.entries(props.form.errors ?? {})
    .filter(([key]) => key === props.fieldName || key.startsWith(`${props.fieldName}.`))
    .map(([, message]) => message as string))

const minuteStep = props.fieldData?.minuteStep ?? 30
const timeOptions: string[] = []
for (let minutes = 0; minutes < 24 * 60; minutes += minuteStep) {
    timeOptions.push(`${String(Math.floor(minutes / 60)).padStart(2, "0")}:${String(minutes % 60).padStart(2, "0")}`)
}

const weekDays = [
    { dayOfWeek: 1, label: ctrans("Monday") },
    { dayOfWeek: 2, label: ctrans("Tuesday") },
    { dayOfWeek: 3, label: ctrans("Wednesday") },
    { dayOfWeek: 4, label: ctrans("Thursday") },
    { dayOfWeek: 5, label: ctrans("Friday") },
    { dayOfWeek: 6, label: ctrans("Saturday") },
    { dayOfWeek: 7, label: ctrans("Sunday") },
]

const shiftTime = (time: string, steps: number): string => {
    const index = Math.min(Math.max(timeOptions.indexOf(time) + steps, 0), timeOptions.length - 1)
    return timeOptions[index]
}

const addRange = (hours: HoursRange[]) => {
    const previous = hours[hours.length - 1]
    if (!previous) {
        hours.push({ from: "10:00", to: "17:00" })
        return
    }
    const from = shiftTime(previous.to, 60 / minuteStep)
    hours.push({ from, to: shiftTime(from, 120 / minuteStep) })
}

const setOpen = (hours: HoursRange[], isOpen: boolean) => {
    hours.splice(0, hours.length)
    if (isOpen) {
        addRange(hours)
    }
}

const fromIsoDate = (iso: string): Date => {
    const [year, month, day] = iso.split("-").map(Number)
    return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const isoDayOfWeek = (date: Date): number => date.getDay() === 0 ? 7 : date.getDay()

const today = new Date()
today.setHours(0, 0, 0, 0)

const pickedDates = computed(() => availability.value.dates.map(date => fromIsoDate(date.date)))
const datePickerValue = ref<Date | null>(null)

const addDate = (date: Date | null) => {
    if (!date) {
        return
    }
    const isoDate = toLocalIsoDate(date)
    if (!availability.value.dates.some(existing => existing.date === isoDate)) {
        availability.value.dates.push({
            date: isoDate,
            hours: (availability.value.weekly[isoDayOfWeek(date)] ?? []).map(range => ({ ...range })),
        })
        availability.value.dates.sort((a, b) => a.date.localeCompare(b.date))
    }
    datePickerValue.value = null
}

const removeDate = (index: number) => {
    availability.value.dates.splice(index, 1)
}

const dateLabel = (isoDate: string): string => fromIsoDate(isoDate).toLocaleDateString(undefined, { weekday: "short", day: "numeric", month: "short", year: "numeric" })

const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"
const selectPt = { label: { class: "!py-1 !text-sm" } }
</script>

<template>
    <div class="space-y-6">
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <div class="border-b border-gray-200 bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-700">
                {{ ctrans("Weekly hours") }}
            </div>

            <div v-for="day in weekDays" :key="day.dayOfWeek"
                class="flex flex-wrap items-start gap-x-4 gap-y-2 border-b border-gray-100 px-4 py-2 last:border-b-0">
                <div class="flex w-40 items-center gap-3 pt-1">
                    <ToggleSwitch
                        :modelValue="availability.weekly[day.dayOfWeek].length > 0"
                        :aria-label="ctrans('Open on :day', { day: day.label })"
                        @update:modelValue="(isOpen: boolean) => setOpen(availability.weekly[day.dayOfWeek], isOpen)" />
                    <span class="text-sm font-medium text-gray-700">{{ day.label }}</span>
                </div>

                <div v-if="!availability.weekly[day.dayOfWeek].length" class="pt-1.5 text-sm text-gray-400">
                    {{ ctrans("Closed") }}
                </div>

                <div v-else class="flex flex-col gap-2">
                    <div v-for="(range, rangeIndex) in availability.weekly[day.dayOfWeek]" :key="rangeIndex" class="flex items-center gap-2">
                        <Select v-model="range.from" :options="timeOptions" :class="fieldFocusClass" :pt="selectPt" class="w-28" :aria-label="ctrans('Opens at')" />
                        <span class="text-gray-400">–</span>
                        <Select v-model="range.to" :options="timeOptions" :class="fieldFocusClass" :pt="selectPt" class="w-28" :aria-label="ctrans('Closes at')" />
                        <Button type="transparent" size="xs" :icon="faTrashAlt" :aria-label="ctrans('Remove hours')"
                            v-tooltip="ctrans('Remove hours')"
                            @click="availability.weekly[day.dayOfWeek].splice(rangeIndex, 1)" />
                        <Button v-if="rangeIndex === availability.weekly[day.dayOfWeek].length - 1"
                            type="transparent" size="xs" :icon="faPlus" :aria-label="ctrans('Add hours')"
                            v-tooltip="ctrans('Add hours')"
                            @click="addRange(availability.weekly[day.dayOfWeek])" />
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-4 py-2">
                <div>
                    <div class="text-sm font-semibold text-gray-700">{{ ctrans("Specific dates") }}</div>
                    <div class="text-xs text-gray-500">{{ ctrans("Open extra hours or close on a picked date. These replace the weekly hours for that day.") }}</div>
                </div>
                <DatePicker
                    v-model="datePickerValue"
                    :minDate="today"
                    :disabledDates="pickedDates"
                    :manualInput="false"
                    :placeholder="ctrans('Pick a date')"
                    showIcon
                    iconDisplay="input"
                    :class="fieldFocusClass"
                    :pt="{ pcInputText: { root: { class: '!w-40 !py-1 !text-sm' } } }"
                    :aria-label="ctrans('Pick a date')"
                    @update:modelValue="addDate" />
            </div>

            <div v-if="!availability.dates.length" class="flex items-center gap-2 px-4 py-4 text-sm text-gray-400">
                <FontAwesomeIcon :icon="faCalendarPlus" fixed-width aria-hidden="true" />
                {{ ctrans("No dates picked, the weekly hours apply every day.") }}
            </div>

            <div v-for="(date, dateIndex) in availability.dates" :key="date.date"
                class="flex flex-wrap items-start gap-x-4 gap-y-2 border-b border-gray-100 px-4 py-2 last:border-b-0">
                <div class="flex w-56 items-center gap-3 pt-1">
                    <ToggleSwitch
                        :modelValue="date.hours.length > 0"
                        :aria-label="ctrans('Open on :day', { day: dateLabel(date.date) })"
                        @update:modelValue="(isOpen: boolean) => setOpen(date.hours, isOpen)" />
                    <span class="text-sm font-medium text-gray-700">{{ dateLabel(date.date) }}</span>
                </div>

                <div v-if="!date.hours.length" class="pt-1.5 text-sm font-medium text-red-500">
                    {{ ctrans("Closed all day") }}
                </div>

                <div v-else class="flex flex-col gap-2">
                    <div v-for="(range, rangeIndex) in date.hours" :key="rangeIndex" class="flex items-center gap-2">
                        <Select v-model="range.from" :options="timeOptions" :class="fieldFocusClass" :pt="selectPt" class="w-28" :aria-label="ctrans('Opens at')" />
                        <span class="text-gray-400">–</span>
                        <Select v-model="range.to" :options="timeOptions" :class="fieldFocusClass" :pt="selectPt" class="w-28" :aria-label="ctrans('Closes at')" />
                        <Button type="transparent" size="xs" :icon="faTrashAlt" :aria-label="ctrans('Remove hours')"
                            v-tooltip="ctrans('Remove hours')"
                            @click="date.hours.splice(rangeIndex, 1)" />
                        <Button v-if="rangeIndex === date.hours.length - 1"
                            type="transparent" size="xs" :icon="faPlus" :aria-label="ctrans('Add hours')"
                            v-tooltip="ctrans('Add hours')"
                            @click="addRange(date.hours)" />
                    </div>
                </div>

                <Button class="ml-auto" type="tertiary" size="xs" :icon="faTrashAlt" :label="ctrans('Remove date')"
                    @click="removeDate(dateIndex)" />
            </div>
        </div>

        <p v-for="message in errorMessages" :key="message" class="text-sm text-red-600">
            {{ message }}
        </p>
    </div>
</template>

<style scoped>
:deep(.p-toggleswitch.p-toggleswitch-checked .p-toggleswitch-slider) {
    background: var(--app-accent);
}
</style>
