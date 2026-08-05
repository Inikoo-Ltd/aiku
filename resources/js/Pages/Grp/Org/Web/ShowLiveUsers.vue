<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 03 Aug 2026 21:05:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, computed, nextTick } from 'vue'
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { trans } from "laravel-vue-i18n"
import { useIntervalFn } from '@vueuse/core'
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useLocaleStore } from "@/Stores/locale"
import LiveVisitorsCanvas from "@/Components/Web/LiveVisitorsCanvas.vue"
import { LiveVisitor, funnelStage, liveVisitorColors, liveVisitorStatusLabels, pagePath, sumBaskets, useLiveVisitors } from "@/Composables/useLiveVisitors"
import { faDesktop, faMobile, faTabletAlt, faShoppingBasket, faFileAlt, faRobot, faSearch } from "@fal"

library.add(faDesktop, faMobile, faTabletAlt, faShoppingBasket, faFileAlt, faRobot, faSearch)

const props = defineProps<{
    website: { id: number; slug: string; domain: string }
    currency: string | null
    paused?: boolean
    visitors: any[]
    title: string
    breadcrumbs: any[]
    pageHead: PageHeadingTypes
}>()

const locale = useLocaleStore()
const { visitors: liveVisitors, syncFromServer } = useLiveVisitors(props.website.id, props.currency)

syncFromServer(props.visitors ?? [])

const tableBodyRef = ref<HTMLElement | null>(null)
const hoveredSession = ref<string | null>(null)
const pinnedSession = ref<string | null>(null)
const currentGrouping = ref('country')
const searchQuery = ref('')

const groupingOptions = [
    { value: 'country', label: trans('Country') },
    { value: 'city', label: trans('City') },
    { value: 'status', label: trans('Activity') },
    { value: 'page_title', label: trans('Page title') },
    { value: 'page_url', label: trans('Page URL') },
    { value: 'customer', label: trans('Customer') },
    { value: 'serving_agent', label: trans('Serving agent') },
    { value: 'department', label: trans('Department') },
    { value: 'browser', label: trans('Browser') },
    { value: 'search_engine', label: trans('Search engine') },
    { value: 'search_term', label: trans('Search term') },
]

const regionNames = (() => {
    try {
        return new Intl.DisplayNames([locale.locale_iso ?? 'en'], { type: 'region' })
    } catch {
        return null
    }
})()

const countryName = (code?: string) =>
    !code || code === 'XX' ? trans('Unknown') : (regionNames?.of(code) ?? code)

const statusLabel = (status: string) => trans(liveVisitorStatusLabels[status] ?? status)

const deviceIcon = (v: LiveVisitor) => {
    const device = (v.device ?? '').toLowerCase()
    if (device === 'bot') return faRobot
    if (device.includes('phone') || device.includes('mobile')) return faMobile
    if (device.includes('tablet') || device.includes('phablet')) return faTabletAlt

    return faDesktop
}

const pageIcon = (v: LiveVisitor) => (funnelStage(v) ? faShoppingBasket : faFileAlt)

const groupKeyOf = (v: LiveVisitor): string => {
    switch (currentGrouping.value) {
        case 'country': return countryName(v.country)
        case 'city': return v.city || trans('Unknown')
        case 'browser': return v.browser || trans('Unknown')
        case 'page_title': return v.page_title || v.page || trans('Home')
        case 'page_url': return v.url || '/'
        case 'customer': return v.customer_name || trans('Guest')
        case 'search_engine': return v.search_engine || trans('Direct')
        case 'search_term': return v.search_term || trans('None')
        case 'status': return statusLabel(v.status)
        case 'serving_agent': return v.agent || trans('None')
        case 'department': return v.department || trans('None')
        default: return trans('Other')
    }
}

const matchesSearch = (v: LiveVisitor): boolean => {
    const query = searchQuery.value.trim().toLowerCase()
    if (!query) {
        return true
    }

    return [v.page, v.page_title, v.url, v.city, v.customer_name, countryName(v.country), v.browser]
        .some(field => field?.toLowerCase().includes(query))
}

const visibleVisitors = computed(() =>
    Array.from(liveVisitors.value.values())
        .filter(matchesSearch)
        .sort((a, b) => b.last_active - a.last_active)
)

const totalBasket = computed(() => sumBaskets(visibleVisitors.value))

const legend = computed(() => {
    const present = new Set(visibleVisitors.value.map(v => v.status))

    return Object.entries(liveVisitorColors).filter(([name]) => present.has(name))
})

const highlighted = computed(() => pinnedSession.value ?? hoveredSession.value)

const focusRow = (sessionId: string | null) => {
    hoveredSession.value = sessionId
    if (!sessionId) {
        return
    }
    nextTick(() => {
        tableBodyRef.value
            ?.querySelector(`[data-session="${sessionId}"]`)
            ?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
    })
}

// Renews the server-side "somebody is watching" flag and reconciles anything a dropped
// websocket frame missed.
useIntervalFn(() => {
    router.reload({
        only: ['visitors'],
        onSuccess: page => syncFromServer((page.props as any).visitors ?? []),
    })
}, 90000)

const money = (v: LiveVisitor) =>
    v.basket_amount === undefined ? '' : locale.currencyFormat(v.currency_code ?? props.currency ?? '', v.basket_amount)

const placeLabel = (v: LiveVisitor) =>
    [v.city, v.region].filter(Boolean).join(', ') || countryName(v.country)

const clock = ref(Date.now())
useIntervalFn(() => (clock.value = Date.now()), 1000)

const sinceLabel = (v: LiveVisitor) => {
    const seconds = Math.max(0, Math.round(clock.value / 1000 - v.last_active))
    if (seconds < 10) {
        return trans('now')
    }

    return seconds < 60 ? `${seconds}s` : `${Math.floor(seconds / 60)}m`
}
</script>

<template>
    <Head :title="title" />

    <PageHeading :data="pageHead" />

    <div class="px-4 sm:px-6 lg:px-8 py-6 space-y-4">
        <div v-if="paused" class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ trans('Live tracking is paused: this website is currently handling an unusual number of visitors. It resumes automatically once traffic settles.') }}
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <select
                v-model="currentGrouping"
                class="rounded-md border-gray-300 py-1.5 pl-3 pr-9 text-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option v-for="opt in groupingOptions" :key="opt.value" :value="opt.value">
                    {{ trans('Group by') }} {{ opt.label }}
                </option>
            </select>

            <div class="relative">
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="trans('Search visitors')"
                    class="rounded-md border-gray-300 py-1.5 pl-3 pr-9 text-sm focus:border-indigo-500 focus:ring-indigo-500 w-64"
                >
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                    <FontAwesomeIcon :icon="faSearch" class="text-gray-400" />
                </div>
            </div>

            <div class="ml-auto flex items-center gap-5 text-sm">
                <span class="flex items-center gap-2 text-gray-500">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500" />
                    </span>
                    {{ trans('Live') }}
                </span>
                <span class="text-gray-500">
                    {{ trans('Visitors') }}
                    <span class="font-semibold text-gray-900 tabular-nums">{{ visibleVisitors.length }}</span>
                </span>
                <span class="text-gray-500">
                    {{ trans('Baskets') }}
                    <span class="font-semibold text-gray-900 tabular-nums">{{ locale.currencyFormat(currency ?? '', totalBasket) }}</span>
                </span>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-gradient-to-b from-slate-50 to-white shadow-sm overflow-hidden">
            <div class="relative h-[460px] cursor-crosshair">
                <LiveVisitorsCanvas
                    :visitors="visibleVisitors"
                    :group-key-of="groupKeyOf"
                    :highlighted="highlighted"
                    :on-expire="(sessionId: string) => liveVisitors.delete(sessionId)"
                    @hover="focusRow"
                    @select="sessionId => pinnedSession = pinnedSession === sessionId ? null : sessionId"
                />

                <div v-if="!visibleVisitors.length" class="absolute inset-0 flex items-center justify-center text-sm text-gray-400">
                    {{ trans('No live visitors right now.') }}
                </div>

                <div class="absolute bottom-3 left-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-gray-500">
                    <span v-for="[name, color] in legend" :key="name" class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: color }" />
                        <span>{{ statusLabel(name) }}</span>
                    </span>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-200">
                        <th class="w-10 py-2.5" />
                        <th class="text-left font-medium px-3 py-2.5">{{ trans('Location') }}</th>
                        <th class="text-left font-medium px-3 py-2.5">{{ trans('Customer') }}</th>
                        <th class="text-right font-medium px-3 py-2.5">{{ trans('Basket') }}</th>
                        <th class="text-left font-medium px-3 py-2.5">{{ trans('Page') }}</th>
                        <th class="text-right font-medium px-3 py-2.5 w-16">{{ trans('Seen') }}</th>
                    </tr>
                </thead>
                <tbody ref="tableBodyRef" class="divide-y divide-gray-100">
                    <tr
                        v-for="visitor in visibleVisitors"
                        :key="visitor.session_id"
                        :data-session="visitor.session_id"
                        class="transition-colors"
                        :class="[
                            highlighted === visitor.session_id ? 'bg-indigo-50' : 'hover:bg-gray-50',
                            visitor.flash_until > clock ? 'flash' : '',
                        ]"
                        @mouseenter="hoveredSession = visitor.session_id"
                        @mouseleave="hoveredSession = null"
                        @click="pinnedSession = pinnedSession === visitor.session_id ? null : visitor.session_id"
                    >
                        <td class="py-2 text-center">
                            <FontAwesomeIcon
                                :icon="deviceIcon(visitor)"
                                class="text-gray-400"
                                :title="`${visitor.browser ?? ''} ${visitor.os ?? ''}`.trim()"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <span class="flex items-center gap-2">
                                <img
                                    v-if="visitor.country && visitor.country !== 'XX'"
                                    :src="`/flags/${visitor.country.toLowerCase()}.png`"
                                    :alt="visitor.country"
                                    class="h-3 w-auto rounded-[2px] shrink-0"
                                    loading="lazy"
                                    @error="($event.target as HTMLImageElement).style.display = 'none'"
                                >
                                <span class="text-gray-700">{{ placeLabel(visitor) }}</span>
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <span :class="visitor.customer_name ? 'text-gray-900' : 'text-gray-400 italic'">
                                {{ visitor.customer_name ?? trans('Guest') }}
                            </span>
                        </td>
                        <td
                            class="px-3 py-2 text-right tabular-nums"
                            :class="visitor.basket_amount ? 'text-gray-900 font-medium' : 'text-gray-300'"
                        >
                            {{ money(visitor) || '—' }}
                        </td>
                        <td class="px-3 py-2">
                            <span class="flex items-center gap-2 text-gray-600 min-w-0">
                                <FontAwesomeIcon :icon="pageIcon(visitor)" class="text-gray-400 shrink-0" />
                                <span class="truncate" :title="visitor.url">{{ pagePath(visitor) }}</span>
                            </span>
                        </td>
                        <td class="px-3 py-2 text-right text-xs text-gray-400 tabular-nums">
                            {{ sinceLabel(visitor) }}
                        </td>
                    </tr>
                    <tr v-if="!visibleVisitors.length">
                        <td colspan="6" class="px-3 py-10 text-center text-sm text-gray-400">
                            {{ trans('Waiting for activity…') }}
                        </td>
                    </tr>
                </tbody>
            </table>
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
