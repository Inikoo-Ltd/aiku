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
    sidebar?:any
}>()

console.log('old menu sidebar', props);
// console.log('menu mobile', props.menuData);

const layout = inject('layout', retinaLayoutStructure)
const isLoggedIn = inject('isPreviewLoggedIn', false)
const upcommingProductCategories = inject('newCustomSidebarMenu')


// console.log('custom menu sidebar',  upcommingProductCategories);
function convertToDepartmentStructure(aromatherapyDataArray) {
    console.log(aromatherapyDataArray);
    // If input is not an array, wrap it in an array
    const dataArray = Array.isArray(aromatherapyDataArray) ? aromatherapyDataArray : [aromatherapyDataArray];

    // Convert each aromatherapy item to department structure
    return dataArray.map(aromatherapyData => {
        // Extract the main aromatherapy link
        const mainLink = aromatherapyData?.link;

        // Create the main department structure
        const departmentStructure = {
            url: mainLink?.href?.replace('https://', ''),
            name: aromatherapyData.label,
            sub_departments: []
        };

        // Convert each subnav to a sub_department
        aromatherapyData?.subnavs?.forEach(subnav => {
            const subDepartment = {
                url: subnav?.link?.href.replace('https://', ''),
                name: subnav.title,
                families: []
            };

            // Convert each link in the subnav to a family
            subnav?.links.forEach(link => {
                const family = {
                    url: link?.link?.href.replace('https://', ''),
                    name: link?.label
                };
                subDepartment.families.push(family);
            });

            departmentStructure.sub_departments.push(subDepartment);
        });

        return departmentStructure ?? [];
    });
}


const mergedProductCategories = ref([]); // Create a reactive ref to hold the new value

watch(
    () => props.sidebar,
    (newValue) => {
        if (newValue) {
            const converted = convertToDepartmentStructure(newValue?.sidebar?.data?.fieldValue.navigation);
            mergedProductCategories.value = [...converted];
            // console.log(newValue.sidebar);
        } else {
            mergedProductCategories.value = []; // Handle the case where the data is null or undefined
        }
    },
    { immediate: true } // Add options for immediate and deep watching
);

</script>

<template>
    <!-- <pre>{{ sidebar.sidebar }}</pre> -->
    <div class="block md:hidden p-3">
        <div class="flex justify-between items-center">
            <!-- Section: Hamburger mobile -->
            <MobileMenu :header="headerData" :menu="menuData" :productCategories="mergedProductCategories" />

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
