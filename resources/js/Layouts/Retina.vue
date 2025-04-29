<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 18 Feb 2024 06:25:44 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { usePage } from "@inertiajs/vue3"
import RetinaFooter from "@/Layouts/Retina/RetinaFooter.vue"
import RetinaLeftSideBar from "@/Layouts/Retina/RetinaLeftSideBar.vue"
import RetinaRightSideBar from "@/Layouts/Retina/RetinaRightSideBar.vue"
import RetinaTopBar from "@/Layouts/Retina/RetinaTopBar.vue"
import Breadcrumbs from "@/Components/Navigation/Breadcrumbs.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { initialiseRetinaApp } from "@/Composables/initialiseRetinaApp"
import { useLayoutStore } from "@/Stores/retinaLayout"
import Notification from '@/Components/Utils/Notification.vue'

import { faNarwhal, faHome, faBars, faUsersCog, faTachometerAltFast, faUser, faLanguage, faParachuteBox, faCube, faBallot, faConciergeBell, faGarage, faAlignJustify, faShippingFast, faPaperPlane, faTasks } from '@fal'
import { faSearch, faBell } from '@far'
import { ref, provide } from 'vue'
import { useLocaleStore } from "@/Stores/locale"

library.add( faNarwhal, faHome, faBars, faUsersCog, faTachometerAltFast, faUser, faLanguage, faParachuteBox, faCube, faBallot, faConciergeBell, faGarage, faAlignJustify, faShippingFast, faPaperPlane, faTasks, faSearch, faBell )

// console.log('sss', useLayoutStore().app.theme)

provide('layout', useLayoutStore())
provide('locale', useLocaleStore())


initialiseRetinaApp()

const layout = useLayoutStore()
const sidebarOpen = ref(false)
console.log('environment:', layout.app.environment)

const isStaging = layout.app.environment === 'staging'

</script>

<template>
    <div class="fixed inset-0 bg-slate-100" />

    <div class="isolate relative min-h-full transition-all"
        :class="[Object.values(layout.rightSidebar).some(value => value.show) ? 'mr-44' : 'mr-0']">

        <RetinaTopBar @sidebarOpen="(value: boolean) => sidebarOpen = value" :sidebarOpen="sidebarOpen"
            logoRoute="retina.dashboard.show" />

        
        <!-- Sidebar: Left -->
        <div class="">
            <!-- Mobile Helper: background to close hamburger -->
            <div @click="sidebarOpen = !sidebarOpen" class="bg-gray-200/80 fixed top-0 w-screen h-screen z-10 md:hidden" v-if="sidebarOpen" />
            <RetinaLeftSideBar class="-left-2/3 transition-all z-20 block md:left-[0]"
                :class="[
                    { 'left-[0]': sidebarOpen },
                ]" @click="sidebarOpen = !sidebarOpen" />
        </div>

        <!-- Main Content -->
        <main class="h-screen pb-10 transition-all pl-2 md:pl-0 pr-2 "
            :class="[
                layout.leftSidebar.show ? 'ml-0 md:ml-48' : 'ml-0 md:ml-16',
                isStaging ? 'pt-14 md:pt-[75px]' : ' pt-14 md:pt-[52px]',
            ]"
        >
            <div class="bg-white shadow-lg rounded h-full overflow-y-auto relative flex flex-col pb-6 text-gray-700">
                <!-- Section: Breadcrumbs -->
                <div class="mt-1">
                    <!-- <Breadcrumbs v-if="usePage().props.breadcrumbs?.length > 0"
                        :breadcrumbs="usePage().props.breadcrumbs ?? []"
                        :navigation="usePage().props.navigation ?? []"
                    /> -->

                    <Breadcrumbs
                        class="bg-white w-full transition-all duration-200 ease-in-out"
                        :class="[
                            layout.leftSidebar.show ? 'left-0 md:left-48' : 'left-0 md:left-12',
                        ]"
                        :breadcrumbs="usePage().props.breadcrumbs ?? []" :navigation="usePage().props.navigation ?? []"
                        :layout="layout"    
                    />
                </div>
                        <slot />
                <!-- <transition name="slide-to-right" mode="out-in" appear>
                    <div :key="$page.url">
                    </div>
                </transition> -->
            </div>
        </main>

        <!-- Sidebar: Right -->
        <RetinaRightSideBar class="fixed top-[52px] w-[170px] transition-all"
            :class="[Object.values(layout.rightSidebar).some(value => value.show) ? 'right-2' : '-right-[170px]']" />

    </div>

    <RetinaFooter />

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
// * {
//     --color-primary: v-bind('layout.app.theme[0]');
// }

/* Navigation: Aiku */
.navigationActive {
    @apply rounded py-2 font-semibold transition-all duration-0 ease-out;
}
.navigation {
    @apply hover:bg-gray-300/40 py-2 rounded font-semibold transition-all duration-0 ease-out;
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
    @apply w-5/6 absolute h-0.5 rounded-full bottom-0 left-[50%] translate-x-[-50%] mx-auto transition-all;
    background-color: v-bind('layout.app.theme[4]');
}
.bottomNavigation {
    @apply bg-gray-300 w-0 group-hover:w-3/6 absolute h-0.5 rounded-full bottom-0 left-[50%] translate-x-[-50%] mx-auto transition-all
}

.primaryLink {
    background: v-bind('`linear-gradient(to top, ${layout.app.theme[2]}, ${layout.app.theme[2] + "AA"})`');
    &:hover, &:focus {
        color: v-bind('`${layout.app.theme[3]}`');
    }

    @apply focus:ring-0 focus:outline-none focus:border-none
    bg-no-repeat [background-position:0%_100%]
    [background-size:100%_0.2em]
    motion-safe:transition-all motion-safe:duration-200
    hover:[background-size:100%_100%]
    focus:[background-size:100%_100%] px-1 py-0.5
}

.secondaryLink {
    background: v-bind('`linear-gradient(to top, ${layout.app.theme[6]}, ${layout.app.theme[6] + "AA"})`');
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
.specialBox {
    background: v-bind('`linear-gradient(to top, ${layout.app.theme[0]}, ${layout.app.theme[0] + "AA"})`');
    color: v-bind('`${layout.app.theme[0]}`');
    border: v-bind('`4px solid ${layout.app.theme[0]}`');

    &:hover, &:focus {
        color: v-bind('`${layout.app.theme[1]}`');
    }

    @apply border-indigo-300 border-2 rounded-md
    cursor-pointer
    focus:ring-0 focus:outline-none focus:border-none
    bg-no-repeat [background-position:0%_100%]
    [background-size:100%_0em]
    motion-safe:transition-all motion-safe:duration-100
    hover:[background-size:100%_100%]
    focus:[background-size:100%_100%] px-1;
}
</style>