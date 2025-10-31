<script setup lang="ts">
import IrisSidebar from '@/Components/IrisSidebar.vue'
import { getStyles } from "@/Composables/styles";
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Components/Image.vue"
import { inject, watch, ref, onMounted } from 'vue';
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

console.log('-- Header data', props)
const layout = inject('layout', retinaLayoutStructure)
const isLoggedIn = inject('isPreviewLoggedIn', false)
// const isLoggedIn = false
const sidebarMenu = inject('sidebarMenu', null) // come from layout PreviewLayout
const computedSelectedSidebarData = computed(() => {
    return sidebarMenu?.value || layout.iris?.sidebar
})

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

watch(computedSelectedSidebarData,
    (newValue) => {
        if (newValue) {
            const navigationBottomData = newValue?.data?.fieldValue?.navigation_bottom;
            const navigationData = newValue?.data?.fieldValue?.navigation;

            // console.log('navigationBottomData', navigationBottomData);
            // Process navigation_bottom data
            if (navigationBottomData) {
                const convertedBottom = convertToDepartmentStructure(navigationBottomData);
                customMenusBottom.value = [...convertedBottom];
                // console.log('Bottom menu data:', convertedBottom);
            } else {
                customMenusBottom.value = [];
            }
            // console.log('navigationData 111', customMenusBottom.value);

            // Process navigation data
            if (navigationData) {
                const convertedTop = convertToDepartmentStructure(navigationData);
                customMenusTop.value = [...convertedTop];
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

const screenType = inject('screenType', 'desktop')
console.log('sss',layout)
</script>

<template>
    <div class="block md:hidden p-3">
        <div class="grid grid-cols-3 items-center justify-between">
            <!-- Section: Hamburger & Search -->
            <div class="flex items-center gap-x-2 w-fit">
                <!-- Hamburger Sidebar -->
                <IrisSidebar :header="headerData" :menu="menuData" :productCategories="productCategories"
                    :custom-menus-bottom="customMenusBottom" :custom-menus-top="customMenusTop" :screenType="screenType"
                    :sidebarLogo="computedSelectedSidebarData?.data?.fieldValue?.sidebar_logo"
                    :sidebar="computedSelectedSidebarData" />

                <!-- Search Bar -->
                <LuigiSearchMobile v-if="layout.iris?.luigisbox_tracker_id" id="luigi_mobile" :style="{
                    ...getStyles(headerData?.mobile?.profile?.container?.properties, screenType),
                }" />
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
                        :style="getStyles(headerData?.mobile?.profile?.container?.properties, screenType)" />
                </LinkIris>

                <!-- Logged In -->
                <template v-else>
                    <OverlayBadge v-if="layout.retina?.type == 'b2b'"  :value="layout?.iris_variables?.cart_count">
                        <LinkIris href="/app/basket" class="px-1">
                            <FontAwesomeIcon icon="fal fa-shopping-cart" fixed-width aria-hidden="true"
                                :style="getStyles(headerData?.mobile?.profile?.container?.properties, screenType)" />
                        </LinkIris>
                    </OverlayBadge>

                    <LinkIris v-else href="/app/dashboard" class="px-1">
                        <img
                            src="/art/dashboard.png"
                            :style="{
                                ...getStyles(headerData?.mobile?.profile?.container?.properties, screenType),
                                height: '1.05em',
                                verticalAlign: 'middle'
                            }"
                            :alt="trans('Dashboard icon')"
                        />
                    </LinkIris>

                    <LinkIris href="/app/profile" class="px-1">
                        <FontAwesomeIcon :icon="headerData?.mobile?.profile?.icon || 'fal fa-user-circle'" fixed-width
                            aria-hidden="true"
                            :style="getStyles(headerData?.mobile?.profile?.container?.properties, screenType)" />
                    </LinkIris>
                </template>
            </div>
        </div>
    </div>

</template>

<style scoped></style>