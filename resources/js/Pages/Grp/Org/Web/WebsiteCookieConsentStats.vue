<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Fri, 27 Nov 2026 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import DatePicker from "primevue/datepicker"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"

/* Accept / reject / ignore rates of our own cookie banner (INI-572), for marketing's monthly report.
   Percentages only, out of the times the banner was shown. */

type Rates = { accepted_all: number | null, rejected_all: number | null, partial: number | null, ignored: number | null }

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    filters: { from: string, to: string }
    is_enabled: boolean
    stats: {
        shown: number
        choices: number
        counts: Record<keyof Rates, number>
        rates: Rates
        categories: { analytics: number | null, marketing: number | null, preferences: number | null }
        first_date: string | null
    }
    route: { name: string, parameters: Record<string, any> }
}>()

const locale = useLocaleStore()
const isLoading = ref(false)

const fromIsoDate = (iso: string): Date => {
    const [year, month, day] = iso.split("-").map(Number)
    return new Date(year, month - 1, day)
}
const toIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const visit = (range: { from: string, to: string }) =>
    router.get(route(props.route.name, { ...props.route.parameters, ...range }), {}, {
        only: ["filters", "stats"],
        preserveState: true,
        preserveScroll: true,
        onStart: () => (isLoading.value = true),
        onFinish: () => (isLoading.value = false),
    })

const dateModel = (key: "from" | "to") =>
    computed<Date>({
        get: () => fromIsoDate(props.filters[key]),
        set: (value) => value && visit({ ...props.filters, [key]: toIsoDate(value) }),
    })
const rangeFrom = dateModel("from")
const rangeTo = dateModel("to")

const today = new Date()
const presets = [
    { label: ctrans("This month"), from: new Date(today.getFullYear(), today.getMonth(), 1), to: today },
    { label: ctrans("Last month"), from: new Date(today.getFullYear(), today.getMonth() - 1, 1), to: new Date(today.getFullYear(), today.getMonth(), 0) },
]

const percent = (value: number | null) => (value === null ? "–" : `${locale.number(value)}%`)
const shortDate = (iso: string) => useFormatTime(iso, { formatTime: "PP" })

const rates = computed(() => [
    { key: "accepted_all" as const, label: ctrans("Accept rate"), hint: ctrans("Accepted all cookies"), class: "text-green-600" },
    { key: "partial" as const, label: ctrans("Partial accept rate"), hint: ctrans("Accepted some categories"), class: "text-amber-600" },
    { key: "rejected_all" as const, label: ctrans("Reject rate"), hint: ctrans("Rejected all optional cookies"), class: "text-red-600" },
    { key: "ignored" as const, label: ctrans("Ignore rate"), hint: ctrans("Saw the banner and made no choice"), class: "text-gray-600" },
])

const categories = computed(() => [
    { key: "analytics" as const, label: ctrans("Performance") },
    { key: "marketing" as const, label: ctrans("Targeting") },
    { key: "preferences" as const, label: ctrans("Functionality") },
])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="border-b border-gray-200 bg-white px-4 py-2">
        <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <DatePicker v-model="rangeFrom" :maxDate="rangeTo" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input"
                :pt="{ pcInputText: { root: { class: '!w-32 !py-1 !text-xs' } } }" :aria-label="ctrans('From')" />
            <span class="text-gray-400">–</span>
            <DatePicker v-model="rangeTo" :minDate="rangeFrom" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input"
                :pt="{ pcInputText: { root: { class: '!w-32 !py-1 !text-xs' } } }" :aria-label="ctrans('To')" />
            <Button v-for="preset in presets" :key="preset.label" type="tertiary" size="xs" :label="preset.label"
                @click="visit({ from: toIsoDate(preset.from), to: toIsoDate(preset.to) })" />
            <span v-if="isLoading" class="text-[var(--app-accent)]"><LoadingIcon /></span>
        </div>
    </div>

    <div class="space-y-4 p-4">
        <p v-if="!is_enabled" class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            {{ ctrans("Own cookie banner is off for this website, so nothing new is recorded.") }}
        </p>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="rate in rates" :key="rate.key" class="rounded-lg border border-gray-200 bg-white p-4">
                <div class="text-3xl font-semibold tabular-nums" :class="rate.class">{{ percent(stats.rates[rate.key]) }}</div>
                <div class="mt-1 text-sm font-medium text-gray-700">{{ rate.label }}</div>
                <div class="text-xs text-gray-500">{{ rate.hint }} · {{ locale.number(stats.counts[rate.key]) }}</div>
            </div>
        </div>

        <p class="text-sm text-gray-600">
            {{ ctrans("Banner shown :shown times from :from to :to, with :choices choices made.", {
                shown: locale.number(stats.shown), choices: locale.number(stats.choices), from: shortDate(filters.from), to: shortDate(filters.to),
            }) }}
        </p>

        <div class="max-w-xl rounded-lg border border-gray-200 bg-white">
            <div class="border-b border-gray-200 px-4 py-2 text-sm font-medium text-gray-700">{{ ctrans("Allowed per category") }}</div>
            <div v-for="category in categories" :key="category.key" class="flex justify-between px-4 py-2 text-sm">
                <span class="text-gray-600">{{ category.label }}</span>
                <span class="font-medium tabular-nums text-gray-900">{{ percent(stats.categories[category.key]) }}</span>
            </div>
        </div>

        <p class="text-xs text-gray-500">
            {{ ctrans("Rates are out of the times the banner was shown, once per browser session, to visitors with no saved choice. Only the first choice is counted; later changes from Cookie settings are not.") }}
            <template v-if="stats.first_date">{{ ctrans("Recorded since :date.", { date: shortDate(stats.first_date) }) }}</template>
        </p>
    </div>
</template>
