<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 03 Aug 2026 21:05:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { useIntervalFn } from "@vueuse/core"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowRight, faHome, faCube, faFolder, faFolderTree, faFolderDownload, faLayerGroup, faNewspaper, faColumns, faCreditCard, faShoppingBasket, faPlaneArrival, faFileAlt } from "@fal"
import { useLocaleStore } from "@/Stores/locale"
import LiveVisitorsCanvas from "@/Components/Web/LiveVisitorsCanvas.vue"
import { LiveVisitor, dedupeByCustomer, funnelStage, isHomepage, liveVisitorColors, liveVisitorStatusLabels, pagePath, shortDuration } from "@/Composables/useLiveVisitors"

library.add(faArrowRight, faHome, faCube, faFolder, faFolderTree, faFolderDownload, faLayerGroup, faNewspaper, faColumns, faCreditCard, faShoppingBasket, faPlaneArrival, faFileAlt)

// The channel subscription is owned by the parent so a page never opens it twice; this component
// only renders the shared visitor map.
const props = defineProps<{
    visitors: Map<string, LiveVisitor>
    counts: { logged_in: number; logged_out: number }
    currency: string | null
    liveUsersUrl: string | null
}>()

const locale = useLocaleStore()
const hoveredSession = ref<string | null>(null)

const regionNames = (() => {
    try {
        return new Intl.DisplayNames([locale.locale_iso ?? "en"], { type: "region" })
    } catch {
        return null
    }
})()

const countryName = (code?: string) =>
    !code || code === "XX" ? trans("Unknown") : (regionNames?.of(code) ?? code)

const allVisitors = computed(() =>
    Array.from(props.visitors.values()).sort((a, b) => b.last_active - a.last_active)
)

// Without an organising force the bubbles just collide into a column, so the mini view clusters
// them by status. It is also the grouping that needs no explanation at a glance.
const statusLabel = (status: string) => trans(liveVisitorStatusLabels[status] ?? status)

const groupKeyOf = (v: LiveVisitor) => statusLabel(v.status)

// Only legend the statuses actually present, so entries that cannot occur here never show up
const legend = computed(() => {
    const present = new Set(allVisitors.value.map(v => v.status))

    return Object.entries(liveVisitorColors).filter(([name]) => present.has(name))
})

// The mini table is the "who is signed in right now" view; guests stay in the bubbles only.
// Whoever is actively building a basket goes to the top — that is the reason to look at all.
const identifiedVisitors = computed(() =>
    dedupeByCustomer(allVisitors.value.filter(v => v.logged_in || v.customer_name))
        .sort((a, b) =>
            Number(b.status === "ordering") - Number(a.status === "ordering")
            || (b.basket_amount ?? 0) - (a.basket_amount ?? 0)
            || b.last_active - a.last_active)
        .slice(0, 8)
)

// The last two steps before an order get their own panes; everyone else shares the main one.
const inBasket = computed(() => allVisitors.value.filter(v => funnelStage(v) === "basket"))
const inCheckout = computed(() => allVisitors.value.filter(v => funnelStage(v) === "checkout"))
const browsing = computed(() => allVisitors.value.filter(v => funnelStage(v) === null))

const pageIcons: Record<string, any> = {
    storefront: faHome,
    product: faCube,
    products: faCube,
    family: faFolder,
    department: faFolderTree,
    sub_department: faFolderDownload,
    catalogue: faFolderTree,
    collection: faLayerGroup,
    blog: faNewspaper,
    content: faColumns,
    landing_page: faPlaneArrival,
    basket: faShoppingBasket,
    checkout: faCreditCard,
}

const pageIcon = (v: LiveVisitor) => {
    const stage = funnelStage(v)
    if (stage) {
        return pageIcons[stage]
    }

    return isHomepage(v) ? faHome : (pageIcons[v.page_type ?? ""] ?? faFileAlt)
}

const pageLabel = (v: LiveVisitor) => {
    const stage = funnelStage(v)
    if (stage) {
        return stage === "basket" ? trans("Basket") : trans("Checkout")
    }

    return isHomepage(v) ? trans("Homepage") : pagePath(v)
}

const timeOnPage = (v: LiveVisitor) => shortDuration(v.page_since, clock.value)

const money = (v: LiveVisitor) =>
    v.basket_amount ? locale.currencyFormat(v.currency_code ?? props.currency ?? "", v.basket_amount) : ""

const clock = ref(Date.now())
useIntervalFn(() => (clock.value = Date.now()), 1000)
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3 border-b border-gray-100">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="relative flex h-2 w-2 shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500" />
                </span>
                <h3 class="text-base font-semibold text-gray-900">{{ trans("Live visitors") }}</h3>
                <span class="text-sm text-gray-400 truncate">
                    {{ counts.logged_in }} {{ trans("signed in") }} · {{ counts.logged_out }} {{ trans("guests") }}
                </span>
            </div>

            <Link
                v-if="liveUsersUrl"
                :href="liveUsersUrl"
                class="primaryLink text-sm flex items-center gap-1.5 shrink-0"
            >
                {{ trans("See all") }}
                <FontAwesomeIcon :icon="faArrowRight" class="text-xs" />
            </Link>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2">
            <div class="flex flex-col border-b lg:border-b-0 lg:border-r border-gray-100">
                <!-- The two steps before an order, side by side above the browsing crowd -->
                <div class="grid grid-cols-2 border-b border-gray-100">
                    <div
                        v-for="pane in [
                            { key: 'basket', label: trans('In basket'), visitors: inBasket, accent: '#f59e0b' },
                            { key: 'checkout', label: trans('At checkout'), visitors: inCheckout, accent: '#10b981' },
                        ]"
                        :key="pane.key"
                        class="relative h-20 border-r border-gray-100 last:border-r-0"
                        :style="{ background: `linear-gradient(to bottom, ${pane.accent}0f, transparent)` }"
                    >
                        <div class="absolute top-1.5 left-3 z-10 flex items-baseline gap-1.5 pointer-events-none">
                            <span class="text-[10px] font-semibold uppercase tracking-wider" :style="{ color: pane.accent }">
                                {{ pane.label }}
                            </span>
                            <span class="text-sm font-bold text-gray-900 tabular-nums">{{ pane.visitors.length }}</span>
                        </div>

                        <LiveVisitorsCanvas
                            :visitors="pane.visitors"
                            :highlighted="hoveredSession"
                            :radius="8"
                            :show-labels="false"
                            :on-expire="(sessionId: string) => props.visitors.delete(sessionId)"
                            @hover="sessionId => hoveredSession = sessionId"
                        />
                    </div>
                </div>

                <div class="relative h-56 sm:h-72 bg-gradient-to-b from-slate-50 to-white">
                    <LiveVisitorsCanvas
                        :visitors="browsing"
                        :group-key-of="groupKeyOf"
                        :highlighted="hoveredSession"
                        :radius="10"
                        :show-labels="false"
                        :on-expire="(sessionId: string) => props.visitors.delete(sessionId)"
                        @hover="sessionId => hoveredSession = sessionId"
                    />

                    <div v-if="!browsing.length" class="absolute inset-0 flex items-center justify-center text-sm text-gray-400">
                        {{ trans("Nobody browsing right now.") }}
                    </div>
                </div>

                <!-- Kept out of the canvas so drifting bubbles never sit on top of it -->
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2 border-t border-gray-100 text-[10px] text-gray-400">
                    <span v-for="[name, color] in legend" :key="name" class="flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: color }" />
                        <span>{{ statusLabel(name) }}</span>
                    </span>
                </div>
            </div>

            <div class="flex flex-col min-w-0">
                <ul class="divide-y divide-gray-100 flex-1">
                    <li
                        v-for="visitor in identifiedVisitors"
                        :key="visitor.session_id"
                        class="px-4 py-2 grid grid-cols-[auto_minmax(0,1.1fr)_minmax(0,1fr)_auto_auto] items-center gap-x-3 text-sm text-gray-500 transition-colors"
                        :class="[
                            hoveredSession === visitor.session_id ? 'bg-indigo-50' : 'hover:bg-gray-50',
                            visitor.flash_until > clock ? 'flash' : '',
                        ]"
                        @mouseenter="hoveredSession = visitor.session_id"
                        @mouseleave="hoveredSession = null"
                    >
                        <img
                            v-if="visitor.country && visitor.country !== 'XX'"
                            :src="`/flags/${visitor.country.toLowerCase()}.png`"
                            :alt="visitor.country"
                            class="h-3 w-auto rounded-[2px]"
                            loading="lazy"
                            @error="($event.target as HTMLImageElement).style.display = 'none'"
                        >
                        <span v-else class="w-4" />

                        <span class="truncate" :title="visitor.customer_name">{{ visitor.customer_name ?? trans("Guest") }}</span>

                        <span class="flex items-center gap-1.5 min-w-0" :title="visitor.url">
                            <FontAwesomeIcon :icon="pageIcon(visitor)" class="text-gray-300 shrink-0" fixed-width />
                            <span class="truncate">{{ pageLabel(visitor) }}</span>
                        </span>

                        <span class="tabular-nums text-gray-400 text-right w-10">{{ timeOnPage(visitor) }}</span>

                        <span class="tabular-nums text-right">{{ money(visitor) }}</span>
                    </li>

                    <li v-if="!identifiedVisitors.length" class="px-4 py-8 text-center text-sm text-gray-400">
                        {{ trans("No signed-in visitors right now.") }}
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<style scoped>
.flash {
    animation: flash 1.2s ease-out;
}

@keyframes flash {
    0% { background-color: rgb(254 249 195); }
    100% { background-color: transparent; }
}
</style>
