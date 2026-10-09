<script setup lang="ts">
import { computed } from "vue"
import Select from "primevue/select"
import DatePicker from "primevue/datepicker"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: {
        minuteStep?: number
    }
}>()

const minuteStep = props.fieldData?.minuteStep ?? 30
const timeOptions: string[] = []
for (let minutes = 0; minutes < 24 * 60; minutes += minuteStep) {
    timeOptions.push(`${String(Math.floor(minutes / 60)).padStart(2, "0")}:${String(minutes % 60).padStart(2, "0")}`)
}

const fromIsoDate = (iso: string): Date => {
    const [year, month, day] = iso.split("-").map(Number)
    return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const datePart = computed(() => (props.form[props.fieldName] ?? "").split(" ")[0] || null)
const timePart = computed(() => (props.form[props.fieldName] ?? "").split(" ")[1] || null)

const setValue = (date: string | null, time: string | null) => {
    props.form[props.fieldName] = date && time ? `${date} ${time}` : date ? `${date} ` : null
}

const dateModel = computed<Date | null>({
    get: () => (datePart.value ? fromIsoDate(datePart.value) : null),
    set: (value) => setValue(value ? toLocalIsoDate(value) : null, timePart.value),
})

const timeModel = computed<string | null>({
    get: () => timePart.value,
    set: (value) => setValue(datePart.value, value),
})

const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center gap-2">
            <DatePicker
                v-model="dateModel"
                dateFormat="D d M yy"
                :manualInput="false"
                :placeholder="ctrans('Date')"
                showIcon
                iconDisplay="input"
                :class="fieldFocusClass"
                :pt="{ pcInputText: { root: { class: '!w-48 !py-1.5 !text-sm' } } }"
                :aria-label="ctrans('Date')" />
            <Select
                v-model="timeModel"
                :options="timeOptions"
                :placeholder="ctrans('Time')"
                :class="fieldFocusClass"
                :pt="{ label: { class: '!py-1.5 !text-sm' } }"
                class="w-28"
                :aria-label="ctrans('Time')" />
        </div>
        <p v-if="form.errors?.[fieldName]" class="mt-2 text-sm text-red-600">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>
