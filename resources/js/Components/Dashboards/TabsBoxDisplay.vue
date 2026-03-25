<script setup lang="ts">
import { inject, ref } from "vue"
import Icon from "../Icon.vue"
import { faSpinnerThird } from '@fad'
import { router } from '@inertiajs/vue3'
import { faInfoCircle, faPallet, faCircle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { faAppleCrate,faRoad, faClock, faDatabase, faNetworkWired, faEye, faThLarge ,faTachometerAltFast, faMoneyBillWave, faHeart, faShoppingCart, faCameraRetro, faStream, faBoxOpen, faChevronDown } from '@fal'

library.add(
    faInfoCircle, faRoad, faClock, faDatabase, faPallet, faCircle,
    faNetworkWired, faSpinnerThird, faEye, faThLarge, faTachometerAltFast,
    faMoneyBillWave, faHeart, faShoppingCart, faCameraRetro, faStream, faAppleCrate,
    faBoxOpen, faChevronDown
)

const layoutStore = inject('layout', layoutStructure)
const locale = inject('locale', aikuLocaleStructure)

const props = defineProps<{
    tabs_box: {
        label: string
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
            iconClass?: string
            information?: {
                label: string | number
                type?: string // 'icon', 'date', 'number', 'currency'
            }
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
            }[]
        }[]
    }[]
    current?: string | number
}>()

const expandedBoxes = ref<Record<string, boolean>>({})

const toggleExpand = (boxLabel: string) => {
    expandedBoxes.value[boxLabel] = !expandedBoxes.value[boxLabel]
}

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
</script>

<template>
    <div>
        <!-- TabsBoxDisplay -->
        <div class="hidden px-6 md:flex gap-x-6 my-2 items-start">
            <div
                v-for="box in tabs_box"
                :key="box.label"
                class="rounded-md px-3 relative border w-full flex flex-col py-2 select-none transition-all duration-200"
                :style="{
                  backgroundColor: box.tabs.some(tab => tab.tab_slug === props.current) ? layoutStore.app.theme[4] + '22' : 'transparent',
                  color: box.tabs.some(tab => tab.tab_slug === props.current) ? layoutStore.app.theme[4] : 'inherit',
                  borderColor: box.tabs.some(tab => tab.tab_slug === props.current) ? layoutStore.app.theme[4] : 'inherit'
                }"
            >
                <div class="text-center mb-2 text-xs">
                    <FontAwesomeIcon v-if="box.icon" :icon="box.icon" class="" fixed-width aria-hidden="true" />
                    {{ box.label }}
                </div>

                <div class="flex gap-x-4">
                    <div
                        v-for="tab in box.tabs"
                        :key="tab.tab_slug"
                        class="w-full flex flex-col items-center"
                        @click="['grp.org.shops.show.dashboard.show', 'grp.org.dashboard.show', 'grp.dashboard.show'].includes(layoutStore.currentRoute) ? router.get(getRoute(tab.tab_slug)) : null"
                    >
                        <div class="group flex items-center gap-1 tabular-nums relative text-xl px-2 mb-1 cursor-default">
                            <div class="mx-auto text-center">
                                <template v-if="tab.icon || tab.icon_data">
                                    <Icon
                                        v-if="tab.icon_data"
                                        :data="tab.icon_data"
                                        class="text-xl"
                                        :class="!['grp.org.shops.show.dashboard.show', 'grp.org.dashboard.show', 'grp.dashboard.show'].includes(layoutStore.currentRoute) ? 'cursor-not-allowed' : 'group-hover:cursor-pointer'"
                                    />
                                    <FontAwesomeIcon v-else :icon="tab.icon" class="text-xl" fixed-width aria-hidden="true" />
                                </template>
                            </div>

                            <div class="relative text-center">
                                <span
                                    class="inline opacity-80 group-hover:opacity-100 transition-all"
                                    :class="!['grp.org.shops.show.dashboard.show', 'grp.org.dashboard.show', 'grp.dashboard.show'].includes(layoutStore.currentRoute) ? 'cursor-not-allowed' : 'group-hover:cursor-pointer group-hover:underline'"
                                >
                                  {{ renderLabelBasedOnType(tab.value, tab.type, { currency_code: box.currency_code }) }}
                                </span>
                            </div>

                            <template v-if="tab.indicator">
                                <FontAwesomeIcon icon="fas fa-circle" class="absolute top-1 -right-0 text-green-500 text-[6px]" fixed-width aria-hidden="true" />
                                <FontAwesomeIcon icon="fas fa-circle" class="absolute top-1 -right-0 text-green-500 text-[6px] animate-ping" fixed-width aria-hidden="true" />
                            </template>
                        </div>

                        <div class="text-gray-400 font-normal text-xs opacity-70">
                            {{ renderLabelBasedOnType(tab.information?.label, tab.information?.type, { currency_code: box.currency_code }) }}
                        </div>
                    </div>
                </div>

                <!-- Expand Button -->
                <button
                    v-if="box.children && box.children.length > 0"
                    class="mt-1 flex items-center justify-center w-full text-gray-400 hover:text-gray-600 transition-colors"
                    @click.stop="toggleExpand(box.label)"
                >
                    <FontAwesomeIcon
                        icon="fal fa-chevron-down"
                        class="text-[10px] transition-transform duration-200"
                        :class="expandedBoxes[box.label] ? 'rotate-180' : ''"
                        fixed-width
                        aria-hidden="true"
                    />
                </button>

                <!-- Children Rows -->
                <div
                    v-if="box.children && box.children.length > 0 && expandedBoxes[box.label]"
                    class="mt-2 border-t pt-2"
                    :style="{ borderColor: box.tabs.some(tab => tab.tab_slug === props.current) ? layoutStore.app.theme[4] + '44' : '#e5e7eb' }"
                >
                    <!-- Table Header -->
                    <div class="flex items-center gap-x-2 text-[10px] text-gray-400 mb-1 pb-1 border-b border-gray-100">
                        <span class="flex-1 min-w-0"></span>
                        <div class="flex gap-x-3 shrink-0">
                            <div
                                v-for="tab in box.tabs"
                                :key="'col-header-' + tab.tab_slug"
                                class="w-20 text-right"
                            >
                                <FontAwesomeIcon v-if="tab.icon" :icon="tab.icon" fixed-width aria-hidden="true" />
                            </div>
                        </div>
                    </div>
                    <!-- Table Rows -->
                    <div
                        v-for="child in box.children"
                        :key="child.slug"
                        class="flex items-center gap-x-2 text-xs py-1 border-b border-gray-50 last:border-0"
                    >
                        <span class="truncate text-gray-600 font-medium flex-1 min-w-0">{{ child.label }}</span>
                        <div class="flex gap-x-3 shrink-0">
                            <div
                                v-for="childTab in child.tabs"
                                :key="childTab.tab_slug"
                                class="w-20 text-right"
                            >
                                <div class="tabular-nums font-semibold text-gray-800">
                                    {{ renderLabelBasedOnType(childTab.value, childTab.type, { currency_code: child.currency_code }) }}
                                </div>
                                <div class="text-gray-400 text-[10px]">
                                    {{ renderLabelBasedOnType(childTab.information?.label, childTab.information?.type, { currency_code: child.currency_code }) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile -->
        <div class="mt-2 px-3 md:hidden space-y-3">
            <div
                v-for="box in tabs_box"
                :key="'mobile-' + box.label"
                class="rounded-lg border shadow-sm overflow-hidden select-none"
                :style="{
                  backgroundColor: box.tabs.some(tab => tab.tab_slug === props.current) ? layoutStore.app.theme[4] + '11' : 'transparent',
                  borderColor: box.tabs.some(tab => tab.tab_slug === props.current) ? layoutStore.app.theme[4] + '44' : 'inherit'
                }"
            >
                <!-- Box Header -->
                <div
                    class="bg-gray-50 px-4 py-2 border-b border-gray-200 flex items-center"
                    :class="box.children && box.children.length > 0 ? 'cursor-pointer' : ''"
                    @click="box.children && box.children.length > 0 ? toggleExpand('mobile-' + box.label) : null"
                >
                    <div class="flex items-center justify-center gap-2 text-sm font-semibold text-gray-700 flex-1">
                        <FontAwesomeIcon v-if="box.icon" :icon="box.icon" class="text-gray-500" fixed-width aria-hidden="true" />
                        <span>{{ box.label }}</span>
                    </div>
                    <FontAwesomeIcon
                        v-if="box.children && box.children.length > 0"
                        icon="fal fa-chevron-down"
                        class="text-gray-400 text-xs transition-transform duration-200"
                        :class="expandedBoxes['mobile-' + box.label] ? 'rotate-180' : ''"
                        fixed-width
                        aria-hidden="true"
                    />
                </div>

                <!-- Tabs Grid -->
                <div class="p-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div
                            v-for="tab in box.tabs"
                            :key="tab.tab_slug"
                            class="rounded-md p-3 transition-all duration-200"
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
                            <!-- Tab Icon (if exists) -->
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

                            <!-- Tab Value -->
                            <div class="text-center relative">
                                <div
                                    class="text-xl font-semibold tabular-nums mb-1"
                                    :style="tab.tab_slug === props.current ? { color: layoutStore.app.theme[4] } : {}"
                                >
                                    {{ renderLabelBasedOnType(tab.value, tab.type, { currency_code: box.currency_code }) }}
                                </div>

                                <!-- Indicator -->
                                <template v-if="tab.indicator">
                                    <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 right-0 text-green-500 text-[6px]" fixed-width aria-hidden="true" />
                                    <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 right-0 text-green-500 text-[6px] animate-ping" fixed-width aria-hidden="true" />
                                </template>
                            </div>

                            <!-- Tab Information Label -->
                            <div class="text-center text-xs text-gray-500 leading-tight">
                                {{ renderLabelBasedOnType(tab.information?.label, tab.information?.type, { currency_code: box.currency_code }) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile Children -->
                <div
                    v-if="box.children && box.children.length > 0 && expandedBoxes['mobile-' + box.label]"
                    class="border-t border-gray-200"
                >
                    <!-- Mobile Table Header -->
                    <div class="flex items-center gap-x-2 px-4 py-1.5 bg-gray-50 text-[10px] text-gray-400 border-b border-gray-200">
                        <span class="flex-1 min-w-0"></span>
                        <div class="flex gap-x-4 shrink-0">
                            <div
                                v-for="tab in box.tabs"
                                :key="'mobile-col-header-' + tab.tab_slug"
                                class="w-16 text-right"
                            >
                                <FontAwesomeIcon v-if="tab.icon" :icon="tab.icon" fixed-width aria-hidden="true" />
                            </div>
                        </div>
                    </div>
                    <!-- Mobile Table Rows -->
                    <div
                        v-for="child in box.children"
                        :key="'mobile-child-' + child.slug"
                        class="px-4 py-2 flex items-center justify-between text-xs border-b border-gray-100 last:border-0"
                    >
                        <span class="text-gray-600 font-medium truncate mr-2 flex-1 min-w-0">{{ child.label }}</span>
                        <div class="flex gap-x-4 shrink-0">
                            <div
                                v-for="childTab in child.tabs"
                                :key="childTab.tab_slug"
                                class="w-16 text-right"
                            >
                                <div class="tabular-nums font-semibold text-gray-800">
                                    {{ renderLabelBasedOnType(childTab.value, childTab.type, { currency_code: child.currency_code }) }}
                                </div>
                                <div class="text-gray-400 text-[10px]">
                                    {{ renderLabelBasedOnType(childTab.information?.label, childTab.information?.type, { currency_code: child.currency_code }) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
