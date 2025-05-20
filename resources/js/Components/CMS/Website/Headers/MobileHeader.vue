<script setup lang="ts">
import MobileMenu from '@/Components/MobileMenu.vue'
import { getStyles } from "@/Composables/styles";
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Components/Image.vue"

import { library } from "@fortawesome/fontawesome-svg-core";
import { faGalaxy, faTimesCircle, faUserCircle } from "@fas";
import { faBaby, faCactus, faObjectGroup, faUser, faHouse, faTruck, faTag, faPhone, faUserCircle as falUserCircle, faBars } from "@fal";
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

// Add icons to the library
library.add(
    faTimesCircle, faUser, faCactus, faBaby, faObjectGroup, faGalaxy, faLambda, faBackpack, faHouse, faTruck, faTag, faPhone, faBars,
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
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()


</script>

<template>
    <div class="block md:hidden p-3">
        <div class="flex justify-between items-center">
            <MobileMenu :header="headerData" :menu="menuData" />

            <Link href="/">
            <div>
                <Image v-if="headerData.logo?.image?.source" :src="headerData.logo?.image?.source" :imageCover="true"
                    :alt="headerData.logo?.alt" :style="getStyles(headerData.logo.properties, screenType)" />
            </div>
            </Link>


            <div class="flex items-center cursor-pointer">
                <FontAwesomeIcon :icon="headerData?.mobile?.profile?.icon ? headerData?.mobile?.profile?.icon : faUser"
                    :style="getStyles(headerData?.mobile?.profile?.container?.properties, screenType)" />
            </div>
        </div>
    </div>
</template>

<style scoped></style>