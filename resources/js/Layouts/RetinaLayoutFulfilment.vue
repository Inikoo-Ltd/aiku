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

import { faNarwhal, faHome, faBars, faUsersCog, faTachometerAltFast, faUser, faLanguage, faParachuteBox, faCube, faBallot, faConciergeBell, faGarage, faAlignJustify, faShippingFast, faPaperPlane, faTasks } from '@fal'
import { faSearch, faBell } from '@far'
import { ref, provide } from 'vue'
import { useLocaleStore } from "@/Stores/locale"
// import { useColorTheme } from "@/Composables/useStockList"

library.add( faNarwhal, faHome, faBars, faUsersCog, faTachometerAltFast, faUser, faLanguage, faParachuteBox, faCube, faBallot, faConciergeBell, faGarage, faAlignJustify, faShippingFast, faPaperPlane, faTasks, faSearch, faBell )

// console.log('sss', useLayoutStore().app.theme)

provide('layout', useLayoutStore())
provide('locale', useLocaleStore())
initialiseRetinaApp()


// const irisTheme = usePage().props?.iris?.theme ? usePage().props?.iris?.theme : { color: [...useColorTheme[2]] }


const layout = useLayoutStore()
const sidebarOpen = ref(false)

const isStaging = layout.app.environment === 'staging'
</script>

<template>
    <div class="-z-[1] fixed inset-0 bg-slate-100" />
    <div class="isolate relative min-h-full transition-all"
        :class="[Object.values(layout.rightSidebar).some(value => value.show) ? 'mr-44' : 'mr-0']">
    
    
        <RetinaTopBar
            @sidebarOpen="(value: boolean) => sidebarOpen = value"
            :sidebarOpen="sidebarOpen"
            logoRoute="retina.dashboard.show"
        />
        
        <!-- Sidebar: Left -->
        <div class="">
            <!-- Mobile Helper: background to close hamburger -->
            <div @click="sidebarOpen = !sidebarOpen" class="bg-gray-200/80 fixed top-0 w-screen h-screen z-10 md:hidden" v-if="sidebarOpen" />
            <RetinaLeftSideBar
                class="-left-2/3 transition-all z-20 block md:left-[0]"
                :class="[
                    { 'left-[0]': sidebarOpen },
                ]" @click="sidebarOpen = !sidebarOpen" />
        </div>

        <!-- Main Content -->
        <main class="h-[calc(100vh-40px)] transition-all pl-2 pr-2"
            :class="[
                layout.leftSidebar.show ? 'ml-0 md:ml-64' : 'ml-0 md:ml-16',
                isStaging ? 'pt-14 md:pt-[75px]' : ' pt-14 md:pt-[52px]',
            ]"
        >
            <div class="bg-white shadow-lg rounded h-full overflow-y-auto relative flex flex-col pb-6 text-gray-700">
                <!-- Section: Breadcrumbs -->
                <div class="mt-1">
                    <Breadcrumbs
                        class="bg-white w-full transition-all duration-200 ease-in-out"
                        :class="[
                            layout.leftSidebar.show ? 'left-0 md:left-48' : 'left-0 md:left-12',
                        ]"
                        :breadcrumbs="usePage().props.breadcrumbs ?? []"
                        :navigation="usePage().props.navigation ?? []"
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
</template>

<style lang="scss" scoped>
:deep(.bottomNavigationActive) {
    @apply w-5/6 absolute h-0.5 rounded-full bottom-0 left-[50%] translate-x-[-50%] mx-auto transition-all;
    background-color: v-bind('layout.app.theme[4]');
}
:deep(.bottomNavigation) {
    @apply bg-gray-300 w-0 group-hover:w-3/6 absolute h-0.5 rounded-full bottom-0 left-[50%] translate-x-[-50%] mx-auto transition-all
}
</style>