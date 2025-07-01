<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 18 Mar 2023 04:04:35 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->


<!--
    TODO: Icon loading is unlimited if change tabs is failed
-->
<script setup lang="ts">
import { inject, ref, watch } from "vue"
import { capitalize } from "@/Composables/capitalize"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faInfoCircle, faPallet, faCircle } from '@fas'
import { faSpinnerThird } from '@fad'
import { faRoad, faClock, faDatabase, faNetworkWired, faEye, faThLarge ,faTachometerAltFast, faMoneyBillWave, faHeart, faShoppingCart, faCameraRetro, faStream, faTachometerAlt, faTransporter } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { layoutStructure } from "@/Composables/useLayoutStructure"
import type { Navigation } from '@/types/Tabs'

library.add(faInfoCircle, faRoad, faClock, faDatabase, faPallet, faCircle, faNetworkWired, faSpinnerThird, faEye, faThLarge,faTachometerAltFast, faMoneyBillWave, faHeart, faShoppingCart, faCameraRetro, faStream, faTachometerAlt, faTransporter)

const layoutStore = inject('layout', layoutStructure)

const props = defineProps<{
    navigation: Navigation
    current: string | Number
}>()

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

const tabIconClass = function (isCurrent: boolean, type: string | undefined, align: string | undefined, extraIconClass: string) {
    // console.log(isCurrent, type, align, extraIconClass)
    let iconClass = '-ml-0.5 h-5 w-5   ' + extraIconClass
    // iconClass += isCurrent ? 'text-indigo-500 ' : 'text-gray-400 group-hover:text-gray-500 ';
    iconClass += (type == 'icon' && align == 'right') ? 'ml-2 ' : 'mr-2 '
    return iconClass
}

</script>

<template>
    <div>
        <!-- Tabs: Mobile view -->
        <div class="sm:hidden px-3 pt-2">
            <label for="tabs" class="sr-only">Select a tab</label>

            <!-- TODO: use Headless or component Dropdown so the icon is able to show (currrently not) -->
            <select id="tabs" name="tabs" class="block w-full disabled:bg-gray-200 capitalize rounded-md border-gray-300 focus:border-gray-500 focus:ring-gray-500"
                @input="(val: any) => onChangeTab(val.target.value)"
                :disabled="Object.keys(navigation ?? {})?.length < 2"
            >
                <option v-for="(tab, tabSlug) in navigation" :key="tabSlug" :selected="tabSlug == currentTab" :value="tabSlug" class="capitalize">
                    <FontAwesomeIcon v-if="tabLoading == tabSlug" icon="fad fa-spinner-third" class="animate-spin" :class="tabIconClass(tabSlug === currentTab, tab.type, tab.align, tab.iconClass || '')" aria-hidden="true"/>
                    <FontAwesomeIcon v-else-if="tab.icon" :icon="tab.icon" aria-hidden="true"/>
                    {{ tab.title }}
                </option>
            </select>
        </div>

        <!-- Tabs: Desktop view -->
        <div class="hidden sm:block">
            <div class="border-b border-gray-200 flex">
                <!-- Left section -->
                <nav class="-mb-px flex w-full gap-x-6 ml-4" aria-label="Tabs">
                    <template v-for="(tab, tabSlug) in navigation" :key="tabSlug">
                        <button
                            v-if="tab.align !== 'right'"
                            @click="onChangeTab(tabSlug)"
                            :class="[tabSlug === currentTab ? 'tabNavigationActive' : 'tabNavigation']"
                            class="relative group flex items-center py-2 px-1 font-medium capitalize text-left text-sm md:text-base w-fit"
                            :aria-current="tabSlug === currentTab ? 'page' : undefined"
                        >
                            <FontAwesomeIcon v-if="tabLoading === tabSlug" icon="fad fa-spinner-third" class="animate-spin" :class="tabIconClass(tabSlug === currentTab, tab.type, tab.align, tab.iconClass || '')" aria-hidden="true"/>
                            <FontAwesomeIcon v-else-if="tab.icon" :icon="tab.icon" :class="tabIconClass(tabSlug === currentTab, tab.type, tab.align, tab.iconClass || '')" aria-hidden="true"/>
                            {{ tab.title }}

                            <FontAwesomeIcon v-if="tab.indicator" icon='fas fa-circle' class='animate-pulse absolute top-3 -right-1 text-blue-500 text-[6px]' fixed-width aria-hidden='true' />
                        </button>
                    </template>
                </nav>

                <!-- Right section -->
                <nav class="flex flex-row-reverse mr-4" aria-label="Secondary Tabs">
                    <template v-for="(tab,tabSlug) in navigation" :key="tabSlug">
                        <button
                            v-if="tab.align === 'right'"
                            @click="onChangeTab(tabSlug)"
                            :class="[tabSlug === currentTab ? 'tabNavigationActive' : 'tabNavigation']"
                            class="relative group inline-flex gap-x-1.5 justify-center items-center py-2 px-2 border-b-2 font-medium text-sm"
                            :aria-current="tabSlug === currentTab ? 'page' : undefined"
                            v-tooltip="capitalize(tab.title)"
                        >
                            <FontAwesomeIcon v-if="tabLoading === tabSlug" icon="fad fa-spinner-third" class="animate-spin h-5 w-5" aria-hidden="true"/>
                            <FontAwesomeIcon v-else-if="tab.icon" :icon="tab.icon" class="h-5 w-5" aria-hidden="true"/>
                            <span v-if="tab.type!=='icon'" class="capitalize whitespace-nowrap">{{ tab.title }}</span>

                            <FontAwesomeIcon v-if="tab.indicator" icon='fas fa-circle' class='animate-ping absolute top-3 -right-1 text-blue-500 text-[6px]' fixed-width aria-hidden='true' />
                            <FontAwesomeIcon v-if="tab.indicator" icon='fas fa-circle' class='absolute top-3 -right-1 text-blue-500 text-[6px]' fixed-width aria-hidden='true' />
                        </button>
                    </template>
                </nav>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.tabNavigation {
    @apply transition-all duration-75;
    filter: saturate(0);
    border-bottom: v-bind('`2px solid transparent`');
    color: v-bind('`${layoutStore.app.theme[0]}99`');

    &:hover {
        filter: saturate(0.85);
        border-bottom: v-bind('`2px solid ${layoutStore.app.theme[0]}AA`');
        color: v-bind('`${layoutStore.app.theme[0]}AA`');
    }
}

.tabNavigationActive {
    border-bottom: v-bind('`2px solid ${layoutStore.app.theme[0]}`');
    color: v-bind('layoutStore.app.theme[0]');
}

</style>
