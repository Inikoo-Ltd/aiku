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
const upcommingCustomSidebarMenu = inject('newCustomSidebarMenu') //make sure the provide available on each layout


const convertToDepartmentStructure = (menusData) => {
    const dataArray = Array.isArray(menusData) ? menusData : [menusData];

    // Regex untuk menghapus awalan http:// atau https://
    const removeProtocol = url => {
        if (!url || typeof url !== 'string') return null;
        return url.replace(/^https?:\/\//, '');
    };

    return dataArray.map(menu => {
        const mainLinkHref = menu?.link?.href;
        const mainLinkTarget = menu?.link?.type;

        const departmentStructure = {
            url: removeProtocol(mainLinkHref),
            name: menu?.label || null,
            type: mainLinkTarget || null,
            sub_departments: []
        };

        if (Array.isArray(menu?.subnavs)) {
            menu.subnavs.forEach(subnav => {
                const subLinkHref = subnav?.link?.href;
                const subLinkTarget = subnav?.link?.type;

                const subDepartment = {
                    url: removeProtocol(subLinkHref),
                    name: subnav?.title || null,
                    type: subLinkTarget || null,
                    families: []
                };

                if (Array.isArray(subnav?.links)) {
                    subnav.links.forEach(link => {
                        const linkHref = link?.link?.href;
                        const linkTarget = link?.link?.type;

                        const family = {
                            url: removeProtocol(linkHref),
                            name: link?.label || null,
                            type: linkTarget || null
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

const customMenusBottom = ref([]); // Create a reactive ref to hold the bottom navigation
const customMenusTop = ref([]); // Create a reactive ref to hold the top navigation

watch(
    () => upcommingCustomSidebarMenu,
    (newValue) => {
        if (newValue) {
            const navigationBottomData = newValue?.value?.data?.fieldValue?.navigation_bottom;
            const navigationData = newValue?.value?.data?.fieldValue?.navigation;
            
            // Process navigation_bottom data
            if (navigationBottomData) {
                const convertedBottom = convertToDepartmentStructure(navigationBottomData);
                customMenusBottom.value = [...convertedBottom];
                // console.log('Bottom menu data:', convertedBottom);
            } else {
                customMenusBottom.value = [];
            }
            
            // Process navigation data
            if (navigationData) {
                const convertedTop = convertToDepartmentStructure(navigationData);
                customMenusTop.value = [...convertedTop];
                // console.log('Top menu data:', convertedTop);
            } else {
                customMenusTop.value = [];
            }
        } else {
            customMenusBottom.value = []; // Handle the case where newValue is null or undefined
            customMenusTop.value = [];
        }
    },
    { immediate: true, deep: true } // Add options for immediate and deep watching
);
</script>

<template>
    <div class="block md:hidden p-3">
        <div class="flex justify-between items-center">
            <!-- Section: Hamburger mobile -->
            <MobileMenu :header="headerData" :menu="menuData" :productCategories="productCategories" :custom-menus-bottom="customMenusBottom" :custom-menus-top="customMenusTop" />

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