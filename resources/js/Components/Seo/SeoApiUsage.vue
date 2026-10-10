<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Link, router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Chart from "primevue/chart"
import InputNumber from "primevue/inputnumber"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type FeatureRow = { provider: string, feature: string, requests: number, errors: number, cost: number }

type Usage = {
    month: string
    is_current: boolean
    spend: number
    budget: number
    left: number
    projected: number
    share: number | null
    warning: number
    requests: number
    errors: number
    features: FeatureRow[]
    daily: { day: string, cost: number }[]
    history: { month: string, cost: number }[]
    provider_balance: number | null
    latest_errors: { id: number, created_at: string, provider: string, feature: string, endpoint: string, website: string | null, error: string | null }[]
}

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    data: { usage: Usage, can_edit: boolean, budget_route: routeType }
}>()

const usage = computed(() => props.data.usage)
const canEdit = computed(() => props.data.can_edit)

const locale = useLocaleStore()

const usd = (value: number, digits = 2) => `$${value.toFixed(digits)}`

const providerLabels: Record<string, string> = {
    dataforseo: "DataForSEO",
    openrouter: ctrans("AI gateway (OpenRouter)"),
    openai: ctrans("AI gateway (OpenAI)"),
    google_search_console: ctrans("Google Search Console (free)"),
    google_ads_keyword_planner: ctrans("Google Ads Keyword Planner (free, no longer used)"),
}

const monthLabel = (month: string) => useFormatTime(month, { formatTime: "MMMM yyyy" })

const monthParam = (month: string, offset: number) => {
    const date = new Date(`${month}T00:00:00`)
    date.setMonth(date.getMonth() + offset)

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`
}

const usageHref = (month: string) => `${window.location.pathname}?month=${month}`

const share = computed(() => usage.value.share ?? 0)

const state = computed(() => {
    if (usage.value.spend >= usage.value.budget) {
        return { bar: "bg-red-600", text: "text-red-700", message: ctrans("The budget is spent: no paid SEO API call is made until next month or until the budget is raised. Reading results already paid for still works.") }
    }

    if (share.value >= usage.value.warning) {
        return { bar: "bg-amber-500", text: "text-amber-700", message: ctrans(":share% of the budget is spent.", { share: share.value }) }
    }

    if (usage.value.is_current && usage.value.projected > usage.value.budget) {
        return { bar: "bg-amber-500", text: "text-amber-700", message: ctrans("At this month's pace the spend reaches :projected, over the budget.", { projected: usd(usage.value.projected) }) }
    }

    return { bar: "bg-green-600", text: "text-gray-600", message: null }
})

const budget = ref<number>(usage.value.budget)
const isSaving = ref(false)
const budgetError = ref<string | null>(null)

watch(() => usage.value.budget, (value) => budget.value = value)

const saveBudget = () => {
    router.patch(route(props.data.budget_route.name, props.data.budget_route.parameters), { budget: budget.value }, {
        preserveScroll: true,
        onStart: () => {
            isSaving.value = true
            budgetError.value = null
        },
        onError: (errors) => budgetError.value = Object.values(errors)[0] ?? null,
        onFinish: () => isSaving.value = false,
    })
}

const accentColor = () => getComputedStyle(document.documentElement).getPropertyValue("--app-accent").trim() || "#4f46e5"

const chartData = computed(() => ({
    labels: usage.value.daily.map((day) => useFormatTime(day.day, { formatTime: "d" })),
    datasets: [
        {
            label: ctrans("Spend"),
            data: usage.value.daily.map((day) => day.cost),
            backgroundColor: accentColor(),
            borderRadius: 2,
        },
    ],
}))

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: (context: { raw: number }) => usd(context.raw, 4) } } },
    scales: {
        x: { grid: { display: false }, ticks: { color: "#6b7280", maxRotation: 0 } },
        y: { border: { display: false }, grid: { color: "#f3f4f6" }, ticks: { color: "#6b7280", callback: (value: number) => `$${value}` } },
    },
}
</script>

<template>
    <div class="pb-4">
        <div class="flex items-center gap-3 px-4 pt-4 text-sm">
            <Link :href="usageHref(monthParam(usage.month, -1))" class="text-[--app-accent] underline-offset-2 hover:underline focus-visible:underline">{{ ctrans("Previous month") }}</Link>
            <span class="font-medium text-gray-900">{{ monthLabel(usage.month) }}</span>
            <Link v-if="!usage.is_current" :href="usageHref(monthParam(usage.month, 1))" class="text-[--app-accent] underline-offset-2 hover:underline focus-visible:underline">{{ ctrans("Next month") }}</Link>
        </div>

        <section :aria-label="ctrans('Spend against the budget')" class="mx-4 mt-3 rounded-xl bg-white ring-1 ring-gray-200">
            <div class="flex flex-wrap items-end gap-x-10 gap-y-4 px-5 py-4">
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("Spent") }}</div>
                    <div class="mt-0.5 flex items-baseline gap-x-2">
                        <span class="text-3xl font-semibold tabular-nums tracking-tight text-gray-900">{{ usd(usage.spend) }}</span>
                        <span class="text-sm tabular-nums text-gray-500">{{ ctrans("of :budget", { budget: usd(usage.budget) }) }}</span>
                    </div>
                </div>
                <dl class="flex flex-wrap gap-x-8 gap-y-3">
                    <div>
                        <dt class="text-xs text-gray-500">{{ ctrans("Remaining") }}</dt>
                        <dd class="mt-0.5 text-xl font-medium tabular-nums text-gray-900">{{ usd(usage.left) }}</dd>
                    </div>
                    <div v-if="usage.is_current">
                        <dt class="text-xs text-gray-500" v-tooltip="ctrans('The spend so far, spread over the whole month')">{{ ctrans("Month end at this pace") }}</dt>
                        <dd class="mt-0.5 text-xl font-medium tabular-nums text-gray-900">{{ usd(usage.projected) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ ctrans("Requests") }}</dt>
                        <dd class="mt-0.5 text-xl font-medium tabular-nums text-gray-900">{{ locale.number(usage.requests) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ ctrans("Failed") }}</dt>
                        <dd class="mt-0.5 text-xl font-medium tabular-nums" :class="usage.errors ? 'text-red-700' : 'text-gray-900'">{{ locale.number(usage.errors) }}</dd>
                    </div>
                    <div v-if="usage.provider_balance !== null">
                        <dt class="text-xs text-gray-500" v-tooltip="ctrans('Money left on the DataForSEO account, read from DataForSEO every 10 minutes. When it runs out every paid call fails, whatever the budget says')">{{ ctrans("DataForSEO balance") }}</dt>
                        <dd class="mt-0.5 text-xl font-medium tabular-nums" :class="usage.provider_balance <= 0 ? 'text-red-700' : 'text-gray-900'">{{ usd(usage.provider_balance) }}</dd>
                    </div>
                </dl>

                <form v-if="canEdit" class="ml-auto flex items-end gap-2" @submit.prevent="saveBudget">
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="font-medium text-gray-700">{{ ctrans("Monthly budget, all SEO APIs") }}</span>
                        <InputNumber v-model="budget" mode="currency" currency="USD" locale="en-US" :min="0" :max="10000" inputClass="h-10 w-32" />
                    </label>
                    <Button class="h-10 justify-center" type="primary" :label="ctrans('Save')" :loading="isSaving" :disabled="budget === usage.budget" @click="saveBudget" />
                </form>
            </div>

            <div class="px-5 pb-4">
                <div class="h-2 overflow-hidden rounded-full bg-gray-100" role="progressbar" :aria-valuenow="share" aria-valuemin="0" aria-valuemax="100" :aria-label="ctrans('Share of the budget spent')">
                    <div class="h-full rounded-full" :class="state.bar" :style="{ width: `${Math.min(100, share)}%` }" />
                </div>
                <p v-if="state.message" role="status" class="mt-2 text-sm" :class="state.text">{{ state.message }}</p>
                <p v-if="budgetError" role="alert" class="mt-2 text-sm text-red-700">{{ budgetError }}</p>
                <p class="mt-2 text-xs text-gray-500">
                    {{ ctrans("One budget for DataForSEO and the AI gateway calls of the SEO tools together. Once it is spent, the tools stop making paid calls until the next month.") }}
                </p>
            </div>

            <div class="border-t border-gray-100 px-5 py-4">
                <h3 class="text-xs font-medium text-gray-700">{{ ctrans("Spend per day") }}</h3>
                <div class="mt-2 h-40" role="img" :aria-label="ctrans('Spend per day in :month', { month: monthLabel(usage.month) })">
                    <Chart type="bar" :data="chartData" :options="chartOptions" class="h-full" />
                </div>
            </div>
        </section>

        <div class="mx-4 mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <section :aria-labelledby="'usage-features'" class="rounded-xl bg-white ring-1 ring-gray-200">
                <h2 id="usage-features" class="border-b border-gray-100 px-5 py-3 text-sm font-medium text-gray-900">{{ ctrans("Per feature") }}</h2>
                <p v-if="!usage.features.length" class="px-5 py-4 text-sm text-gray-600">{{ ctrans("No request this month.") }}</p>
                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs text-gray-500">
                            <th scope="col" class="px-5 py-2 font-medium">{{ ctrans("Feature") }}</th>
                            <th scope="col" class="px-3 py-2 font-medium">{{ ctrans("Provider") }}</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">{{ ctrans("Requests") }}</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">{{ ctrans("Failed") }}</th>
                            <th scope="col" class="px-5 py-2 text-right font-medium">{{ ctrans("Spend") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in usage.features" :key="`${row.provider}-${row.feature}`">
                            <td class="px-5 py-2 text-gray-900">{{ row.feature }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ providerLabels[row.provider] ?? row.provider }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ locale.number(row.requests) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums" :class="row.errors ? 'text-red-700' : 'text-gray-400'">{{ locale.number(row.errors) }}</td>
                            <td class="px-5 py-2 text-right tabular-nums text-gray-900">{{ usd(row.cost, row.cost < 1 ? 4 : 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section :aria-labelledby="'usage-history'" class="rounded-xl bg-white ring-1 ring-gray-200">
                <h2 id="usage-history" class="border-b border-gray-100 px-5 py-3 text-sm font-medium text-gray-900">{{ ctrans("Previous months") }}</h2>
                <ul class="divide-y divide-gray-100">
                    <li v-for="previous in usage.history" :key="previous.month" class="flex items-center justify-between px-5 py-2 text-sm">
                        <Link :href="usageHref(previous.month.slice(0, 7))" class="text-gray-900 underline-offset-2 hover:underline focus-visible:underline">{{ monthLabel(previous.month) }}</Link>
                        <span class="tabular-nums text-gray-700">{{ usd(previous.cost) }}</span>
                    </li>
                </ul>
            </section>
        </div>

        <section :aria-labelledby="'usage-errors'" class="mx-4 my-4 rounded-xl bg-white ring-1 ring-gray-200">
            <div class="border-b border-gray-100 px-5 py-3">
                <h2 id="usage-errors" class="text-sm font-medium text-gray-900">{{ ctrans("Latest failed requests") }}</h2>
                <p class="text-xs text-gray-500">{{ ctrans("An expired key, an empty balance or a paused account shows up here first.") }}</p>
            </div>
            <p v-if="!usage.latest_errors.length" class="px-5 py-4 text-sm text-gray-600">{{ ctrans("No failed request.") }}</p>
            <ul v-else class="divide-y divide-gray-100">
                <li v-for="failure in usage.latest_errors" :key="failure.id" class="px-5 py-2.5 text-sm">
                    <div class="flex flex-wrap items-baseline gap-x-3 text-xs text-gray-500">
                        <span class="tabular-nums">{{ useFormatTime(failure.created_at, { formatTime: "d MMM yyyy HH:mm" }) }}</span>
                        <span>{{ failure.feature }}</span>
                        <span>{{ providerLabels[failure.provider] ?? failure.provider }}</span>
                        <span v-if="failure.website">{{ failure.website }}</span>
                        <span class="font-mono">{{ failure.endpoint }}</span>
                    </div>
                    <p class="mt-0.5 break-words text-red-700">{{ failure.error || ctrans("No message") }}</p>
                </li>
            </ul>
        </section>
    </div>
</template>
