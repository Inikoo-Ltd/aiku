<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 03 Mar 2023 13:49:56 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import PupilLeftSidebarNavigation from "@/Layouts/Pupil/PupilLeftSidebarNavigation.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronLeft } from "@far"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { inject } from "vue"
library.add(faChevronLeft)

const layout = inject('layout')

// Set LeftSidebar value to local storage
const handleToggleLeftBar = () => {
    localStorage.setItem('leftSideBar', (!layout.leftSidebar.show).toString())
    layout.leftSidebar.show = !layout.leftSidebar.show
}

const isStaging = layout.app.environment === 'staging'



</script>

<template>
    <div class="pb-20 px-2 fixed md:flex md:flex-col md:inset-y-0 h-full transition-all"
        :class="[
            layout.leftSidebar.show ? 'w-8/12 md:w-48' : 'w-8/12 md:w-16',
            isStaging ? 'mt-9 lg:mt-12 pt-7' : 'mt-9 lg:mt-10 pt-3'
        ]"
        :style="{
            'background-color': layout.app.theme[0] + '00',
            'color': layout.app.theme[1]
        }"
        id="pupil_left_sidebar"
    >
            <!-- Toggle: collapse-expand LeftSideBar -->
            <div @click="handleToggleLeftBar"
                class="hidden absolute z-10 right-0 top-2/4 -translate-y-full translate-x-1/4 w-5 aspect-square border border-gray-300 rounded-full md:flex md:justify-center md:items-center cursor-pointer"
                :title="layout.leftSidebar.show ? 'Collapse the bar' : 'Expand the bar'" :style="{
                    'background-color': layout.app.theme[2],
                    'color': layout.app.theme[3]
                }">
                <div class="flex items-center justify-center transition-all duration-300 ease-in-out"
                    :class="{ 'rotate-180': !layout.leftSidebar.show }">
                    <FontAwesomeIcon icon='far fa-chevron-left' class='h-[10px] leading-none' aria-hidden='true'
                        :class="layout.leftSidebar.show ? '-translate-x-[1px]' : ''" />
                </div>
            </div>

            <div class="shadow rounded-md flex flex-grow flex-col h-full overflow-y-auto custom-hide-scrollbar pb-4"
                :style="{
                    'background-color': layout.app.theme[0],
                    'color': layout.app.theme[1]
                }"
            >
                <PupilLeftSidebarNavigation />
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
    -ms-overflow-style: none;
    /* IE and Edge */
    scrollbar-width: none;
    /* Firefox */
}
</style>
