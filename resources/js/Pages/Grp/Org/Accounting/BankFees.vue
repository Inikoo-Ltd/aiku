<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import DatePicker from "primevue/datepicker"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPercent } from "@fal"
library.add(faPercent)

const props = defineProps<{
    data: object
    title: string
    pageHead: PageHeadingTypes
    range: { from: string; to: string }
}>()

const locale = useLocaleStore()

const range = ref({ ...props.range })

const fromIsoDate = (iso: string): Date => {
    const [year, month, day] = iso.split("-").map(Number)
    return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const dateModel = (key: "from" | "to") =>
    computed<Date | null>({
        get: () => (range.value[key] ? fromIsoDate(range.value[key]) : null),
        set: (value) => {
            if (!value) return
            range.value = { ...range.value, [key]: toLocalIsoDate(value) }
            router.get(route(route().current() as string, route().params), { ...(route().queryParams ?? {}), ...range.value }, { preserveState: true, preserveScroll: true, replace: true })
        },
    })
const rangeFrom = dateModel("from")
const rangeTo = dateModel("to")

const datePickerPt = { pcInputText: { root: { class: "!w-32 !py-1 !text-xs" } } }
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="flex flex-wrap items-center gap-2 px-4 py-2 text-xs text-gray-500">
        <span>{{ ctrans("From") }}</span>
        <DatePicker v-model="rangeFrom" :maxDate="rangeTo ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('From')" />
        <span>{{ ctrans("To") }}</span>
        <DatePicker v-model="rangeTo" :minDate="rangeFrom ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('To')" />
    </div>
    <Table :resource="data">
        <template #cell(bank_fees)="{ item }">
            <span class="whitespace-nowrap tabular-nums">{{ locale.currencyFormat(item.currency_code, item.bank_fees) }}</span>
        </template>
        <template #cell(bank_base)="{ item }">
            <span class="whitespace-nowrap tabular-nums">{{ locale.currencyFormat(item.currency_code, item.bank_base) }}</span>
        </template>
        <template #cell(bank_fee_percentage)="{ item }">
            <span class="whitespace-nowrap tabular-nums">{{ item.bank_fee_percentage === null ? "-" : item.bank_fee_percentage.toFixed(2) + "%" }}</span>
        </template>
        <template #cell(absorbed_fees)="{ item }">
            <span class="whitespace-nowrap tabular-nums">{{ locale.currencyFormat(item.currency_code, item.absorbed_fees) }}</span>
        </template>
    </Table>
</template>
