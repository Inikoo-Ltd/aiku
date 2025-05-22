<script setup lang="ts">
import Drawer from 'primevue/drawer';
import { ref, inject } from 'vue';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue';
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faSignIn, faSignOut, faTimesCircle } from '@fas';
import { library } from '@fortawesome/fontawesome-svg-core'
import { faBars, faChevronCircleDown } from '@fal';
import { getStyles } from "@/Composables/styles";
import { faGalaxy, faUserCircle } from "@fas";
import { faBaby, faCactus, faObjectGroup, faUser, faHouse, faTruck, faTag, faPhone, faUserCircle as falUserCircle } from "@fal";
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

library.add(
    faTimesCircle, faUser, faCactus, faBaby, faObjectGroup, faGalaxy, faLambda, faBackpack, faHouse, faTruck, faTag, faPhone,
    faTruckLoading, faTruckMoving, faTruckContainer, faUserRegular, faWarehouse, faWarehouseAlt, faShippingFast, faInventory, faUserCircle,
    faDollyFlatbedAlt, faBoxes, faShoppingCart, faBadgePercent, faChevronRight, faCaretRight, faPhoneAlt, faGlobe, faPercent, faPoundSign, faClock, falUserCircle
);

const props = defineProps<{
    header: { logo?: { image: { source: string } } },
    menu?: { data: Array<{ type: string, label: string, subnavs?: Array<{ title: string, link: { href: string, target: string }, links: Array<{ label: string, link: { href: string, target: string } }> }>, link?: { href: string, target: string } }> }
}>();

const visible = ref(false);
const isLoggedIn = inject('isPreviewLoggedIn', false)
const onLogout = inject('onLogout')
</script>

<template>
    <div>
        <button @click="visible = true">
            <FontAwesomeIcon :icon="header?.mobile?.menu?.icon || faBars"
                :style="getStyles(header?.mobile?.menu?.container?.properties)" />
        </button>

        <Drawer v-model:visible="visible" :header="''">
            <template #closeicon>
                <FontAwesomeIcon :icon="faTimesCircle" @click="visible = false" class="text-sm" />
            </template>

            <template #header>
                <img :src="header?.logo?.image?.source?.original" :alt="header?.logo?.alt" class="h-16" />
            </template>

            <div class="menu-container">
                <div class="menu-content">
                    <div v-for="(item, index) in props.menu" :key="index">
                        <!-- MULTIPLE TYPE WITH DROPDOWN -->
                        <Disclosure v-if="item.type === 'multiple'" v-slot="{ open }">
                            <DisclosureButton class="w-full text-left p-4 font-semibold text-gray-600 border-b">
                                <div class="flex justify-between items-center text-lg">
                                    <span>{{ item.label }}</span>
                                    <FontAwesomeIcon :icon="faChevronCircleDown"
                                        :class="{ 'rotate-180': open, 'transition-transform duration-300': true }" />
                                </div>
                            </DisclosureButton>

                            <DisclosurePanel class="disclosure-panel">
                                <div v-for="(submenu, subIndex) in item.subnavs" :key="subIndex" class="mb-6">
                                    <a v-if="submenu.title" :href="submenu.link?.href" :target="submenu.link?.target"
                                        class="block text-base font-bold text-gray-700 mb-2">
                                        {{ submenu.title }}
                                    </a>

                                    <div v-if="submenu.links" class="space-y-2 mt-2 ml-4 pl-4  border-gray-200">
                                        <a v-for="(menu, menuIndex) in submenu.links" :key="menuIndex"
                                            :href="menu.link?.href" :target="menu.link?.target"
                                            class="block text-sm text-gray-700 relative hover:text-primary transition-all">
                                            <span class="absolute left-0 -ml-4">–</span>
                                            {{ menu.label }}
                                        </a>
                                    </div>

                                </div>
                            </DisclosurePanel>
                        </Disclosure>

                        <!-- SINGLE LINK -->
                        <div v-else class="py-4 px-5 border-b">
                            <a :href="item.link?.href" :target="item.link?.target"
                                class="font-bold text-gray-600 text-lg">
                                {{ item.label }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="login-section">
                    <a v-if="!isLoggedIn" href="/app" class="font-bold text-gray-500">
                        <FontAwesomeIcon :icon="faSignIn" class="mr-3" /> Login
                    </a>
                    <div v-else @click="onLogout()" class="font-bold text-red-500 cursor-pointer">
                        <FontAwesomeIcon :icon="faSignOut" class="mr-3" /> Log Out
                    </div>
                </div>
            </div>
        </Drawer>
    </div>
</template>

<style scoped>
.menu-container {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.menu-content {
    flex: 1;
    overflow-y: auto;
    padding-bottom: 1rem;
}

.login-section {
    flex-shrink: 0;
    padding: 1rem 1.25rem;
    border-top: 1px solid #e5e5e5;
    display: flex;
    align-items: center;
    justify-content: flex-start;
}

.disclosure-panel {
    background-color: #f9fafb;
    padding: 1rem 1.25rem;
}

.disclosure-panel a {
    display: block;
    transition: all 0.2s ease-in-out;
}

.disclosure-panel a:hover {
    text-decoration: underline;
    color: #3b82f6;
}
</style>
