<script setup lang="ts">
import { inject } from "vue"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faInfoCircle, faPallet, faCircle } from '@fas'
import { faSpinnerThird } from '@fad'
import { faAppleCrate,faRoad, faClock, faDatabase, faNetworkWired, faEye, faThLarge ,faTachometerAltFast, faMoneyBillWave, faHeart, faShoppingCart, faCameraRetro, faStream } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Icon from "../Icon.vue"

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

const renderLabelBasedOnType = (label?: string | number, type?: string, options?: { currency_code?: string }) => {
    if (type === 'number') {
        return locale.number(Number(label))
    } else if (type === 'currency') {
        if (!options?.currency_code) return label
        return locale.currencyFormat(options?.currency_code, Number(label))
    } else {
        return label || '-'
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
                    >
                        <div class="group flex items-center gap-1 tabular-nums relative text-xl px-2 mb-1 cursor-default">
                            <div class="mx-auto text-center">
                                <template v-if="tab.icon || tab.icon_data">
                                    <Icon v-if="tab.icon_data" :data="tab.icon_data" class="text-xl" />
                                    <FontAwesomeIcon v-else :icon="tab.icon" class="text-xl" fixed-width aria-hidden="true" />
                                </template>
                            </div>

                            <div class="relative text-center">
                <span class="inline opacity-80 group-hover:opacity-100 transition-opacity">
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
        <div class="mt-2 px-2 md:hidden">
            <div
                v-for="box in tabs_box"
                :key="'mobile-' + box.label"
                class="border rounded-md p-3 select-none"
            >
                <div class="text-center mb-2 text-xs font-semibold">
                    <FontAwesomeIcon v-if="box.icon" :icon="box.icon" fixed-width aria-hidden="true" />
                    {{ box.label }}
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div v-for="tab in box.tabs" :key="tab.tab_slug" class="text-center">
                        <div class="text-lg font-medium opacity-80">
                            {{ renderLabelBasedOnType(tab.value, tab.type, { currency_code: box.currency_code }) }}
                        </div>
                        <div class="text-gray-400 text-xs opacity-70">
                            {{ renderLabelBasedOnType(tab.information?.label, tab.information?.type, { currency_code: box.currency_code }) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
