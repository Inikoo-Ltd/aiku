<script setup lang="ts">
import MobileMenu from '@/Components/MobileMenu.vue'
import { getStyles } from "@/Composables/styles";
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Components/Image.vue"
import { inject, watch, ref } from 'vue';
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
import LuigiSearch from "@/Components/CMS/LuigiSearch.vue"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'

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
    productCategories : Array<any>
    screenType?: 'mobile' | 'tablet' | 'desktop'
}>()

const layout = inject('layout', retinaLayoutStructure)
const isLoggedIn = inject('isPreviewLoggedIn', false)
const upcommingProductCategories = inject('newCustomSidebarMenu') //make sure the provide available on each layout


const  convertToDepartmentStructure = (menusData) => {

    // If input is not an array, wrap it in an array
    const dataArray = Array.isArray(menusData) ? menusData : [menusData];

    // Convert each aromatherapy item to department structure
    return dataArray.map(menu => {
        const mainLinkHref = menu?.link?.href;

        const departmentStructure = {
            url: typeof mainLinkHref === 'string' ? mainLinkHref.replace('https://', '') : undefined,
            name: menu?.label || undefined,
            sub_departments: []
        };

        if (Array.isArray(menu?.subnavs)) {
            menu.subnavs.forEach(subnav => {
                const subLinkHref = subnav?.link?.href;
                const subDepartment = {
                    url: typeof subLinkHref === 'string' ? subLinkHref.replace('https://', '') : undefined,
                    name: subnav?.title || undefined,
                    families: []
                };

                if (Array.isArray(subnav?.links)) {
                    subnav.links.forEach(link => {
                        const linkHref = link?.link?.href;
                        const family = {
                            url: typeof linkHref === 'string' ? linkHref.replace('https://', '') : undefined,
                            name: link?.label || undefined
                        };
                        subDepartment.families.push(family);
                    });
                }

                departmentStructure.sub_departments.push(subDepartment);
            });
        }

        return departmentStructure;
    });
}



const customMenus = ref([]); // Create a reactive ref to hold the new value

watch(
    () => upcommingProductCategories,
    (newValue) => {
        if (newValue) {
            const converted = convertToDepartmentStructure(newValue?.value?.data?.fieldValue.navigation);
            customMenus.value = [...converted];
            // console.log(converted);
        } else {
            customMenus.value = []; // Handle the case where the data is null or undefined
        }
    },
    { immediate: true, deep:true } // Add options for immediate and deep watching
);

</script>

<template>
    <div class="block md:hidden p-3">
        <div class="flex justify-between items-center">
            <!-- Section: Hamburger mobile -->
            <MobileMenu :header="headerData" :menu="menuData" :productCategories="productCategories" :custom-menus="customMenus" />

            <!-- Section: Logo  -->
            <component :is="true ? Link : 'div'" :href="'/'" class="block w-full h-[65px] mb-1 rounded">
                <Image  v-if="headerData.logo?.image?.source"  :src="headerData.logo?.image?.source" alt="logo" :imageCover="true"
                    :style="{ objectFit: 'contain' }" />
            </component>

            <!-- Section: Profile -->
            <div class="flex items-center cursor-pointer">
                <Link href="/app/profile" v-if="isLoggedIn">
                    <FontAwesomeIcon :icon="headerData?.mobile?.profile?.icon ? headerData?.mobile?.profile?.icon : faUser"
                    :style="getStyles(headerData?.mobile?.profile?.container?.properties, screenType)" />
                </Link>
            </div>
        </div>

        <!-- Search Bar -->
        <div v-if="layout.iris?.luigisbox_tracker_id" class="relative justify-self-center w-full">
            <LuigiSearch id="luigi_mobile" />
        </div>
    </div>
</template>

<style scoped></style>
