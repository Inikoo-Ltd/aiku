<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faQuestionCircle } from "@fal"
import { faShoppingBasket, faCheckCircle } from "@fas"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { ActionGaugeTS } from "@/types/Components/ActionGauge"

library.add(faQuestionCircle, faShoppingBasket, faCheckCircle)

defineProps<{
    gauges: ActionGaugeTS[]
}>()

const locale = useLocaleStore()

const ringRadius = 26
const ringCircumference = 2 * Math.PI * ringRadius

const ringTooltip = (gauge: ActionGaugeTS) => {
    if (!Number.isFinite(gauge.value) || !Number.isFinite(gauge.total) || !gauge.total) {
        return null
    }
    return ctrans(":value / :total current SKOs", { value: locale.number(gauge.value), total: locale.number(gauge.total) })
}

const ringOffset = (percentage: number) => {
    const visiblePercentage = percentage > 0 ? Math.max(percentage, 3) : 0
    return ringCircumference * (1 - Math.min(visiblePercentage, 100) / 100)
}
</script>

<template>
    <div class="px-4 mt-4">
        <div class="flex items-center gap-x-2 text-sm font-semibold text-gray-700">
            {{ ctrans("Action needed") }}
            <span class="text-xs font-normal text-gray-400">{{ ctrans("What to work on in the warehouse today") }}</span>
        </div>
        <div class="mt-2 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
            <div v-for="gauge in gauges" :key="gauge.key" class="relative">
                <Link
                    :href="route(gauge.route.name, gauge.route.parameters)"
                    class="group flex flex-col items-center gap-y-2 rounded-lg bg-white px-3 pt-4 pb-3 ring-1 ring-gray-200 shadow-sm hover:bg-gray-50 hover:shadow transition"
                >
                    <div v-tooltip="ringTooltip(gauge)" class="relative w-20 h-20">
                        <svg viewBox="0 0 64 64" class="w-full h-full -rotate-90">
                            <circle cx="32" cy="32" :r="ringRadius" fill="none" stroke-width="6" stroke="#e5e7eb" />
                            <circle
                                cx="32"
                                cy="32"
                                :r="ringRadius"
                                fill="none"
                                stroke-width="6"
                                stroke-linecap="round"
                                :stroke="gauge.color"
                                :stroke-dasharray="ringCircumference"
                                :stroke-dashoffset="ringOffset(gauge.percentage)"
                                class="transition-[stroke-dashoffset] duration-700"
                            />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center leading-none">
                            <FontAwesomeIcon v-if="!gauge.value" icon="fas fa-check-circle" class="text-xl text-green-500" fixed-width aria-hidden="true" />
                            <template v-else>
                                <span class="text-lg font-bold tabular-nums" :style="{ color: gauge.color }">{{ locale.number(gauge.value) }}</span>
                                <span class="mt-0.5 text-[10px] font-medium tabular-nums text-gray-500">{{ gauge.percentage }}%</span>
                            </template>
                        </div>
                    </div>
                    <div class="flex items-center gap-x-1 text-sm font-medium text-gray-600 group-hover:text-gray-900">
                        {{ gauge.label }}
                        <FontAwesomeIcon v-tooltip="gauge.hint" icon="fal fa-question-circle" class="cursor-help text-gray-300 hover:text-gray-500" fixed-width aria-hidden="true" />
                    </div>
                </Link>
                <Link
                    v-if="gauge.secondary"
                    v-tooltip="gauge.secondary.tooltip"
                    :href="route(gauge.secondary.route.name, gauge.secondary.route.parameters)"
                    class="absolute top-2 right-2 flex items-center gap-x-1 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-xs tabular-nums text-gray-500 hover:text-gray-800 hover:border-gray-300"
                >
                    <FontAwesomeIcon :icon="gauge.secondary.icon" fixed-width aria-hidden="true" />
                    {{ locale.number(gauge.secondary.value) }}
                </Link>
            </div>
        </div>
    </div>
</template>
