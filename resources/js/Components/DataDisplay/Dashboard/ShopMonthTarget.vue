<script setup lang="ts">
import { inject, computed, ref } from "vue"
import { Line } from "vue-chartjs"
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler } from "chart.js"
import { router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPencil, faBullseyeArrow } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

library.add(faPencil, faBullseyeArrow)
ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler)

interface MonthTarget {
    month: string
    month_label: string
    last_year_label: string
    currency_code: string
    day_of_month: number
    days_in_month: number
    sales_so_far: number
    last_year_so_far: number
    last_year_total: number
    expected: number
    pipeline: { amount: number, orders: number, submitted_amount: number, in_warehouse_amount: number }
    target: { amount: number | null, is_default: boolean, growth: number, set_by: string | null }
    gap: number | null
    needed_per_day: number | null
    remaining_days: number
    chart: { days: number[], this_year: number[], last_year: number[] }
    can_edit: boolean
    update_route: { name: string, parameters: Record<string, number> }
}

const props = defineProps<{
    monthTarget: MonthTarget
}>()

const locale = inject("locale", aikuLocaleStructure)

const money = (amount: number | null) => locale.currencyFormat(props.monthTarget.currency_code, Math.round(amount ?? 0))

const percentOf = (part: number, whole: number | null) => (whole ? Math.min(100, (part / whole) * 100) : 0)

const target = computed(() => props.monthTarget.target.amount)

const invoicedWidth = computed(() => percentOf(props.monthTarget.sales_so_far, target.value))
const pipelineWidth = computed(() => Math.min(100 - invoicedWidth.value, percentOf(props.monthTarget.pipeline.amount, target.value)))
const monthElapsedPosition = computed(() => (props.monthTarget.day_of_month / props.monthTarget.days_in_month) * 100)

const versusLastYear = computed(() => {
    if (!props.monthTarget.last_year_so_far) {
        return null
    }
    return ((props.monthTarget.sales_so_far / props.monthTarget.last_year_so_far) - 1) * 100
})

const expectedVersusTarget = computed(() => (target.value ? (props.monthTarget.expected / target.value) * 100 : null))

const isEditing = ref(false)
const isSaving = ref(false)
const newTarget = ref<number | null>(null)

const startEditing = () => {
    newTarget.value = target.value ? Math.round(target.value) : null
    isEditing.value = true
}

const saveTarget = async () => {
    if (newTarget.value === null || newTarget.value < 0) {
        return
    }
    isSaving.value = true
    try {
        await axios.patch(route(props.monthTarget.update_route.name, props.monthTarget.update_route.parameters), {
            target_org_currency: newTarget.value,
            month: props.monthTarget.month,
        })
        isEditing.value = false
        router.reload({ only: ["dashboard"] })
    } catch (error: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: error?.response?.data?.message ?? ctrans("The target was not saved"),
            type: "error",
        })
    } finally {
        isSaving.value = false
    }
}

const chartData = computed(() => ({
    labels: props.monthTarget.chart.days,
    datasets: [
        {
            label: props.monthTarget.month_label,
            data: props.monthTarget.chart.this_year,
            borderColor: "#4f46e5",
            backgroundColor: "rgba(79, 70, 229, 0.08)",
            fill: true,
            tension: 0.2,
            pointRadius: 0,
            borderWidth: 2.5,
        },
        {
            label: props.monthTarget.last_year_label,
            data: props.monthTarget.chart.last_year,
            borderColor: "#9ca3af",
            borderDash: [4, 4],
            tension: 0.2,
            pointRadius: 0,
            borderWidth: 1.5,
        },
        ...(target.value ? [{
            label: ctrans("Target"),
            data: props.monthTarget.chart.days.map(() => target.value),
            borderColor: "#16a34a",
            borderDash: [8, 4],
            pointRadius: 0,
            borderWidth: 1.5,
        }] : []),
    ],
}))

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index" as const, intersect: false },
    plugins: {
        legend: { position: "bottom" as const, labels: { boxWidth: 12, font: { size: 11 } } },
        tooltip: {
            callbacks: {
                title: (items: any[]) => ctrans("Day :day", { day: items[0]?.label }),
                label: (item: any) => `${item.dataset.label}: ${money(item.raw)}`,
            },
        },
    },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 10 } },
        y: { ticks: { font: { size: 10 }, callback: (value: number) => money(value) } },
    },
}))
</script>

<template>
    <div class="mx-4 mt-4 grid gap-4 rounded-lg border bg-white p-4 shadow-sm lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="text-sm text-gray-500">
                        <FontAwesomeIcon icon="fal fa-bullseye-arrow" fixed-width aria-hidden="true" />
                        {{ ctrans(":month target", { month: monthTarget.month_label }) }}
                    </p>

                    <form v-if="isEditing" class="mt-1 flex items-center gap-2" @submit.prevent="saveTarget">
                        <input
                            v-model.number="newTarget"
                            type="number"
                            min="0"
                            step="1"
                            class="w-40 rounded border-gray-300 text-xl font-bold"
                            :aria-label="ctrans('Target')"
                            autofocus
                        />
                        <button type="submit" :disabled="isSaving" class="rounded bg-indigo-600 px-3 py-1.5 text-sm text-white disabled:opacity-50">
                            {{ ctrans("Save") }}
                        </button>
                        <button type="button" class="text-sm text-gray-500" @click="isEditing = false">{{ ctrans("Cancel") }}</button>
                    </form>

                    <p v-else class="text-3xl font-bold">
                        {{ target ? money(target) : ctrans("No target") }}
                        <button
                            v-if="monthTarget.can_edit"
                            type="button"
                            class="ml-1 align-middle text-base text-gray-400 hover:text-gray-700"
                            :aria-label="ctrans('Edit target')"
                            @click="startEditing"
                        >
                            <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                        </button>
                    </p>

                    <p class="text-xs text-gray-500">
                        <template v-if="!monthTarget.target.is_default">
                            {{ ctrans("Set by :name", { name: monthTarget.target.set_by ?? ctrans("management") }) }}
                        </template>
                        <template v-else-if="target">
                            {{ ctrans(":last_year sales plus :growth% (not set by management)", { last_year: monthTarget.last_year_label, growth: String(Math.round(monthTarget.target.growth * 100)) }) }}
                        </template>
                    </p>
                </div>

                <div class="text-right">
                    <p class="text-sm text-gray-500">{{ ctrans("Expected by month end") }}</p>
                    <p class="text-3xl font-bold" :class="expectedVersusTarget !== null && expectedVersusTarget < 100 ? 'text-red-600' : 'text-green-600'">
                        {{ money(monthTarget.expected) }}
                    </p>
                    <p v-if="expectedVersusTarget !== null" class="text-xs text-gray-500">
                        {{ ctrans(":percent% of target", { percent: String(Math.round(expectedVersusTarget)) }) }}
                    </p>
                </div>
            </div>

            <div v-if="target">
                <div class="relative h-5 overflow-hidden rounded-full bg-gray-100">
                    <div class="absolute inset-y-0 left-0 bg-green-500" :style="{ width: invoicedWidth + '%' }" />
                    <div
                        class="absolute inset-y-0 bg-green-200 bg-[repeating-linear-gradient(45deg,transparent,transparent_4px,rgba(255,255,255,.6)_4px,rgba(255,255,255,.6)_8px)]"
                        :style="{ left: invoicedWidth + '%', width: pipelineWidth + '%' }"
                    />
                    <div
                        class="absolute inset-y-0 w-0.5 bg-gray-700"
                        :style="{ left: monthElapsedPosition + '%' }"
                        v-tooltip="ctrans('Day :day of :days', { day: String(monthTarget.day_of_month), days: String(monthTarget.days_in_month) })"
                    />
                </div>
                <div class="mt-1 flex justify-between text-xs text-gray-500">
                    <span>{{ ctrans(":percent% invoiced", { percent: String(Math.round(invoicedWidth)) }) }}</span>
                    <span>{{ ctrans("+:percent% in the warehouse pipeline", { percent: String(Math.round(pipelineWidth)) }) }}</span>
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded bg-gray-50 p-3">
                    <dt class="text-xs text-gray-500">{{ ctrans("Invoiced so far") }}</dt>
                    <dd class="text-lg font-semibold">{{ money(monthTarget.sales_so_far) }}</dd>
                    <dd v-if="versusLastYear !== null" class="text-xs" :class="versusLastYear < 0 ? 'text-red-600' : 'text-green-600'">
                        {{ ctrans(":change% vs same days last year", { change: (versusLastYear > 0 ? "+" : "") + versusLastYear.toFixed(1) }) }}
                    </dd>
                </div>
                <div class="rounded bg-gray-50 p-3">
                    <dt class="text-xs text-gray-500">{{ ctrans("In the pipeline") }}</dt>
                    <dd class="text-lg font-semibold">{{ money(monthTarget.pipeline.amount) }}</dd>
                    <dd class="text-xs text-gray-500">
                        {{ ctrans(":orders orders: :submitted submitted, :warehouse in warehouse", {
                            orders: String(monthTarget.pipeline.orders),
                            submitted: money(monthTarget.pipeline.submitted_amount),
                            warehouse: money(monthTarget.pipeline.in_warehouse_amount),
                        }) }}
                    </dd>
                </div>
                <div class="rounded bg-gray-50 p-3">
                    <dt class="text-xs text-gray-500">{{ ctrans("Still needed") }}</dt>
                    <dd class="text-lg font-semibold" :class="monthTarget.gap ? 'text-red-600' : 'text-green-600'">
                        {{ monthTarget.gap === null ? "-" : monthTarget.gap ? money(monthTarget.gap) : ctrans("Covered") }}
                    </dd>
                    <dd class="text-xs text-gray-500">{{ ctrans("after the pipeline invoices") }}</dd>
                </div>
                <div class="rounded bg-gray-50 p-3">
                    <dt class="text-xs text-gray-500">{{ ctrans("Needed per day") }}</dt>
                    <dd class="text-lg font-semibold">{{ monthTarget.needed_per_day === null ? "-" : money(monthTarget.needed_per_day) }}</dd>
                    <dd class="text-xs text-gray-500">{{ ctrans(":days days left", { days: String(monthTarget.remaining_days) }) }}</dd>
                </div>
            </dl>
        </div>

        <div class="h-64 lg:h-auto lg:min-h-64">
            <Line :data="chartData" :options="chartOptions" />
        </div>
    </div>
</template>
