<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faHourglassHalf, faBoxOpen, faHeadset, faCheckCircle } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime, useRangeFromNow } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"

type Signal = "stuck" | "waiting_stock" | "waiting_cs"

type Stage = {
    tab: string
    total: number
    stuck: number
    waiting_stock: number
    waiting_cs: number
    oldest_since: string | null
}

const props = defineProps<{
    attention: {
        stuck_after_working_days: number
        stages: Stage[]
        oldest: {
            reference: string
            slug: string
            tab: string
            customer_name: string | null
            shop_slug: string
            organisation_slug: string
            stage_since: string
        }[]
        waiting_items_route?: routeType | null
    }
    labels: Record<string, string>
}>()

const cards = computed(() => [
    {
        key: "stuck" as Signal,
        icon: faHourglassHalf,
        title: ctrans("Stuck"),
        hint: ctrans("In the same stage for more than :days working days", { days: props.attention.stuck_after_working_days }),
        tone: "text-red-600",
        chip: "border-red-200 bg-red-50 text-red-700 hover:border-red-400",
    },
    {
        key: "waiting_stock" as Signal,
        icon: faBoxOpen,
        title: ctrans("Waiting for stock"),
        hint: ctrans("The picker could not find a line and is waiting for the warehouse. Customer service has not been told."),
        tone: "text-amber-600",
        chip: "border-amber-200 bg-amber-50 text-amber-700 hover:border-amber-400",
    },
    {
        key: "waiting_cs" as Signal,
        icon: faHeadset,
        title: ctrans("With customer service"),
        hint: ctrans("The picker sent a line to customer service to decide: don't pick, send back or replace."),
        tone: "text-amber-600",
        chip: "border-amber-200 bg-amber-50 text-amber-700 hover:border-amber-400",
    },
].map(card => ({
    ...card,
    total: props.attention.stages.reduce((sum, stage) => sum + stage[card.key], 0),
    stages: props.attention.stages.filter(stage => stage[card.key] > 0),
})))

const nothingToDo = computed(() => cards.value.every(card => card.total === 0))

const hrefFor = (tab: string, signal: Signal) => {
    const url = new URL(window.location.href)
    url.search = ""
    url.searchParams.set("tab", tab)
    url.searchParams.set(`${tab}_elements[attention]`, signal)

    return url.pathname + url.search
}

const orderHref = (order: { organisation_slug: string, shop_slug: string, slug: string }) =>
    route("grp.org.shops.show.ordering.orders.show", [order.organisation_slug, order.shop_slug, order.slug])
</script>

<template>
    <div class="mx-3 mt-3 md:mx-6">
        <div v-if="nothingToDo" class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-700">
            <FontAwesomeIcon :icon="faCheckCircle" fixed-width aria-hidden="true" />
            {{ ctrans("Nothing needs attention: no order has sat in a stage for more than :days working days and no line is waiting.", { days: attention.stuck_after_working_days }) }}
        </div>

        <div v-else class="grid gap-3 lg:grid-cols-4">
            <div v-for="card in cards" :key="card.key" class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-baseline justify-between gap-2">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500" v-tooltip="card.hint">
                        <FontAwesomeIcon :icon="card.icon" fixed-width aria-hidden="true" />
                        {{ card.title }}
                    </div>
                    <div class="text-2xl font-semibold tabular-nums" :class="card.total ? card.tone : 'text-gray-300'">{{ card.total }}</div>
                </div>
                <div class="mt-1 text-xs text-gray-500">{{ card.hint }}</div>
                <div v-if="card.stages.length" class="mt-2 flex flex-wrap gap-1.5">
                    <Link
                        v-for="stage in card.stages"
                        :key="stage.tab"
                        :href="hrefFor(stage.tab, card.key)"
                        class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs transition"
                        :class="card.chip"
                        v-tooltip="card.key === 'stuck' && stage.oldest_since ? ctrans('Oldest: :age', { age: useRangeFromNow(stage.oldest_since) }) : undefined">
                        <span>{{ labels[stage.tab] ?? stage.tab }}</span>
                        <span class="font-semibold tabular-nums">{{ stage[card.key] }}</span>
                    </Link>
                </div>
                <Link
                    v-if="card.key === 'waiting_cs' && card.total && attention.waiting_items_route"
                    :href="route(attention.waiting_items_route.name, attention.waiting_items_route.parameters)"
                    class="mt-2 inline-block text-xs font-medium hover:underline">
                    {{ ctrans("Handle the waiting lines") }}
                </Link>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Waiting longest") }}</div>
                <div v-if="!attention.oldest.length" class="mt-2 text-xs text-gray-400">{{ ctrans("No stuck orders") }}</div>
                <ul v-else class="mt-2 space-y-1 text-xs">
                    <li v-for="order in attention.oldest" :key="order.slug" class="flex items-center justify-between gap-2">
                        <span class="min-w-0 truncate">
                            <Link :href="orderHref(order)" class="font-medium hover:underline">{{ order.reference }}</Link>
                            <span class="ml-1 text-gray-500">{{ order.customer_name }}</span>
                        </span>
                        <span class="whitespace-nowrap text-gray-500" v-tooltip="useFormatTime(order.stage_since, { formatTime: 'aiku' })">
                            {{ labels[order.tab] ?? order.tab }} ·
                            <span class="font-semibold text-red-600">{{ useRangeFromNow(order.stage_since) }}</span>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
