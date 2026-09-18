<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Feb 2024 08:02:30 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBoxUsd, faUsersCog, faChartLine, faUserHardHat, faBlenderPhone, faUser, faInventory, faConveyorBeltAlt,
    faChevronDown, faPalletAlt, faAbacus,faCloudRainbow,faShoppingCart,faMountains, faTasksAlt, faTruck,
    faFlaskPotion,faFillDrip,faBullhorn,faBadgePercent,faChargingStation, faBallot, faSlidersH, faChartLineDown,
  faArrowFromLeft,faArrowToBottom, faWarehouse, faFax
} from "@fal"
import { generateNavigationName, generateCurrentString } from '@/Composables/useConvertString'
import '@/Composables/Icon/ProductionsStateIcon'

import { get } from 'lodash-es'
import NavigationSimple from '@/Layouts/Grp/NavigationSimple.vue'
import NavigationGroup from "@/Layouts/Grp/NavigationGroup.vue"
import NavigationScope from "@/Layouts/Grp/NavigationScope.vue"
import NavigationHorizontal from "@/Layouts/Grp/NavigationHorizontal.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { trans } from "laravel-vue-i18n"

library.add(faBoxUsd, faUsersCog, faChartLine, faUserHardHat, faBlenderPhone, faUser, faUsersCog, faInventory, faConveyorBeltAlt, faChevronDown, faPalletAlt,
faAbacus, faCloudRainbow,faShoppingCart,faMountains, faTasksAlt, faTruck, faFlaskPotion, faFillDrip, faBullhorn,faBadgePercent,faChargingStation,
faBallot, faSlidersH, faChartLineDown,faArrowFromLeft,faArrowToBottom, faWarehouse, faFax
)

const layout = inject('layout', layoutStructure)

onMounted(() => {
    if (typeof window !== "undefined") {
        if (localStorage.getItem('leftSideBar')) {
            // Read from local storage then store to Pinia
            layout.leftSidebar.show = JSON.parse(localStorage.getItem('leftSideBar') ?? '')
        }
    }

    measureNavigationScroll()
    if (navigationScroll.value && typeof ResizeObserver !== "undefined") {
        const observer = new ResizeObserver(measureNavigationScroll)
        observer.observe(navigationScroll.value)
        stopWatchingNavigationSize = () => observer.disconnect()
    }
})

const bottomNavigationKeys = ['tasks', 'tickets', 'chat']

const navigationScroll = ref<HTMLElement | null>(null)
const scrolledFromTop = ref(0)
const scrollLeftToBottom = ref(0)

const fadeOpacity = (distance: number) => Math.min(1, Math.max(0, distance / 32))

const topFadeOpacity = computed(() => fadeOpacity(scrolledFromTop.value))
const bottomFadeOpacity = computed(() => fadeOpacity(scrollLeftToBottom.value))

const measureNavigationScroll = () => {
    const element = navigationScroll.value
    if (!element) return
    scrolledFromTop.value = element.scrollTop
    scrollLeftToBottom.value = element.scrollHeight - element.clientHeight - element.scrollTop
}

let stopWatchingNavigationSize: (() => void) | null = null

onBeforeUnmount(() => stopWatchingNavigationSize?.())

watch(
    () => [layout.currentRoute, layout.leftSidebar.show],
    () => nextTick(measureNavigationScroll)
)

const iconList: { [key: string]: string } = {
    shop: 'fal fa-store-alt',
    warehouse: 'fal fa-warehouse-alt',
    fulfilment: 'fal fa-hand-holding-box',
}


</script>

<template>
    <div class="relative flex flex-grow flex-col h-full min-h-0">
    <nav ref="navigationScroll" class="text-white isolate relative flex flex-grow flex-col pt-3 pb-4 px-2 h-full overflow-y-auto custom-hide-scrollbar flex-1 gap-y-1.5" aria-label="Sidebar" @scroll.passive="measureNavigationScroll">
        <div class="hidden">
            {{ get(layout, ['navigation', 'org', layout.currentParams?.organisation], false) }}
        </div>
        <template v-if="get(layout, ['navigation', 'org', layout.currentParams?.organisation], false)">
            <template v-for="(orgNav, itemKey) in layout.navigation.org[layout.currentParams.organisation]" :key="itemKey" >
                <!-- shops_index, warehouses_index, fulfilments_index -->
                <template v-if="itemKey == 'shops_index' || itemKey == 'warehouses_index' || itemKey == 'fulfilments_index'">
                    <!-- Shops index (if the shop length more than 1 || the selected shop is not 'open') -->
                    <template v-if="
                        itemKey == 'shops_index' && (layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_shops?.length || 0) > 1
                        || itemKey == 'shops_index' && (layout.digital_agency.data.find(agency => agency.slug == layout.currentParams.organisation)?.authorised_shops?.length || 0) > 1
                    "
                    >
                        <NavigationSimple v-if="
                            !layout.organisationsState[layout.currentParams.organisation]?.currentShop
                            || (layout.organisationsState[layout.currentParams.organisation]?.currentShop && layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_shops.find(shop => shop.slug === layout.organisationsState[layout.currentParams.organisation]?.currentShop)?.state === 'openxx')
                            
                        "
                            :nav="orgNav"
                            :navKey="itemKey"
                        />
                    </template>

                    <!-- Fulfilments index (if the fulfilment length more than 1  || the selected fulfilment is not 'open') -->
                    <template v-if="itemKey == 'fulfilments_index' && (layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_fulfilments.length || 0) > 1">
                        <NavigationSimple
                            v-if="
                                !layout.organisationsState?.[layout.currentParams.organisation]?.currentFulfilment
                                || (layout.organisationsState[layout.currentParams.organisation]?.currentFulfilment && layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_fulfilments.find(fulfilment => fulfilment.slug === layout.organisationsState[layout.currentParams.organisation]?.currentFulfilment)?.state !== 'open')
                                
                            "
                            :nav="orgNav"
                            :navKey="itemKey"
                        />
                    </template>

                    <!-- Warehouses index (if the warehouse length more than 1) -->
                    <template v-if="
                        itemKey == 'warehouses_index'
                        && ((layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_warehouses.length || 0) > 1 || (layout.agents.data.find(agent => agent.slug == layout.currentParams.organisation)?.authorised_warehouses?.length || 0) > 1)">
                        <NavigationSimple v-if="!layout.organisationsState?.[layout.currentParams.organisation]?.currentWarehouse"
                            :nav="orgNav"
                            :navKey="itemKey"
                        />
                    </template>
                </template>

                <!-- shops_navigation or warehouses_navigation or fulfilments_navigation -->
                <template v-else-if="itemKey == 'shops_fulfilments_navigation' || itemKey == 'warehouses_navigation' || itemKey == 'productions_navigation'">
                    <template v-if="itemKey == 'shops_fulfilments_navigation'">
                        <NavigationHorizontal
                            :key="itemKey + layout.currentParams.organisation"
                            v-if="(
                                layout.organisationsState[layout.currentParams.organisation]?.currentShop && layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_shops.find(shop => shop.slug === layout.organisationsState[layout.currentParams.organisation]?.currentShop)?.state === 'open')
                                || (layout.organisationsState[layout.currentParams.organisation]?.currentFulfilment && layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_fulfilments.find(fulfilment => fulfilment.slug === layout.organisationsState[layout.currentParams.organisation]?.currentFulfilment)?.state === 'open'
                            )"
                            :orgNav="orgNav"
                            :itemKey="generateNavigationName(itemKey)"
                            :numberOptions="layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.[`authorised_${layout.organisationsState[layout.currentParams.organisation].currentType}s`]?.filter(fulfil => {return fulfil.state == 'open'})?.length || 0"
                        />
                    </template>

                    <template v-if="itemKey == 'warehouses_navigation' && (layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_warehouses.length || layout.agents.data.find(agent => agent.slug == layout.currentParams.organisation)?.authorised_warehouses.length)">
                        <!-- If: Warehouses length is 1 -->
                        <template v-if="
                            layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_warehouses.length === 1
                            || layout.agents.data.find(agent => agent.slug == layout.currentParams.organisation)?.authorised_warehouses.length === 1
                        ">
                            <!-- <NavigationGroup
                                :orgNav="orgNav"
                                :itemKey="generateNavigationName(itemKey)"
                                :icon="iconList[generateNavigationName(itemKey)] || ''"
                            /> -->

                            <NavigationScope
                                :key="itemKey"
                                icon="fal fa-warehouse"
                                :navs="orgNav[Object.keys(orgNav)[0]]"
                                :scope="trans('Warehouse') + ` (${Object.keys(orgNav)[0]})`"
                                root="grp.org.warehouses.show"
                            />

                            <!-- <NavigationSimple v-for="(nav, navKey) in Object.values(orgNav)[0]"
                                :nav="nav"
                                :navKey="navKey"
                            /> -->
                        </template>

                        <!-- Else: Warehouses length more than 1 -->
                        <template v-else-if="layout.organisationsState?.[layout.currentParams.organisation]?.[generateNavigationName(generateCurrentString(itemKey))]">
                            <NavigationGroup
                                :orgNav="orgNav"
                                :itemKey="generateNavigationName(itemKey)"
                                :icon="iconList[generateNavigationName(itemKey)]"
                            />
                        </template>
                    </template>
                    
                    <template v-if="itemKey == 'productions_navigation' && layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_productions.length">
                        <NavigationScope
                            :key="itemKey"
                            icon="fal fa-fill-drip"
                            :navs="orgNav[Object.keys(orgNav)[0]]"
                            :scope="trans('Production')"
                            root="grp.org.productions.show."
                        />
                    </template>
                </template>

                <!-- Simple Navigation: HR, Procurement, etc -->
                <template v-else>
                    <NavigationSimple
                        :nav="orgNav"
                        :navKey="itemKey"
                        :class="{ hidden: itemKey === 'tasks' }"
                    />
                </template>
            </template>
        </template>

        <!-- LeftSidebar: Grp -->
        <template v-else>
            <template v-for="(grpNav, itemKey) in layout.navigation.grp" :key="itemKey">
                <div v-if="bottomNavigationKeys.includes(String(itemKey))" class="hidden">
                    <NavigationSimple :nav="grpNav" :navKey="itemKey" />
                </div>
                <NavigationSimple v-else :nav="grpNav" :navKey="itemKey" />
            </template>
        </template>

    </nav>
    <div
        class="navigationFadeTop pointer-events-none absolute inset-x-0 top-0 h-6 transition-opacity duration-200"
        :style="{ opacity: topFadeOpacity }"
        aria-hidden="true" />
    <div
        class="navigationFadeBottom pointer-events-none absolute inset-x-0 bottom-0 h-8 transition-opacity duration-200"
        :style="{ opacity: bottomFadeOpacity }"
        aria-hidden="true" />
    </div>
</template>

<style scoped>
.navigationFadeTop {
    background: v-bind("`linear-gradient(to bottom, color-mix(in srgb, ${layout?.app?.theme[0]}, 15% black), transparent)`");
}

.navigationFadeBottom {
    background: v-bind("`linear-gradient(to top, color-mix(in srgb, ${layout?.app?.theme[0]}, 15% black), transparent)`");
}
</style>
