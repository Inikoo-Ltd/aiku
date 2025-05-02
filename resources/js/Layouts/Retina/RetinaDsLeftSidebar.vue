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
library.add(faChevronLeft)

const layout = useLayoutStore()

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
