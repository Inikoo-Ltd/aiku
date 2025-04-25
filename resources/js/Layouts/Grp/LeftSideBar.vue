<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 03 Mar 2023 13:49:56 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import LeftSidebarNavigation from "@/Layouts/Grp/LeftSidebarNavigation.vue"
import LeftSidebarBottomNav from "@/Layouts/Grp/LeftSidebarBottomNav.vue"
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import NavigationSimple from '@/Layouts/Grp/NavigationSimple.vue'
import { useLogoutAuth } from "@/Composables/useAppMethod"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronLeft } from "@far"
import { faSignOutAlt,faSensor } from "@fal"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { inject, ref } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import Button from "@/Components/Elements/Buttons/Button.vue"
library.add(faChevronLeft, faSignOutAlt,faSensor)

const layout = inject('layout', layoutStructure)

// Set the LeftSidebar value to local storage
const handleToggleLeftBar = () => {
    if (typeof window !== "undefined") {
        localStorage.setItem('leftSideBar', (!layout.leftSidebar.show).toString())
    }
    layout.leftSidebar.show = !layout.leftSidebar.show
}

const logoutData = {
    label: 'Logout',
    tooltip: 'Logout the app',
    icon: 'fal fa-sign-out-alt',
}

const isLoadingLogout = ref(false)
const onLogoutAuth = () => {
    useLogoutAuth(layout.user, {
        onStart: () => isLoadingLogout.value = true,
        onError: () => isLoadingLogout.value = false,
    })
}

</script>

<template>
    <div class="pb-32 lg:pb-40 fixed md:flex md:flex-col md:inset-y-0 h-full transition-all duration-300 ease-in-out"
        :style="{
            'background-color': layout.app.theme[0],
            'color': layout.app.theme[2]
        }"
        :class="[
            layout.leftSidebar.show ? 'w-8/12 md:w-48' : 'w-8/12 md:w-12',
            layout.app.environment === 'staging' ? 'mt-11 lg:mt-16' : 'mt-11 lg:mt-10'
        ]"
        id="leftSidebar"
    >
        <!-- Toggle: collapse-expand LeftSideBar -->
        <div @click="handleToggleLeftBar"
            class="hidden absolute z-10 right-0 top-2/4 -translate-y-full translate-x-1/2 w-5 aspect-square border border-gray-300 rounded-full md:flex md:justify-center md:items-center cursor-pointer"
            :title="layout.leftSidebar.show ? 'Collapse the bar' : 'Expand the bar'"
            :style="{
                'background-color':  `color-mix(in srgb, ${layout.app.theme[0]} 85%, black)`,
                'color': layout.app.theme[1]
            }"
        >
            <div class="flex items-center justify-center transition-all duration-300 ease-in-out"
                :class="{'rotate-180': !layout.leftSidebar.show}"
            >
                <FontAwesomeIcon icon='far fa-chevron-left' class='h-[10px] leading-none' aria-hidden='true'
                    :class="layout.leftSidebar.show ? '-translate-x-[1px]' : ''"
                />
            </div>
        </div>

        <div class="flex flex-grow flex-col h-full overflow-y-auto custom-hide-scrollbar pb-3">
            <LeftSidebarNavigation />
        </div>

        <!-- Section: LogoutRetina -->
        <div class="absolute bottom-20 w-full">
            <div class="flex justify-center">
                <Popover class="relative w-full " v-slot="{ open }">
                    <PopoverButton class="flex w-full focus:outline-none focus:ring-0 focus:border-none px-2">
                        <div class="w-full rounded-md" :class="[open ? 'bg-black/25' : '']">
                            <NavigationSimple :nav="logoutData" />
                        </div>
                    </PopoverButton>

                    <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95" >
                        <PopoverPanel class="absolute -top-3 left-1/2 -translate-y-full bg-white rounded-md px-4 py-3 border border-gray-200 shadow">
                            <div class="min-w-32 flex flex-col justify-center gap-y-2">
                                <div class="whitespace-nowrap text-gray-500 text-xs">Are you sure want to logout?</div>
                                <div class="mx-auto">
                                    <Button @click="onLogoutAuth()" :loading="isLoadingLogout" label="Yes, Logout" type="red" />
                                </div>
                            </div>
                        </PopoverPanel>
                    </transition>
                </Popover>
            </div>
        </div>

        <div v-if="false" class="absolute bottom-[68px] w-full">
            <LeftSidebarBottomNav />
        </div>
    </div>
</template>

<style>
/* Hide scrollbar for Chrome, Safari and Opera */
.custom-hide-scrollbar::-webkit-scrollbar {
  display: none;
}

/* Hide scrollbar for IE, Edge and Firefox */
.custom-hide-scrollbar {
  -ms-overflow-style: none; /* IE and Edge */
  scrollbar-width: none; /* Firefox */
}</style>
