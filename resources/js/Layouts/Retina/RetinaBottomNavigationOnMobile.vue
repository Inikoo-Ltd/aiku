<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 03 Mar 2023 13:49:56 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import { inject, onMounted, ref } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBoxUsd, faParking, faUsersCog, faLightbulb, faUserHardHat, faUser, faInventory, faConveyorBeltAlt, faChevronDown, faPalletAlt, faUserFriends, faKey, faFolderTree, faBooks } from "@fal"

import { generateNavigationName } from '@/Composables/useConvertString'

import RetinaNavigationHorizontalNew from "./RetinaNavigationHorizontalNew.vue"
import RetinaMobileNavigationSimple from "./RetinaMobileNavigationSimple.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { isNavigationActive } from "@/Composables/useUrl"
import { Popover } from "primevue"
library.add(faBoxUsd, faParking, faUsersCog, faLightbulb, faUserHardHat, faUser, faUsersCog, faInventory, faConveyorBeltAlt, faChevronDown, faPalletAlt, faUserFriends, faKey, faFolderTree, faBooks)

const layout = inject('layout', {})

// onMounted(() => {
//     if (localStorage.getItem('leftSideBar')) {
//         // Read from local storage then store to Pinia
//         layout.leftSidebar.show = JSON.parse(localStorage.getItem('leftSideBar') ?? '')
//     }
// })

// const iconList: { [key: string]: string } = {
//     shop: 'fal fa-store-alt',
//     warehouse: 'fal fa-warehouse-alt',
//     fulfilment: 'fal fa-hand-holding-box',
// }

// Section: Popover
const selectedNav = ref()
const _op = ref()
const toggle = (event) => {
    _op.value?.toggle(event)
}



const currentActiveHorizontal = (nav: {}) => {
    const xxx =  nav.horizontal_navigations?.find(horizontal => {
        return horizontal.key == layout.currentPlatform
    })

    if (!xxx) {
        return nav.horizontal_navigations?.[0]
    }

    return xxx
}

const isSomeSubnavActive = (nav: {}) => {
    return isNavigationActive(layout.currentRoute, currentActiveHorizontal(nav)?.root)
}
</script>

<template>
    <div class="flex gap-x-3 pt-2 pb-3 px-4 overflow-x-auto">
        <template v-for="(grpNav, itemKey) in layout.navigation">
            <template v-if="grpNav.type === 'horizontal'">
                <div
                    @click="(e) => (selectedNav = grpNav, toggle(e))"
                    class="group flex items-center px-2 text-[20px] gap-x-2 pr-5 relative"
                    :class="[
                        (selectedNav == grpNav && _op.visible) || isSomeSubnavActive(grpNav)
                            ? 'navigationActive'
                            : 'navigation',
                    ]"
                    :style="[(selectedNav == grpNav && _op.visible) || isSomeSubnavActive(grpNav) ? {
                        'background-color': layout.app?.theme[1],
                        'color': layout.app?.theme[2]
                    } : {} ]"
                    astart="() => isLoading = true"
                    afinish="() => isLoading = false"
                >
                    <!-- <LoadingIcon v-if="'isLoading'" class="flex-shrink-0" /> -->
                    <FontAwesomeIcon xv-else-if="grpNav.icon" aria-hidden="true" :rotation="grpNav.before_horizontal?.subNavigation?.[0].icon_rotation" class="flex-shrink-0" fixed-width :icon="grpNav.before_horizontal?.subNavigation?.[0].icon" />
                    <FontAwesomeIcon icon="fal fa-chevron-right" class="text-xxs absolute top-1/2 -translate-y-1/2 right-1.5 transition-all" fixed-width aria-hidden="true"
                        :class="selectedNav == grpNav && _op.visible ? 'rotate-90' : '-rotate-90'"
                    />
                </div>
            </template>
            
            <RetinaMobileNavigationSimple
                v-else
                :nav="grpNav"
                :navKey="generateNavigationName(itemKey)"
            />
        </template>

        <slot />

        <Popover ref="_op">
            <template #container>
                <RetinaNavigationHorizontalNew
                    class="bg-[rgba(20,20,20,0.80)] text-white"
                    :style="{
                        'background-color': 'var(--theme-color-0)',
                        'color': 'var(--theme-color-1)'
                    }"
                    v-if="selectedNav"
                    xkey="itemKey + 'platform'"
                    :nav="selectedNav"
                    isNoArrows
                />
            </template>
        </Popover>
    </div>
</template>
