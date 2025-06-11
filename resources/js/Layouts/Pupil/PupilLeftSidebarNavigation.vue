<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 03 Mar 2023 13:49:56 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import { inject, onMounted } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBoxUsd, faParking, faUsersCog, faLightbulb, faUserHardHat, faUser, faInventory, faConveyorBeltAlt, faChevronDown, faPalletAlt, faUserFriends } from "@fal"
// import { useLayoutStore } from "@/Stores/retinaLayout.js"

import PupilNavigationSimple from '@/Layouts/Pupil/PupilNavigationSimple.vue'
import PupilNavigationHorizontal from '@/Layouts/Pupil/PupilNavigationHorizontal.vue'
import PupilNavigationGroup from "@/Layouts/Pupil/PupilNavigationGroup.vue"


import { generateNavigationName, generateCurrentString } from '@/Composables/useConvertString'
import { get } from 'lodash-es'
library.add(faBoxUsd, faParking, faUsersCog, faLightbulb, faUserHardHat, faUser, faUsersCog, faInventory, faConveyorBeltAlt, faChevronDown, faPalletAlt, faUserFriends)

const layout = inject('layout', {})

onMounted(() => {
    if (localStorage.getItem('leftSideBar')) {
        // Read from local storage then store to Pinia
        layout.leftSidebar.show = JSON.parse(localStorage.getItem('leftSideBar') ?? '')
    }
})

const iconList: { [key: string]: string } = {
    shop: 'fal fa-store-alt',
    warehouse: 'fal fa-warehouse-alt',
    fulfilment: 'fal fa-hand-holding-box',
}

</script>

<template>
    <nav class="isolate relative flex flex-grow flex-col pt-3 pb-4 px-2 h-full overflow-y-auto custom-hide-scrollbar flex-1 gap-y-1.5" aria-label="Sidebar">
        <!-- LeftSidebar: Org -->
        <!-- <span class="text-white">{{ layout.currentShop }} -- {{ layout.currentWarehouse }}</span> -->
        <template v-if="get(layout, ['navigation', 'org', layout.currentParams?.organisation], false)">
            <template v-for="(orgNav, itemKey) in layout.navigation.org[layout.currentParams.organisation]" :key="itemKey" >
                <!-- shops_index, warehouses_index, fulfilments_index -->
                <template v-if="itemKey == 'shops_index' || itemKey == 'warehouses_index' || itemKey == 'fulfilments_index'">
                    <!-- Shops index (if the shop lenght more than 1) -->
                    <template v-if="itemKey == 'shops_index' && (layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_shops.length || 0) > 1">
                        <PupilNavigationSimple v-if="!layout.organisationsState[layout.currentParams.organisation].currentShop"
                            :nav="orgNav"
                            :navKey="itemKey"
                        />
                    </template>

                    <!-- Warehouses index (if the warehouse lenght more than 1) -->
                    <template v-if="itemKey == 'warehouses_index' && (layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_warehouses.length || 0) > 1">
                        <PupilNavigationSimple v-if="!layout.organisationsState?.[layout.currentParams.organisation]?.currentWarehouse"
                            :nav="orgNav"
                            :navKey="itemKey"
                        />
                    </template>

                    <!-- Fulfilments index (if the fulfilment lenght more than 1) -->
                    <template v-if="itemKey == 'fulfilments_index' && (layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_fulfilments.length || 0) > 1">
                        <PupilNavigationSimple v-if="!layout.organisationsState?.[layout.currentParams.organisation]?.currentFulfilment"
                            :nav="orgNav"
                            :navKey="itemKey"
                        />
                    </template>
                </template>

                <!-- shops_navigation or warehouses_navigation or fulfilments_navigation -->
                <template v-else-if="itemKey == 'shops_navigation' || itemKey == 'warehouses_navigation' || itemKey == 'fulfilments_navigation'">
                    <template v-if="itemKey == 'shops_navigation' && (layout.organisationsState?.[layout.currentParams.organisation]?.currentType == 'shop') && layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_shops.length">
                        <!-- If: Shops length is 1 (show the subNav directly) -->
                        <!-- <PupilNavigationSimple v-if="layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_shops.length === 1"
                            :nav="Object.values(Object.values(orgNav)[0])[0]"
                            :navKey="generateNavigationName(Object.keys(orgNav)[0])"
                        /> -->
                        <template v-if="layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_shops.length === 1">
                            <!-- <PupilNavigationSimple v-for="nav, navKey in Object.values(orgNav)[0]"
                                :nav="nav"
                                :navKey="navKey"
                            /> -->

                            <PupilNavigationGroup
                                :orgNav="orgNav"
                                :itemKey="generateNavigationName(itemKey)"
                                :icon="iconList[generateNavigationName(itemKey)] || ''"
                            />
                        </template>

                        <template v-else-if="layout.organisationsState?.[layout.currentParams.organisation]?.[generateNavigationName(generateCurrentString(itemKey))]">
                            <PupilNavigationGroup
                                :orgNav="orgNav"
                                :itemKey="generateNavigationName(itemKey)"
                                :icon="iconList[generateNavigationName(itemKey)]"
                            />
                        </template>
                    </template>

                    <template v-if="itemKey == 'warehouses_navigation' && layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_warehouses.length">
                        <!-- If: Warehouses length is 1 -->
                        <!-- <PupilNavigationSimple v-if="layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_warehouses.length === 1"
                            :nav="Object.values(Object.values(orgNav)[0])[0]"
                            :navKey="generateNavigationName(Object.keys(orgNav)[0])"
                        /> -->
                        <template v-if="layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_warehouses.length === 1">
                            <!-- <PupilNavigationSimple v-for="nav, navKey in Object.values(orgNav)[0]"
                                :nav="nav"
                                :navKey="navKey"
                            /> -->

                            <PupilNavigationGroup
                                :orgNav="orgNav"
                                :itemKey="generateNavigationName(itemKey)"
                                :icon="iconList[generateNavigationName(itemKey)] || ''"
                            />
                        </template>

                        <!-- Else: Warehouses length more than 1 -->
                        <template v-else-if="layout.organisationsState?.[layout.currentParams.organisation]?.[generateNavigationName(generateCurrentString(itemKey))]">
                            <PupilNavigationGroup
                                :orgNav="orgNav"
                                :itemKey="generateNavigationName(itemKey)"
                                :icon="iconList[generateNavigationName(itemKey)]"
                            />
                        </template>
                    </template>

                    <template v-if="
                        itemKey == 'fulfilments_navigation' && (layout.organisationsState?.[layout.currentParams.organisation]?.currentType == 'fulfilment')
                        && layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_fulfilments.length
                    ">
                        <!-- If Fulfilment length is 1 -->
                        <template v-if="layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.authorised_fulfilments.length === 1">
                            <!-- <PupilNavigationSimple v-for="nav, navKey in Object.values(orgNav)[0]"
                                :nav="nav"
                                :navKey="navKey"
                            /> -->

                            <PupilNavigationGroup
                                :orgNav="orgNav"
                                :itemKey="generateNavigationName(itemKey)"
                                :icon="iconList[generateNavigationName(itemKey)] || ''"
                            />
                        </template>

                        <!-- Else: Fulfilment length more than 1 -->
                        <PupilNavigationGroup v-else
                            :orgNav="orgNav"
                            :itemKey="generateNavigationName(itemKey)"
                            :icon="iconList[generateNavigationName(itemKey)] || ''"
                        />
                    </template>
                </template>

                <!-- Simple Navigation: HR, Procurement, etc -->
                <template v-else>
                    <PupilNavigationSimple
                        :nav="orgNav"
                        :navKey="itemKey"
                    />
                </template>
            </template>
        </template>

        <!-- LeftSidebar: Grp -->
        <template v-else v-for="(grpNav, itemKey) in layout.navigation">
            <PupilNavigationSimple
                v-if="itemKey != 'platforms_navigation'"
                :nav="grpNav"
                :navKey="generateNavigationName(itemKey)"
            />

            <template v-else>
                <PupilNavigationHorizontal
                    :key="itemKey + 'platform'"
                    :nav="grpNav"
                    :itemKey="generateNavigationName(itemKey)"
                />
            </template>
        </template>

    </nav>
</template>
