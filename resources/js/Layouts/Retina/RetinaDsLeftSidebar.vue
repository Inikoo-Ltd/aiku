<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 03 Mar 2023 13:49:56 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import RetinaLeftSidebarNavigation from "@/Layouts/Retina/RetinaLeftSidebarNavigation.vue"
import { useLayoutStore } from "@/Stores/retinaLayout"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronLeft } from "@far"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
library.add(faChevronLeft)

const layout = useLayoutStore()

// Set the LeftSidebar value to local storage
const handleToggleLeftBar = () => {
    if (typeof window !== "undefined") {
        localStorage.setItem('leftSideBar', (!layout.leftSidebar.show).toString())
    }
    layout.leftSidebar.show = !layout.leftSidebar.show
}
</script>

<template>
    <div
        :style="{
            'background-color': layout.app.theme[0] + '00',
            'color': layout.app.theme[1]
        }"
        id="leftSidebar"
    >
        
        <div class="shadow rounded-md flex flex-grow flex-col h-full overflow-y-auto custom-hide-scrollbar pb-4"
            :style="{
                'background-color': layout.app.theme[0],
                'color': layout.app.theme[1]
            }"
        >
            <div @click="handleToggleLeftBar"
                class="hidden absolute z-10 right-1/2 bottom-0 xtop-2/4 translate-y-1/2 translate-x-1/2 w-6 aspect-square border border-gray-300 rounded-full md:flex md:justify-center md:items-center cursor-pointer"
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
            
            <RetinaLeftSidebarNavigation />
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
