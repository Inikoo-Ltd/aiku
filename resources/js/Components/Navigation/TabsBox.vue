<!--
    TODO: Icon loading is unlimited if change tabs is failed
-->
<script setup lang="ts">
import { inject, ref, watch } from "vue"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faInfoCircle, faPallet, faCircle } from '@fas'
import { faSpinnerThird } from '@fad'
import { faRoad, faClock, faDatabase, faNetworkWired, faEye, faThLarge ,faTachometerAltFast, faMoneyBillWave, faHeart, faShoppingCart, faCameraRetro, faStream} from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { routeType } from "@/types/route"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Select from 'primevue/select'
import IftaLabel from 'primevue/iftalabel'
import { trans } from "laravel-vue-i18n";

library.add(faInfoCircle, faRoad, faClock, faDatabase, faPallet, faCircle, faNetworkWired, faSpinnerThird, faEye, faThLarge,faTachometerAltFast, faMoneyBillWave, faHeart, faShoppingCart, faCameraRetro, faStream)

const layoutStore = inject('layout', layoutStructure)
const locale = inject('locale', aikuLocaleStructure)

const props = defineProps<{
    tabs_box: {
        label: string
        currency_code?: string
        tabs: {
            label: string
            icon?: string
            indicator?: boolean
            tab_slug: string
            type?: string // 'icon', 'date', 'number', 'currency'
            align?: string
            route?: routeType
            iconClass?: string
            information?: {
                label: string | number
                type?: string // 'icon', 'date', 'number', 'currency'
            }
        }[]
    }[]
    current: string | Number
}>()

const mergeTabs = () => {
    return props.tabs_box.reduce((acc, current) => {
        return acc.concat(current.tabs);
    }, []);
};

const emits = defineEmits<{
    (e: 'update:tab', value: string): void
}>()

const currentTab = ref(props.current)
const tabLoading = ref<boolean | string>(false)

// Method: click Tab
const onChangeTab = async (tabSlug: string) => {
    if(tabSlug === currentTab.value) return  // To avoid click on the current tab occurs loading
    tabLoading.value = tabSlug
    emits('update:tab', tabSlug)
}

// Set new active Tab after parent has changed page
watch(() => props.current, (newVal) => {
    currentTab.value = newVal
    tabLoading.value = false
})


const renderLabelBasedOnType = (data?: {label: string | number, type?: string}, options?: { currency_code?: string}) => {
    if(data?.type === 'number') {
        return locale.number(Number(data?.label))
    } else if (data?.type === 'currency') {
        if (!options?.currency_code) {
            return data?.label
        } else {
            return locale.currencyFormat(options?.currency_code, Number(data?.label))
        }
    } else {
        return data?.label || '-'
    }
    
}
</script>

<template>
    <div>
        <!-- Desktop -->
        <div class="hidden px-6 md:flex gap-x-6 my-2 border-b border-gray-300">
            <div v-for="box in tabs_box" class="px-3 relative border border-gray-300 w-full flex flex-col  py-2 transition-all z-10"
                :class="box.tabs.some(tab => tab.tab_slug === currentTab) ? 'mt-3 rounded-t-xl border-b-0 -mb-0.5 bg-white' : 'bg-gray-500/10 shadow-xl mb-2 rounded-md '"
            >
                <div class="text-center text-gray-500 mb-2 text-xs">
                    <FontAwesomeIcon v-if="box.icon" :icon='box.icon' class='' fixed-width aria-hidden='true' />
                    {{ box.label }}
                </div>
                <div class="flex gap-x-4">
                    <div v-for="tab in box.tabs" class="w-full flex flex-col items-center">
                        <div
                            @click="onChangeTab(tab.tab_slug)"
                            class="tabular-nums relative cursor-pointer text-2xl px-2"
                            :class="tab.tab_slug === currentTab ? 'text-indigo-600' : ''"
                        >
                            <template v-if="box.icon">
                                <LoadingIcon v-if="tabLoading == tab.tab_slug" class="animate-spin text-xl" />
                                <FontAwesomeIcon v-else :icon='box.icon' class='text-xl' fixed-width aria-hidden='true' />
                            </template>
                            
                            <div class="relative ">
                                <span class="inline" :class="tabLoading == tab.tab_slug ? 'opacity-0' : ''">
                                    {{ renderLabelBasedOnType(tab, {currency_code: box.currency_code}) }}
                                </span>
                                <div v-if="!box.icon && tabLoading == tab.tab_slug" class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
                                    <LoadingIcon />
                                </div>
                            </div>
                            <template v-if="tab.indicator">
                                <FontAwesomeIcon icon='fas fa-circle' class='absolute top-1 -right-0 text-green-500 text-[6px]' fixed-width aria-hidden='true' />
                                <FontAwesomeIcon icon='fas fa-circle' class='absolute top-1 -right-0 text-green-500 text-[6px] animate-ping' fixed-width aria-hidden='true' />
                            </template>
                        </div>
                        
                        <div class="text-gray-400 font-normal">
                            {{ renderLabelBasedOnType(tab.information, {currency_code: box.currency_code}) }}
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Mobile -->
        <div class="mt-2 px-2 md:hidden">
            <IftaLabel>
                <Select
                    :modelValue="current"
                    :options="mergeTabs()"
                    optionValue="tab_slug"
                    optionLabel="label"
                    checkmark
                    :loading="!!tabLoading"
                    class="w-full"
                    @change="(ee) => onChangeTab(ee.value)"
                >
                    <template #loadingicon>
                        <LoadingIcon />
                    </template>
                </Select>
                <label for="dd-city">{{ trans("Tabs") }}</label>
            </IftaLabel>
        </div>
        

        <div class="mt-8"></div>
        
    </div>
</template>
