<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 19 Nov 2026 Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script lang="ts">
export type PeriodAfterOpeningOption = { value: string, label: string }
export type RunShelfLife = { expiry_months: number | null, period_after_opening: string | null }

export const toIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

export function expiryFromMonths(months: number | null | undefined, from: Date = new Date()): string {
    if (!months || months < 1) {
        return ""
    }
    const date = new Date(from.getFullYear(), from.getMonth() + Number(months), from.getDate())
    if (date.getDate() !== from.getDate()) {
        date.setDate(0)
    }

    return toIsoDate(date)
}
</script>

<script setup lang="ts">
import { ref, watch } from "vue"
import DatePicker from "primevue/datepicker"
import InputNumber from "primevue/inputnumber"
import Select from "primevue/select"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { faCalendarDay, faHourglassHalf, faBoxOpen } from "@fal"

const props = defineProps<{
    periods: PeriodAfterOpeningOption[]
    required?: boolean
    months?: number | null
}>()

const expiryDate = defineModel<string>("expiryDate", { default: "" })
const periodAfterOpening = defineModel<string | null>("periodAfterOpening", { default: null })

type Mode = "date" | "months" | "after_opening"

const startsInMonths = !!props.months && !periodAfterOpening.value && expiryDate.value === expiryFromMonths(props.months)
const mode = ref<Mode>(periodAfterOpening.value ? "after_opening" : startsInMonths ? "months" : "date")
const months = ref<number | null>(startsInMonths ? props.months ?? null : null)

const modes = [
    { value: "date" as Mode, label: ctrans("Date"), icon: faCalendarDay },
    { value: "months" as Mode, label: ctrans("Months from today"), icon: faHourglassHalf },
    { value: "after_opening" as Mode, label: ctrans("After opening"), icon: faBoxOpen },
]

watch(mode, (value, previous) => {
    if (value === "after_opening") {
        periodAfterOpening.value ??= props.periods.find(period => period.value === "pao_12m")?.value ?? props.periods[0]?.value ?? null
        expiryDate.value = ""
        return
    }
    if (previous === "after_opening") {
        periodAfterOpening.value = null
    }
    if (value === "months" && months.value) {
        expiryDate.value = expiryFromMonths(months.value)
    }
})

watch(months, value => {
    if (mode.value === "months") {
        expiryDate.value = expiryFromMonths(value)
    }
})

const isoToDate = (iso?: string | null): Date | null => {
    if (!iso) return null
    const [year, month, day] = iso.split("-").map(Number)
    return new Date(year, month - 1, day)
}

const formatted = (iso: string) => isoToDate(iso)?.toLocaleDateString(undefined, { day: "2-digit", month: "2-digit", year: "numeric" }) ?? ""

const fieldPt = { root: { class: "w-full min-w-0" }, pcInputText: { root: { class: "!w-full !min-w-0 !py-0.5 !text-xs" } } }
const numberPt = { root: { class: "w-full min-w-0" }, pcInputText: { root: { class: "!w-full !min-w-0 !py-0.5 !text-xs" } } }
const selectPt = { root: { class: "w-full min-w-0 !text-xs" }, label: { class: "!py-0.5 !text-xs" } }
</script>

<template>
    <div class="flex min-w-0 flex-col gap-0.5">
        <div class="flex items-center justify-between gap-1">
            <span class="text-gray-500">
                {{ mode === "after_opening" ? ctrans("After opening") : ctrans("Expiry date") }}
                <span v-if="required" class="text-red-500">*</span>
            </span>
            <SegmentedToggle v-model="mode" :options="modes" iconOnly soft :ariaLabel="ctrans('How the run is dated')" class="run-expiry-mode" />
        </div>
        <DatePicker
            v-if="mode === 'date'"
            :modelValue="isoToDate(expiryDate)"
            dateFormat="dd/mm/yy"
            :required="required"
            :aria-label="ctrans('Expiry date')"
            :pt="fieldPt"
            @update:modelValue="date => expiryDate = date ? toIsoDate(date as Date) : ''" />
        <template v-else-if="mode === 'months'">
            <InputNumber v-model="months" @input="event => months = typeof event.value === 'number' ? event.value : null" :min="1" :max="240" :useGrouping="false" :suffix="' ' + ctrans('months')" :placeholder="ctrans('e.g. 24')" :required="required" :aria-label="ctrans('Months until it expires')" :pt="numberPt" />
            <span v-if="expiryDate" class="text-[11px] text-gray-400">{{ ctrans("Expires :date", { date: formatted(expiryDate) }) }}</span>
        </template>
        <Select
            v-else
            v-model="periodAfterOpening"
            :options="periods"
            optionLabel="label"
            optionValue="value"
            :aria-label="ctrans('Period after opening')"
            :pt="selectPt" />
    </div>
</template>

<style scoped>
.run-expiry-mode :deep(.p-togglebutton-content) {
    padding: 0 0.3rem;
}
</style>
