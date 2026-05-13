<script setup lang="ts">
import { inject, ref, computed } from "vue"
import { trans } from "laravel-vue-i18n"
import Icon from "../Icon.vue"
import { faSpinnerThird } from '@fad'
import { router, Link } from '@inertiajs/vue3'
import { faInfoCircle, faPallet, faCircle, faTimesCircle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { faAppleCrate, faRoad, faClock, faDatabase, faNetworkWired, faEye, faThLarge, faTachometerAltFast, faMoneyBillWave, faHeart, faShoppingCart, faCameraRetro, faStream, faBoxOpen, faChevronDown, faInventory, faSkullCow, faBan } from '@fal'

library.add(
    faInfoCircle, faRoad, faClock, faDatabase, faPallet, faCircle, faTimesCircle,
    faNetworkWired, faSpinnerThird, faEye, faThLarge, faTachometerAltFast,
    faMoneyBillWave, faHeart, faShoppingCart, faCameraRetro, faStream, faAppleCrate,
    faBoxOpen, faChevronDown, faInventory, faSkullCow, faBan
)

const layoutStore = inject('layout', layoutStructure)
const locale = inject('locale', aikuLocaleStructure)

const props = defineProps<{
    tabs_box: {
        label: string
        show_total?: boolean
        currency_code?: string
        icon?: string | string[]
        tabs: {
            label: string
            value: string | number
            indicator?: boolean
            tab_slug: string
            type?: string // 'icon', 'date', 'number', 'currency'
            align?: string
            icon?: string | string[]
            icon_data?: Record<string, any>
            iconClass?: string
            tooltip?: string
            information?: {
                label: string | number
                type?: string // 'icon', 'date', 'number', 'currency'
            }
            visitRoute?: {
                name: string
                parameters: {}
            }
            warning?: {
                route_target?: { name: string; parameters?: Record<string, any> }
                tooltip?: string
                value?: number | string
                indicator?: boolean
            } | null
        }[]
        children?: {
            label: string
            slug: string
            currency_code?: string
            tabs: {
                tab_slug: string
                value: string | number
                type?: string
                information?: {
                    label: string | number
                    type?: string
                }
                route?: {
                    name: string
                    parameters: Record<string, any>
                }
            }[]
        }[]
    }[]
    current?: string | number
}>()

const isAllExpanded = ref(false)

const toggleExpanded = () => {
    isAllExpanded.value = !isAllExpanded.value
}

const hasChildren = computed(() =>
    props.tabs_box.some(box => (box.children ?? []).length > 0)
)

const tableColumns = computed(() =>
    props.tabs_box.flatMap((box, boxIdx) =>
        box.tabs.map((tab, tabIdx) => ({
            label: tab.label,
            icon: tab.icon ?? box.icon,
            icon_data: tab.icon_data,
            iconClass: tab.iconClass,
            tabSlug: tab.tab_slug,
            type: tab.type,
            currencyCode: box.currency_code,
            isSectionStart: tabIdx === 0 && boxIdx > 0,
        }))
    )
)

const tableRows = computed(() => {
    const uniqueChildren = new Map<string, { label: string; slug: string }>()

    props.tabs_box.forEach(box => {
        ;(box.children ?? []).forEach(child => {
            if (!uniqueChildren.has(child.slug)) {
                uniqueChildren.set(child.slug, { label: child.label, slug: child.slug })
            }
        })
    })

    if (uniqueChildren.size === 0) return []

    return [...uniqueChildren.values()].map(child => ({
        label: child.label,
        slug: child.slug,
        cells: props.tabs_box.flatMap(box => {
            const matchingChild = (box.children ?? []).find(c => c.slug === child.slug)
            return box.tabs.map(tab => {
                const childTab = matchingChild?.tabs.find(t => t.tab_slug === tab.tab_slug)
                return {
                    value: childTab?.value,
                    information: childTab?.information,
                    type: childTab?.type ?? tab.type,
                    currencyCode: matchingChild?.currency_code ?? box.currency_code,
                    route: childTab?.route,
                }
            })
        }),
    }))
})

const currencyFormat = (currencyCode: string, amount: number | string): string | number => {
    if (!amount) return 0
    if (!currencyCode) {
        return amount || 0
    }

    const num = typeof amount === "string" ? parseFloat(amount) : amount

    const formatter = new Intl.NumberFormat(locale?.language?.code, {
        style: (currencyCode) ? "currency" : "decimal",
        currency: currencyCode || '',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    })

    return formatter.format(num);
}

const renderLabelBasedOnType = (label?: string | number, type?: string, options?: { currency_code?: string }) => {
    if (type === 'number') {
        return locale.number(Number(label))
    } else if (type === 'currency') {
        if (!options?.currency_code) return label
        return currencyFormat(options?.currency_code, Number(label))
    } else {
        return label || '0'
    }
}

const boxTotal = (box: { tabs: { value?: string | number }[] }) =>
    box.tabs.reduce((sum, t) => sum + Number(t.value || 0), 0)

const childrenLabel = computed(() => {
    if (layoutStore.currentRoute === 'grp.dashboard.show') return trans('Organisation')
    if (layoutStore.currentRoute === 'grp.org.dashboard.show') return trans('Shop')
    return trans('Name')
})

const getRoute = (tabSlug) => {
    const currentRoute = layoutStore.currentRoute;
    const currentParams = layoutStore.currentParams;

    switch (currentRoute) {
        case 'grp.org.shops.show.dashboard.show':
            return route('grp.org.shops.show.ordering.backlog', {
                ...currentParams,
                tab: tabSlug
            });
        case 'grp.org.dashboard.show':
            return route('grp.org.overview.ordering.backlog', {
                ...currentParams,
                tab: tabSlug
            });
        case 'grp.dashboard.show':
            return route('grp.overview.ordering.backlog', {
                ...currentParams,
                tab: tabSlug
            });
        default:
            return route(currentRoute, currentParams);
    }
}

const clickVisitRoute = (visitRoute: {
    name: string
    parameters: {}
}) => {

    if (!visitRoute) return;

    router.get(route(visitRoute.name, visitRoute.parameters))

}
</script>

<template>
    <div>
        <!-- TabsBoxDisplay Desktop -->
        <div class="hidden px-6 md:flex md:flex-nowrap gap-x-6 mt-4 mb-1 items-stretch md:overflow-x-auto md:pb-1">
            <div
                v-for="(box, idx) in tabs_box"
                :key="box.label"
                class="rounded-md px-3 relative border w-full md:w-auto md:min-w-[15rem] md:flex-none xl:flex-auto xl:basis-auto xl:min-w-fit flex flex-col py-2 select-none transition-all duration-200"
                :style="{
                  backgroundColor: box.tabs.some(tab => tab.tab_slug === props.current) ? layoutStore.app.theme[4] + '22' : 'transparent',
                  color: box.tabs.some(tab => tab.tab_slug === props.current) ? layoutStore.app.theme[4] : 'inherit',
                  borderColor: box.tabs.some(tab => tab.tab_slug === props.current) ? layoutStore.app.theme[4] : 'inherit'
                }"
            >
                <div class="text-center mb-2 text-xs font-semibold">
                    <FontAwesomeIcon v-if="box.icon" :icon="box.icon" class="" fixed-width aria-hidden="true" />
                    {{ box.label }}<template v-if="box.show_total"> ({{ locale.number(boxTotal(box)) }})</template>
                </div>

                <div class="flex gap-x-4 justify-center">
                    <div
                        v-for="tab in box.tabs"
                        :key="tab.tab_slug"
                        class="w-fit flex flex-col items-center mx-auto"
                        :class="!(['grp.org.shops.show.dashboard.show', 'grp.org.dashboard.show', 'grp.dashboard.show'].includes(layoutStore.currentRoute) || tab.visitRoute) ? 'cursor-default' : 'hover:cursor-pointer'"
                        @click="['grp.org.shops.show.dashboard.show', 'grp.org.dashboard.show', 'grp.dashboard.show'].includes(layoutStore.currentRoute) ? router.get(getRoute(tab.tab_slug)) : clickVisitRoute(tab.visitRoute)"
                        v-tooltip="tab.tooltip"
                    >
                        <div class="group flex items-center gap-1 tabular-nums relative text-xl px-2 mb-1">
                            <div class="mx-auto text-center">
                                <template v-if="tab.icon || tab.icon_data">
                                    <Icon
                                        v-if="tab.icon_data"
                                        :data="tab.icon_data"
                                        class="text-xl"
                                    />
                                    <FontAwesomeIcon v-else :icon="tab.icon" class="text-xl" fixed-width aria-hidden="true" />
                                </template>
                            </div>

                            <div class="flex items-center gap-1">
                                <span
                                    class="inline opacity-80 group-hover:opacity-100 transition-all"
                                    :class="!(['grp.org.shops.show.dashboard.show', 'grp.org.dashboard.show', 'grp.dashboard.show'].includes(layoutStore.currentRoute) || tab.visitRoute) ? '' : 'group-hover:underline'"
                                >
                                  {{ renderLabelBasedOnType(tab.value, tab.type, { currency_code: box.currency_code }) }}
                                </span>

                                <!-- Section: Warning -->
                                <Link v-if="tab.warning"
                                    :href="tab.warning?.route_target?.name ? route(tab.warning.route_target.name, tab.warning.route_target.parameters ?? {}) : '#'"
                                    class="relative aspect-square w-5 flex items-center justify-center bg-purple-300 text-purple-700 rounded text-[10px] leading-none opacity-70 hover:opacity-100 no-underline"
                                    v-tooltip="tab.warning?.tooltip"
                                    @click.stop
                                >
                                    {{ tab.warning.value }}
                                    <FontAwesomeIcon v-if="tab.warning?.indicator" icon="fas fa-circle" class="absolute top-0 -right-0.5 text-purple-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                                    <FontAwesomeIcon v-if="tab.warning?.indicator" icon="fas fa-circle" class="absolute top-0 -right-0.5 text-purple-500 text-[5px]" fixed-width aria-hidden="true" />
                                </Link>
                            </div>

                            <template v-if="tab.indicator">
                                <FontAwesomeIcon icon="fas fa-circle" class="absolute top-1 -right-0 text-green-500 text-[6px]" fixed-width aria-hidden="true" />
                                <FontAwesomeIcon icon="fas fa-circle" class="absolute top-1 -right-0 text-green-500 text-[6px] animate-ping" fixed-width aria-hidden="true" />
                            </template>
                        </div>

                        <div v-if="tab.information" class="text-gray-400 font-normal text-xs opacity-70">
                            {{ renderLabelBasedOnType(tab.information?.label, tab.information?.type, { currency_code: box.currency_code }) }}
                        </div>
                    </div>
                </div>

                <div class="flex-1"></div>
            </div>
        </div>

        <!-- Global Expand Button (Desktop) -->
        <div v-if="hasChildren" class="hidden md:flex justify-center">
            <button
                class="flex items-center gap-x-1.5 text-xs text-gray-400 hover:text-gray-600 transition-colors px-3 py-1 rounded hover:bg-gray-100"
                @click="toggleExpanded()"
            >
                <FontAwesomeIcon
                    icon="fal fa-chevron-down"
                    class="text-[10px] transition-transform duration-200"
                    :class="isAllExpanded ? 'rotate-180' : ''"
                    fixed-width
                    aria-hidden="true"
                />
            </button>
        </div>

        <!-- Children Table (Desktop, below expand button) -->
        <div v-if="isAllExpanded && hasChildren" class="hidden md:block px-6 mt-1 mb-4">
            <div class="bg-white rounded-lg shadow ring-1 ring-gray-200 overflow-hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">{{ childrenLabel }}</th>
                            <th
                                v-for="col in tableColumns"
                                :key="col.tabSlug"
                                class="px-4 py-2 text-right text-xs font-semibold text-gray-500"
                                :class="col.isSectionStart ? 'border-l border-gray-200' : ''"
                            >
                                <Icon v-if="col.icon_data" :data="col.icon_data" :class="col.iconClass" :title="col.label" />
                                <FontAwesomeIcon v-else-if="col.icon" :icon="col.icon" :class="col.iconClass" fixed-width aria-hidden="true" v-tooltip="col.label" />
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in tableRows" :key="row.slug" class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-2.5 font-medium text-gray-800">{{ row.label }}</td>
                            <td
                                v-for="(cell, i) in row.cells"
                                :key="i"
                                class="px-4 py-2.5 text-right tabular-nums text-gray-700"
                                :class="[
                                    tableColumns[i]?.isSectionStart ? 'border-l border-gray-200' : '',
                                    cell.route ? 'cursor-pointer hover:text-blue-600 hover:underline' : ''
                                ]"
                                @click="cell.route ? router.get(route(cell.route.name, cell.route.parameters)) : null"
                            >
                                {{ renderLabelBasedOnType(cell.value, cell.type, { currency_code: cell.currencyCode }) }}
                                <div v-if="cell.information?.label" class="text-[10px] text-gray-400">
                                    {{ renderLabelBasedOnType(cell.information.label, cell.information.type, { currency_code: cell.currencyCode }) }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile -->
        <div class="mt-2 px-3 md:hidden flex gap-3 overflow-x-auto pb-1">
            <div
                v-for="box in tabs_box"
                :key="'mobile-' + box.label"
                class="rounded-lg border shadow-sm overflow-hidden select-none flex-none min-w-[16rem]"
                :style="{
                  backgroundColor: box.tabs.some(tab => tab.tab_slug === props.current) ? layoutStore.app.theme[4] + '11' : 'transparent',
                  borderColor: box.tabs.some(tab => tab.tab_slug === props.current) ? layoutStore.app.theme[4] + '44' : 'inherit'
                }"
            >
                <!-- Box Header -->
                <div class="bg-gray-50 px-4 py-2 border-b border-gray-200 flex items-center">
                    <div class="flex items-center justify-center gap-2 text-sm font-semibold text-gray-700 flex-1">
                        <FontAwesomeIcon v-if="box.icon" :icon="box.icon" class="text-gray-500" fixed-width aria-hidden="true" />
                        <span>{{ box.label }}<template v-if="box.show_total"> ({{ locale.number(boxTotal(box)) }})</template></span>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="p-3">
                    <div class="flex flex-nowrap gap-3 overflow-x-auto pb-1">
                        <div
                            v-for="tab in box.tabs"
                            :key="tab.tab_slug"
                            class="rounded-md p-3 transition-all duration-200 flex-none min-w-[11rem]"
                            :class="[
                                tab.tab_slug === props.current
                                    ? 'ring-2 shadow-sm'
                                    : 'bg-gray-50 hover:bg-gray-100',
                                ['grp.org.shops.show.dashboard.show', 'grp.org.dashboard.show', 'grp.dashboard.show'].includes(layoutStore.currentRoute) ? 'cursor-pointer active:scale-95' : 'cursor-default'
                            ]"
                            :style="tab.tab_slug === props.current ? {
                                backgroundColor: layoutStore.app.theme[4] + '22',
                                ringColor: layoutStore.app.theme[4]
                            } : {}"
                            @click="['grp.org.shops.show.dashboard.show', 'grp.org.dashboard.show', 'grp.dashboard.show'].includes(layoutStore.currentRoute) ? router.get(getRoute(tab.tab_slug)) : null"
                        >
                            <div v-if="tab.icon || tab.icon_data" class="flex justify-center mb-2">
                                <Icon
                                    v-if="tab.icon_data"
                                    :data="tab.icon_data"
                                    class="text-lg"
                                    :style="tab.tab_slug === props.current ? { color: layoutStore.app.theme[4] } : {}"
                                />
                                <FontAwesomeIcon
                                    v-else
                                    :icon="tab.icon"
                                    class="text-lg"
                                    :style="tab.tab_slug === props.current ? { color: layoutStore.app.theme[4] } : {}"
                                    fixed-width
                                    aria-hidden="true"
                                />
                            </div>

                            <div class="text-center relative">
                                <div class="flex items-center justify-center gap-1">
                                    <div
                                        class="text-xl font-semibold tabular-nums mb-1 whitespace-nowrap"
                                        :style="tab.tab_slug === props.current ? { color: layoutStore.app.theme[4] } : {}"
                                    >
                                        {{ renderLabelBasedOnType(tab.value, tab.type, { currency_code: box.currency_code }) }}
                                    </div>

                                    <!-- Section: Warning (Mobile) -->
                                    <Link v-if="tab.warning"
                                        :href="tab.warning?.route_target?.name ? route(tab.warning.route_target.name, tab.warning.route_target.parameters ?? {}) : '#'"
                                        class="relative bg-purple-300 text-purple-700 rounded px-1.5 text-xs opacity-70 hover:opacity-100 mb-1"
                                        v-tooltip="tab.warning?.tooltip"
                                        @click.stop
                                    >
                                        {{ tab.warning.value }}
                                        <FontAwesomeIcon v-if="tab.warning?.indicator" icon="fas fa-circle" class="absolute top-0 -right-0.5 text-purple-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                                        <FontAwesomeIcon v-if="tab.warning?.indicator" icon="fas fa-circle" class="absolute top-0 -right-0.5 text-purple-500 text-[5px]" fixed-width aria-hidden="true" />
                                    </Link>
                                </div>

                                <template v-if="tab.indicator">
                                    <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 right-0 text-green-500 text-[6px]" fixed-width aria-hidden="true" />
                                    <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 right-0 text-green-500 text-[6px] animate-ping" fixed-width aria-hidden="true" />
                                </template>
                            </div>

                            <div class="text-center text-xs text-gray-500 leading-tight whitespace-nowrap">
                                {{ renderLabelBasedOnType(tab.information?.label, tab.information?.type, { currency_code: box.currency_code }) }}
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Global Expand Button (Mobile) -->
        <div v-if="hasChildren" class="md:hidden flex justify-center mt-2 mb-1 px-3">
            <button
                class="w-full flex items-center justify-center gap-x-1.5 text-xs text-gray-400 hover:text-gray-600 transition-colors py-1.5 rounded border border-gray-200 hover:bg-gray-50"
                @click="toggleExpanded()"
            >
                <FontAwesomeIcon
                    icon="fal fa-chevron-down"
                    class="text-[10px] transition-transform duration-200"
                    :class="isAllExpanded ? 'rotate-180' : ''"
                    fixed-width
                    aria-hidden="true"
                />
            </button>
        </div>

        <!-- Children Table (Mobile, below expand button) -->
        <div v-if="isAllExpanded && hasChildren" class="md:hidden px-3 mb-2">
            <div class="bg-white rounded-lg shadow ring-1 ring-gray-200 overflow-hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">{{ childrenLabel }}</th>
                            <th
                                v-for="col in tableColumns"
                                :key="col.tabSlug"
                                class="px-4 py-2 text-right text-xs font-semibold text-gray-500"
                                :class="col.isSectionStart ? 'border-l border-gray-200' : ''"
                            >
                                <Icon v-if="col.icon_data" :data="col.icon_data" :class="col.iconClass" :title="col.label" />
                                <FontAwesomeIcon v-else-if="col.icon" :icon="col.icon" :class="col.iconClass" fixed-width aria-hidden="true" v-tooltip="col.label" />
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in tableRows" :key="row.slug" class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-2.5 font-medium text-gray-800 whitespace-nowrap">{{ row.label }}</td>
                            <td
                                v-for="(cell, i) in row.cells"
                                :key="i"
                                class="px-4 py-2.5 text-right tabular-nums text-gray-700 whitespace-nowrap"
                                :class="[
                                    tableColumns[i]?.isSectionStart ? 'border-l border-gray-200' : '',
                                    cell.route ? 'cursor-pointer hover:text-blue-600 hover:underline' : ''
                                ]"
                                @click="cell.route ? router.get(route(cell.route.name, cell.route.parameters)) : null"
                            >
                                {{ renderLabelBasedOnType(cell.value, cell.type, { currency_code: cell.currencyCode }) }}
                                <div v-if="cell.information?.label" class="text-[10px] text-gray-400">
                                    {{ renderLabelBasedOnType(cell.information.label, cell.information.type, { currency_code: cell.currencyCode }) }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
