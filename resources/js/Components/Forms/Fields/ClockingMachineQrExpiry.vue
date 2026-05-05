<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { get } from "lodash-es"
import { trans } from "laravel-vue-i18n"
import { InputNumber, Select } from "primevue"
import DatePicker from "primevue/datepicker"

const props = defineProps<{
    form: Record<string, any>
    fieldName: string
    fieldData?: {
        configuration_field?: string
    }
}>()

const MAX_SECONDS = 1_209_600

const modeOptions = [
    { label: trans("Relative duration"), value: "duration" },
    { label: trans("Specific date & time"), value: "custom_date" },
]

const unitOptions = [
    { label: trans("Second"), value: "second" },
    { label: trans("Minute"), value: "minute" },
    { label: trans("Hour"), value: "hour" },
    { label: trans("Day"), value: "day" },
    { label: trans("Week"), value: "week" },
]

const selectedMode = ref<"duration" | "custom_date">("duration")
const selectedUnit = ref<"second" | "minute" | "hour" | "day" | "week">("second")
const durationValue = ref<number>(1)
const customDate = ref<Date | null>(null)

const unitMultiplier = computed<number>(() => {
    if (selectedUnit.value === "minute") {
        return 60
    }
    if (selectedUnit.value === "hour") {
        return 3600
    }
    if (selectedUnit.value === "day") {
        return 86400
    }
    if (selectedUnit.value === "week") {
        return 604800
    }
    return 1
})

const maxValueForUnit = computed<number>(() => Math.floor(MAX_SECONDS / unitMultiplier.value))
const configurationField = computed(() => props.fieldData?.configuration_field || "config.qr.expiry_configuration")
const minCustomDate = computed(() => new Date())
const maxCustomDate = computed(() => {
    const date = new Date()
    date.setDate(date.getDate() + 14)
    return date
})

const syncDurationToField = (): void => {
    const normalized = Math.min(Math.max(Number(durationValue.value || 1), 1), maxValueForUnit.value)
    durationValue.value = normalized
    props.form[props.fieldName] = normalized * unitMultiplier.value
    props.form.errors[props.fieldName] = null
    props.form[configurationField.value] = {
        mode: "duration",
        unit: selectedUnit.value,
        value: normalized,
        custom_at: null,
    }
    props.form.errors[configurationField.value] = null
}

const syncCustomDateToField = (): void => {
    if (!customDate.value) {
        return
    }

    const now = new Date()
    const chosen = new Date(customDate.value)
    const bounded = chosen > maxCustomDate.value
        ? maxCustomDate.value
        : chosen < now
            ? now
            : chosen

    const seconds = Math.max(1, Math.floor((bounded.getTime() - now.getTime()) / 1000))
    props.form[props.fieldName] = Math.min(seconds, MAX_SECONDS)
    props.form.errors[props.fieldName] = null
    props.form[configurationField.value] = {
        mode: "custom_date",
        unit: null,
        value: null,
        custom_at: bounded.toISOString(),
    }
    props.form.errors[configurationField.value] = null
}

const hydrateFromExistingConfiguration = (): void => {
    const raw = props.form[configurationField.value]
    if (!raw || typeof raw !== "object") {
        return
    }

    if (raw.mode === "custom_date") {
        selectedMode.value = "custom_date"
        if (raw.custom_at) {
            const parsed = new Date(raw.custom_at)
            if (!Number.isNaN(parsed.getTime())) {
                customDate.value = parsed
            }
        }
        return
    }

    if (raw.mode === "duration") {
        selectedMode.value = "duration"
        if (["second", "minute", "hour", "day", "week"].includes(raw.unit)) {
            selectedUnit.value = raw.unit
        }
        if (typeof raw.value === "number" && Number.isFinite(raw.value)) {
            durationValue.value = raw.value
        }
    }
}

hydrateFromExistingConfiguration()

watch(
    () => selectedMode.value,
    (mode) => {
        if (mode === "duration") {
            syncDurationToField()
            return
        }

        if (!customDate.value) {
            const defaultDate = new Date()
            defaultDate.setHours(defaultDate.getHours() + 1)
            customDate.value = defaultDate
        }
        syncCustomDateToField()
    },
    { immediate: true }
)

watch(
    () => [selectedUnit.value, durationValue.value],
    () => {
        if (selectedMode.value === "duration") {
            syncDurationToField()
        }
    }
)

watch(
    () => customDate.value,
    () => {
        if (selectedMode.value === "custom_date") {
            syncCustomDateToField()
        }
    }
)
</script>

<template>
    <div class="space-y-3">
        <div class="grid gap-3 md:grid-cols-3">
            <div class="space-y-1">
                <div class="text-xs font-medium text-gray-500">{{ trans("Expiry Mode") }}</div>
                <Select
                    v-model="selectedMode"
                    :options="modeOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                />
            </div>

            <template v-if="selectedMode === 'duration'">
                <div class="space-y-1">
                    <div class="text-xs font-medium text-gray-500">{{ trans("Duration unit") }}</div>
                    <Select
                        v-model="selectedUnit"
                        :options="unitOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>

                <div class="space-y-1">
                    <div class="text-xs font-medium text-gray-500">{{ trans("Duration value") }}</div>
                    <InputNumber
                        v-model="durationValue"
                        :min="1"
                        :max="maxValueForUnit"
                        :useGrouping="false"
                        showButtons
                        fluid
                    />
                </div>
            </template>

            <div v-else class="space-y-1 md:col-span-2">
                <div class="text-xs font-medium text-gray-500">{{ trans("Expiry date & time") }}</div>
                <DatePicker
                    v-model="customDate"
                    :minDate="minCustomDate"
                    :maxDate="maxCustomDate"
                    showTime
                    hourFormat="24"
                    dateFormat="yy-mm-dd"
                    showIcon
                    fluid
                />
            </div>
        </div>

        <div class="text-xs text-gray-400">
            {{ trans("Maximum expiry is 2 weeks from now.") }}
        </div>

        <p v-if="get(form, ['errors', fieldName])" class="text-sm text-red-600">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>
