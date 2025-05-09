<script setup lang="ts">
import { usePage } from "@inertiajs/vue3"
import { useLayoutStore } from "@/Stores/retinaLayout"

import { ref, provide } from 'vue'
import { useLocaleStore } from "@/Stores/locale"
import { useColorTheme } from "@/Composables/useStockList"
import { isArray } from "lodash"

import IrisHeader from '@/Layouts/Iris/Header.vue'
import IrisFooter from '@/Layouts/Iris/Footer.vue'
import { initialiseRetinaApp } from "@/Composables/initialiseRetinaApp"
initialiseRetinaApp()

provide('layout', useLayoutStore())
provide('locale', useLocaleStore())

const irisTheme = usePage().props?.iris?.theme ? usePage().props?.iris?.theme : { color: [...useColorTheme[2]] }

const layout = useLayoutStore()
const sidebarOpen = ref(false)

const isStaging = layout.app.environment === 'staging'
</script>

<template>
    <div class="-z-[1] fixed inset-0 bg-slate-100" />
    <div class="isolate relative min-h-full transition-all"
    :class="[Object.values(layout.rightSidebar).some(value => value.show) ? 'mr-44' : 'mr-0']">
        <IrisHeader
            v-if="layout.iris?.header?.header"
            :data="layout.iris?.header"
            :colorThemed="irisTheme"
            :menu="layout.iris?.menu"
        />
        
        <!-- Sidebar: Left -->
        <!-- <div class="">
            <div @click="sidebarOpen = !sidebarOpen" class="bg-gray-200/80 fixed top-0 w-screen h-screen z-10 md:hidden" v-if="sidebarOpen" />
            <RetinaLeftSideBar class="-left-2/3 transition-all z-20 block md:left-[0]"
                :class="[
                    { 'left-[0]': sidebarOpen },
                ]" @click="sidebarOpen = !sidebarOpen" />
        </div> -->

        <!-- Main Content -->
        <main
            class="max-w-7xl w-full mx-auto h-full my-10 transition-all px-8 lg:px-0"
            :xxclass="[
                isStaging ? 'pt-14 md:pt-[75px]' : ' pt-14 md:pt-[52px]',
            ]"
        >
            <div class="bg-white shadow-lg rounded-md h-full relative flex flex-col pb-6 text-gray-700">
                <!-- Section: Subsections (Something will teleport to this section) -->
                <div id="RetinaTopBarSubsections" class="pl-2 flex gap-x-2 h-full" />
                
                <slot name="default" />
            </div>
        </main>

    </div>

    <IrisFooter v-if="layout.iris?.footer && !isArray(layout.iris?.footer)" :data="layout.iris?.footer" :colorThemed="irisTheme" />
</template>

<style lang="scss" scoped>
:deep(.topbarNavigationActive) {
    @apply transition-all duration-100 rounded-md py-1.5 pl-2 pr-3;
    background-color: v-bind('layout.app.theme[4]');
    color: v-bind('layout.app.theme[5]');

}

:deep(.topbarNavigation) {
    @apply transition-all duration-100 rounded-md py-1.5 pl-2 pr-3;
    &:hover {
        background-color: v-bind('layout.app.theme[4] + "25"');
    }
}

#RetinaTopBarSubsections:has(> *) {
    @apply pb-2;
}
</style>