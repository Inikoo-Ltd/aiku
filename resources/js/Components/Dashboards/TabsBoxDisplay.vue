<script setup lang="ts">
import { inject } from "vue"
import Icon from "../Icon.vue"
import { faSpinnerThird } from '@fad'
import { router } from '@inertiajs/vue3'
import { faInfoCircle, faPallet, faCircle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { faAppleCrate,faRoad, faClock, faDatabase, faNetworkWired, faEye, faThLarge ,faTachometerAltFast, faMoneyBillWave, faHeart, faShoppingCart, faCameraRetro, faStream } from '@fal'

library.add(
    faInfoCircle, faRoad, faClock, faDatabase, faPallet, faCircle,
    faNetworkWired, faSpinnerThird, faEye, faThLarge, faTachometerAltFast,
    faMoneyBillWave, faHeart, faShoppingCart, faCameraRetro, faStream,faAppleCrate
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
    }[]
    current?: string | number
}>()


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
        return label || '-'
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
        default:
            return route(currentRoute, currentParams);
    }
}
</script>

<template>
    <div>
        <!-- TabsBoxDisplay -->
        <div class="hidden px-6 md:flex gap-x-6 my-2">
            <div
                v-for="box in tabs_box"
                :key="box.label"
                class="rounded-md px-3 relative border w-full flex flex-col py-2 select-none"
                :style="{
                  backgroundColor: box.tabs.some(tab => tab.tab_slug === props.current) ? layoutStore.app.theme[4] + '22' : 'transparent',
                  color: box.tabs.some(tab => tab.tab_slug === props.current) ? layoutStore.app.theme[4] : 'inherit',
                  borderColor: box.tabs.some(tab => tab.tab_slug === props.current) ? layoutStore.app.theme[4] : 'inherit'
                }"
            >
                <div class="flex gap-x-4">
                    <div
                        v-for="tab in box.tabs"
                        :key="tab.tab_slug"
                        class="w-full flex flex-col items-center"
                        @click="layoutStore.currentRoute !== 'grp.org.shops.show.dashboard.show' ? null : router.get(getRoute(tab.tab_slug))"
                    >
                        <div class="group flex items-center gap-1 tabular-nums relative text-xl px-2 mb-1 cursor-default">
                            <div class="mx-auto text-center">
                                <template v-if="tab.icon || tab.icon_data">
                                    <Icon
                                        v-if="tab.icon_data"
                                        :data="tab.icon_data"
                                        class="text-xl"
                                        :class="layoutStore.currentRoute !== 'grp.org.shops.show.dashboard.show' ? 'cursor-not-allowed' : 'group-hover:cursor-pointer'"
                                    />
                                    <FontAwesomeIcon v-else :icon="tab.icon" class="text-xl" fixed-width aria-hidden="true" />
                                </template>
                            </div>

                            <div class="relative text-center">
                                <span
                                    class="inline opacity-80 group-hover:opacity-100 transition-all"
                                    :class="layoutStore.currentRoute !== 'grp.org.shops.show.dashboard.show' ? 'cursor-not-allowed' : 'group-hover:cursor-pointer group-hover:underline'"
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
                <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                    <div class="flex items-center justify-center gap-2 text-sm font-semibold text-gray-700">
                        <FontAwesomeIcon v-if="box.icon" :icon="box.icon" class="text-gray-500" fixed-width aria-hidden="true" />
                        <span>{{ box.label }}</span>
                    </div>
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
                                layoutStore.currentRoute === 'grp.org.shops.show.dashboard.show' ? 'cursor-pointer active:scale-95' : 'cursor-default'
                            ]"
                            :style="tab.tab_slug === props.current ? {
                                backgroundColor: layoutStore.app.theme[4] + '22',
                                ringColor: layoutStore.app.theme[4]
                            } : {}"
                            @click="layoutStore.currentRoute !== 'grp.org.shops.show.dashboard.show' ? null : router.get(getRoute(tab.tab_slug))"
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
            </div>
        </div>
    </div>
</template>
