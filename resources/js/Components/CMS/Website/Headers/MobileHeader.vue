<script setup lang="ts">
import IrisSidebar from '@/Components/IrisSidebar.vue'
import { getStyles } from "@/Composables/styles";
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Components/Image.vue"
import { inject, ref, onMounted } from 'vue';
import { library } from "@fortawesome/fontawesome-svg-core";
import { faGalaxy, faTimesCircle, faUserCircle } from "@fas";
import OverlayBadge from 'primevue/overlaybadge';
import { faBaby, faShoppingCart as falShoppingCart, faCactus, faObjectGroup, faUser, faHouse, faTruck, faTag, faPhone, faUserCircle as falUserCircle, faBars } from "@fal";
import {
    faBackpack,
    faTruckLoading,
    faTruckMoving,
    faTruckContainer,
    faUser as faUserRegular,
    faWarehouse,
    faWarehouseAlt,
    faShippingFast,
    faInventory,
    faDollyFlatbedAlt,
    faBoxes,
    faShoppingCart,
    faBadgePercent,
    faChevronRight,
    faCaretRight,
    faPhoneAlt,
    faGlobe,
    faPercent,
    faPoundSign,
    faClock
} from "@far";
import { faLambda } from "@fad";
import LuigiSearch from "@/Components/CMS/LuigiSearch.vue"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { computed } from 'vue'
import LinkIris from '@/Components/Iris/LinkIris.vue'
import LuigiSearchMobile from '../../LuigiSearchMobile.vue'
import { urlLoginWithRedirect } from '@/Composables/urlLoginWithRedirect'
import { trans } from 'laravel-vue-i18n'

// Add icons to the library
library.add(
    faTimesCircle, faUser, faCactus, faBaby, falShoppingCart, faObjectGroup, faGalaxy, faLambda, faBackpack, faHouse, faTruck, faTag, faPhone, faBars,
    faTruckLoading, faTruckMoving, faTruckContainer, faUserRegular, faWarehouse, faWarehouseAlt, faShippingFast, faInventory, faUserCircle,
    faDollyFlatbedAlt, faBoxes, faShoppingCart, faBadgePercent, faChevronRight, faCaretRight, faPhoneAlt, faGlobe, faPercent, faPoundSign, faClock, falUserCircle
);


const props = defineProps<{
    headerData: {
        logo: {
            properties: object
            image: {
                source: Object
            }
            alt: string
        }
    },
    menuData: Object
    productCategories: Array<any>
    screenType?: 'mobile' | 'tablet' | 'desktop'
}>()

// console.log('-- Header data', props)
const layout = inject('layout', retinaLayoutStructure)
const isLoggedIn = inject('isPreviewLoggedIn', false)
// const isLoggedIn = false
const sidebarMenu = inject('sidebarMenu', null) // come from layout PreviewLayout (workshop), will null if in Iris

const computedSelectedSidebarData = computed(() => {
    return sidebarMenu?.value || layout.iris?.sidebar
})

const screenType = inject('screenType', 'desktop')

// Method: to remove fontSize from properties
const getStylesRemoveFontSize = (properties, screenType) => {
    const xxx = { ...getStyles(properties, screenType) }
    delete xxx?.fontSize

    return xxx
}
</script>

<template>
    <div id="mobile-header" class="block md:hidden p-3">
        <div class="grid grid-cols-3 items-center justify-between">
            <!-- Section: Hamburger & Search -->
            <div class="flex items-center gap-x-1 w-fit">
                <!-- Hamburger Sidebar -->
                <IrisSidebar
                    :header="headerData"
                    :menu="menuData"
                    :productCategories="productCategories"
                    :screenType="screenType"
                    :sidebarLogo="computedSelectedSidebarData?.data?.fieldValue?.sidebar_logo"
                    :sidebar="computedSelectedSidebarData"
                    
                >
                    <template #icon>
                        <FontAwesomeIcon
                            :icon="headerData?.mobile?.menu?.icon || 'fal fa-bars'"
                            :style="getStylesRemoveFontSize(headerData?.mobile?.menu?.container?.properties, screenType)"
                            fixed-width
                            aria-hidden="true"
                            class="text-3xl"
                        />
                    </template>
                </IrisSidebar>

                <!-- Search Bar -->
                <LuigiSearchMobile v-if="layout.iris?.luigisbox_tracker_id"
                    id="luigi_mobile"
                    :style="{
                        ...getStyles(headerData?.mobile?.profile?.container?.properties, screenType),
                    }"
                    class="text-3xl"
                />
            </div>

            <!-- Section: Logo -->
            <div class="xcol-span-2 flex justify-end items-center w-full" :class="!isLoggedIn ?  layout.retina?.type == 'b2b' ? 'justify-end' :'justify-center' : 'justify-end'">
                <component :is="LinkIris" :href="'/'" class="block h-fit max-h-[50px] w-full max-w-32">
                    <Image v-if="headerData.logo?.image?.source" :src="headerData.logo?.image?.source" alt="logo"
                        class="w-full h-auto object-contain" />
                </component>
            </div>

            <!-- Section: Profile -->
            <div class="xcol-span-2 flex items-center justify-end gap-x-2 w-full mr-3">
                <!-- Not Logged In -->
                <LinkIris v-if="!isLoggedIn" :href="urlLoginWithRedirect()" class="px-1">
                    <FontAwesomeIcon icon="fal fa-sign-in" fixed-width aria-hidden="true"
                        :style="getStyles(headerData?.mobile?.profile?.container?.properties, screenType)"
                        class="text-3xl"
                    />
                </LinkIris>

                <!-- Logged In -->
                <template v-else>
                    <OverlayBadge v-if="layout.retina?.type == 'b2b'"  :value="layout?.iris_variables?.cart_count" size="small">
                        <LinkIris href="/app/basket" class="px-1">
                            <FontAwesomeIcon
                                icon="fal fa-shopping-cart"
                                fixed-width
                                aria-hidden="true"
                                :style="getStyles(headerData?.mobile?.profile?.container?.properties, screenType)"
                                class="text-3xl"
                            />
                        </LinkIris>
                    </OverlayBadge>

                    <LinkIris href="/app/dashboard" class="px-1">
                        <FontAwesomeIcon
                            :icon="'fal fa-user-circle'"
                            fixed-width
                            aria-hidden="true"
                            class="text-3xl"
                            :style="getStyles(headerData?.mobile?.profile?.container?.properties, screenType)" />
                    </LinkIris>
                </template>
            </div>
        </div>
    </div>

</template>

<style scoped></style>