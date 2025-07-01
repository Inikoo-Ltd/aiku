<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 11 Aug 2022 11:08:49 Malaysia Time, Kuala Lumpur, Malaysia
  -  Reformatted: Fri, 03 Mar 2023 12:40:58 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Inikoo
  -  Version 4.0
  -->


<script setup lang="ts">
import { ref, provide, defineAsyncComponent, watch } from "vue";
import { initialiseApp } from "@/Composables/initialiseApp";
import { usePage } from "@inertiajs/vue3";
import Footer from "@/Components/Footer/Footer.vue";

import { useLayoutStore } from "@/Stores/layout";
import { useLocaleStore } from "@/Stores/locale";

import "@/Composables/Icon/NavigationImportIcon";


import TopBar from "@/Layouts/Grp/TopBar.vue";
import LeftSideBar from "@/Layouts/Grp/LeftSideBar.vue";
import RightSideBar from "@/Layouts/Grp/RightSideBar.vue";
import Breadcrumbs from "@/Components/Navigation/Breadcrumbs.vue";
import Notification from "@/Components/Utils/Notification.vue";
import {
    faPoll,
    faParking,
    faUsers,
    faTachometerAltFast,
    faGlobe,
    faParachuteBox,
    faClock,
    faTransporter,
    faRulerTriangle,
    faRulerCombined,
    faAtom,
    faFileInvoice,
    faPaperPlane,
    faDraftingCompass,
    faCheck,
    faTimes,
    faAsterisk,faPauseCircle, faSunset, faSkull, faSkullCow, faToggleOn, faBroadcastTower, faEye, faEyeSlash
} from "@fal";
import { faSearch, faBell } from "@far";
import { faAsterisk as fasAsterisk, faExclamation, faInfo, faPlay, faGlobe as fasGlobe } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { notify } from "@kyvg/vue3-notification";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { trans } from "laravel-vue-i18n";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Modal from "@/Components/Utils/Modal.vue";

library.add(faPoll, faPauseCircle, faSunset, faSkull, faSkullCow, faToggleOn, faBroadcastTower, faEye, faEyeSlash, fasAsterisk, faExclamation, faInfo, faPlay, fasGlobe, faUsers, faSearch, faBell, faTachometerAltFast, faGlobe, faParachuteBox, faClock, faTransporter, faParking, faRulerTriangle, faRulerCombined, faAtom, faFileInvoice, faPaperPlane, faDraftingCompass, faTimes, faCheck, faAsterisk);

provide("layout", useLayoutStore());
provide("locale", useLocaleStore());
provide("isMovePallet", true);

initialiseApp();

const StackedComponents = defineAsyncComponent(() => import("@/Layouts/Grp/StackedComponents.vue"));


const layout = useLayoutStore();
const sidebarOpen = ref(false);

// Section: Notification
watch(() => usePage().props?.flash?.notification, (notif) => {
    console.log("notif ret", notif);
    if (!notif) return;

    notify({
        title: notif.title,
        text: notif.description,
        type: notif.status
    });
});

// Section: Modal
interface Modal {
    title: string;
    description: string;
    type: "success" | "error" | "info" | "warning";
}

const selectedModal = ref<Modal | null>(null);
const isModalOpen = ref(false);
watch(() => usePage().props?.flash?.modal, (modal: Modal) => {
    console.log("modal ret", modal);
    if (!modal) return;

    selectedModal.value = modal;
    isModalOpen.value = true;
});
</script>

<template>
    <div id="grp_app" class="bg-white relative min-h-screen transition-all duration-200 ease-in-out"
         :class="[Object.values(layout.rightSidebar).some(value => value.show) ? 'mr-44' : 'mr-0']">

        <TopBar @sidebarOpen="(value: boolean) => sidebarOpen = value" :sidebarOpen="sidebarOpen"
                :logoRoute="`grp.dashboard.show`" urlPrefix="grp." />

        <!-- Section: Breadcrumbs -->
        <Breadcrumbs
            class="bg-white fixed z-[19] transition-all duration-200 ease-in-out"
            :class="[
                layout.leftSidebar.show ? 'left-0 md:left-48 w-[calc(100%-192px)]' : 'left-0 md:left-12 w-[calc(100%-48px)]',
                layout.app.environment === 'staging' ? 'top-11 lg:top-16' : 'top-11 lg:top-10'
            ]"
            :breadcrumbs="usePage().props.breadcrumbs ?? []" :navigation="usePage().props.navigation ?? []"
            :layout="layout"
        />

        <!-- Sidebar: Left -->
        <div class="">
            <!-- Mobile Helper: background to close hamburger -->
            <div class="bg-gray-200/80 fixed top-0 w-screen h-screen z-10 md:hidden" v-if="sidebarOpen"
                 @click="sidebarOpen = !sidebarOpen" />
            <LeftSideBar class="-left-2/3 z-20 block md:left-[0]"
                         :class="[{ 'left-[0]': sidebarOpen }]" @click="sidebarOpen = !sidebarOpen" />
        </div>

        <!-- Main Content -->
        <main
            class="h-full relative flex flex-col pt-[76px] md:pt-[70px] lg:pt-16 pb-6 text-gray-700 transition-all duration-200 ease-in-out"
            :class="[
                layout.leftSidebar.show ? 'ml-0 md:ml-48' : 'ml-0 md:ml-12',
                layout.app.environment === 'staging' ? 'mt-6' : ''
            ]">
            <slot />
        </main>

        <!-- Sidebar: Right -->
        <RightSideBar class="fixed top-16 w-44 transition-all duration-200 ease-in-out"
                      :class="[Object.values(layout.rightSidebar).some(value => value.show) ? 'right-0' : '-right-44']" />

        <Teleport to="body">
            <Transition name="stacked-component">
                <StackedComponents v-if="layout.stackedComponents?.length" />
            </Transition>
        </Teleport>

    </div>


    <Footer />

    <Modal :isOpen="isModalOpen" @onClose="isModalOpen = false" width="w-full max-w-lg">
        <div class="flex min-h-full items-end justify-center text-center sm:items-center px-2 py-3">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left transition-all w-full">
                <div>
                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-gray-100">
                        <FontAwesomeIcon v-if="selectedModal?.status == 'error'" icon="fal fa-times" class="text-red-500 text-2xl" fixed-width aria-hidden="true" />
                        <FontAwesomeIcon v-if="selectedModal?.status == 'success'" icon="fal fa-check" class="text-green-500 text-2xl" fixed-width aria-hidden="true" />
                        <FontAwesomeIcon v-if="selectedModal?.status == 'warning'" icon="fas fa-exclamation" class="text-orange-500 text-2xl" fixed aria-hidden="true" />
                        <FontAwesomeIcon v-if="selectedModal?.status == 'info'" icon="fas fa-info" class="text-gray-500 text-2xl" fixed-width aria-hidden="true" />
                    </div>

                    <div class="mt-3 text-center sm:mt-5">
                        <div as="h3" class="font-semibold text-2xl">
                            {{ selectedModal?.title }}
                        </div>
                        <div class="mt-2 text-sm text-gray-500">
                            {{ selectedModal?.description }}
                        </div>
                    </div>
                </div>
                <div class="mt-5 sm:mt-6">
                    <Button
                        @click="() => isModalOpen = false"
                        :label="trans('Ok, Get it')"
                        full
                    />
                </div>
            </div>
        </div>
    </Modal>

    <!-- Global declaration: Notification -->
    <notifications
        dangerously-set-inner-html
        :max="3"
        width="500"
        classes="custom-style-notification"
        :pauseOnHover="true"
    >
        <template #body="props">
            <Notification :notification="props" />
        </template>
    </notifications>
</template>

<style lang="scss">


/* Navigation: Aiku */
.navigationActive {
    @apply rounded py-2 font-semibold transition-all duration-0 ease-out;
    box-shadow: v-bind('`0 0 0 1px color-mix(in srgb, ${layout?.app?.theme[2]}, 20% white)`') !important;
    background-color: v-bind('layout?.app?.theme[2]');
    color: v-bind('layout?.app?.theme[3]')
}

.navigation {
    @apply hover:bg-gray-300/40 py-2 rounded font-semibold transition-all duration-0 ease-out;
    color: v-bind('layout?.app?.theme[1]')
}

.subNavActive {
    @apply bg-indigo-200/20 sm:border-l-4 sm:border-indigo-100 text-white font-semibold transition-all duration-0 ease-in-out;
}

.subNav {
    @apply hover:bg-white/80 text-gray-100 hover:text-indigo-500 font-semibold transition-all duration-0 ease-in-out
}

.navigationSecondActive {
    @apply transition-all duration-100 ease-in-out;
}

.navigationSecond {
    @apply hover:bg-gray-100 text-gray-400 hover:text-gray-500 transition-all duration-100 ease-in-out
}

.bottomNavigationActive {
    @apply w-5/6 absolute h-0.5 rounded-full bottom-0 left-[50%] translate-x-[-50%] mx-auto transition-all duration-200 ease-in-out;
    background-color: v-bind('layout.app.theme[4]');
}

.bottomNavigation {
    @apply bg-gray-300 w-0 group-hover:w-3/6 absolute h-0.5 rounded-full bottom-0 left-[50%] translate-x-[-50%] mx-auto transition-all duration-200 ease-in-out
}

.bottomNavigationSecondaryActive {
    @apply w-5/6 bg-gray-400 absolute h-0.5 rounded-full bottom-0 left-[50%] translate-x-[-50%] mx-auto transition-all duration-200 ease-in-out;

}

.bottomNavigationSecondary {
    @apply bg-gray-200 w-0 group-hover:w-3/6 absolute h-0.5 rounded-full bottom-0 left-[50%] translate-x-[-50%] mx-auto transition-all duration-200 ease-in-out
}

.primaryLink {
    background: v-bind('`linear-gradient(to top, ${layout.app.theme[6]}, ${layout.app.theme[6] + "77"})`');

    &:hover, &:focus {
        color: v-bind('`${layout.app.theme[7]}`');
    }

    @apply focus:ring-0 focus:outline-none focus:border-none
    bg-no-repeat [background-position:0%_100%]
    transition-all
    [background-size:100%_0.2em]
    motion-safe:transition-all motion-safe:duration-200
    hover:[background-size:100%_100%]
    focus:[background-size:100%_100%] px-1 py-0.5
}

.secondaryLink {
    background: v-bind('`linear-gradient(to top, ${layout.app.theme[6] + "77"}, ${layout.app.theme[6] + "11"})`');

    &:hover, &:focus {
        color: v-bind('`${layout.app.theme[7]}`');
    }

    @apply focus:ring-0 focus:outline-none focus:border-none
    bg-no-repeat [background-position:0%_100%]
    [background-size:100%_0.2em]
    motion-safe:transition-all motion-safe:duration-200
    hover:[background-size:100%_100%]
    focus:[background-size:100%_100%] px-1 py-0.5
}


// For icon box in FlatTreemap
.specialBoxActive {
    background: v-bind('`linear-gradient(to top, ${layout.app.theme[0]}, ${layout.app.theme[0] + "AA"})`');
    color: v-bind('`${layout.app.theme[1]}`');
    border: v-bind('`2px solid ${layout.app.theme[0] + "99"}`') !important;

    @apply rounded overflow-hidden
    cursor-pointer
    focus:ring-0 focus:outline-none
    bg-no-repeat [background-position:0%_100%]
    motion-safe:transition-all motion-safe:duration-100
    [background-size:100%_100%]
    focus:[background-size:100%_100%] px-1;
}

.specialBox {
    background: v-bind('`linear-gradient(to top, ${layout.app.theme[0]}, ${layout.app.theme[0] + "AA"})`');
    color: v-bind('`${layout.app.theme[0]}`');
    border: v-bind('`2px solid ${layout.app.theme[0] + "99"}`') !important;

    &:hover, &:focus {
        color: v-bind('`${layout.app.theme[1]}`');
    }

    @apply rounded overflow-hidden
    cursor-pointer
    focus:ring-0 focus:outline-none
    bg-no-repeat [background-position:0%_100%]
    [background-size:100%_0em]
    motion-safe:transition-all motion-safe:duration-100
    hover:[background-size:100%_100%]
    focus:[background-size:100%_100%] px-1;
}
</style>
