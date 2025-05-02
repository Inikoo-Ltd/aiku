<script setup lang="ts">
import { usePage } from "@inertiajs/vue3"
import { useLayoutStore } from "@/Stores/retinaLayout"
import { ref, provide } from 'vue'
import { useLocaleStore } from "@/Stores/locale"
import { useColorTheme } from "@/Composables/useStockList"
import { isArray } from "lodash"
import IrisHeader from '@/Layouts/Iris/Header.vue'
import IrisFooter from '@/Layouts/Iris/Footer.vue'
import RetinaDsLeftSidebar from "./Retina/RetinaDsLeftSidebar.vue"

provide('layout', useLayoutStore())
provide('locale', useLocaleStore())

const irisTheme = usePage().props?.iris?.theme ? usePage().props?.iris?.theme : { color: [...useColorTheme[2]] }

const layout = useLayoutStore()
const sidebarOpen = ref(false)


console.log('LayoutDs')
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
        <div class="">
            <div @click="sidebarOpen = !sidebarOpen" class="bg-gray-200/80 fixed top-0 w-screen h-screen z-10 md:hidden" v-if="sidebarOpen" />
            
        </div>

        <!-- Main Content -->
        <main
            class="relative max-w-7xl w-full mx-auto min-h-96 h-fit my-10 transition-all px-8 lg:px-0"
        >
            <RetinaDsLeftSidebar
                class="z-20 block right-full -translate-x-3 w-48 absolute pb-20 px-2 md:flex md:flex-col  transition-all"
                @click="sidebarOpen = !sidebarOpen"
            />

            <div class="min-h-full bg-white shadow-lg rounded-md h-full relative flex flex-col pb-6 text-gray-700">

                <slot name="default" />
            </div>
        </main>

    </div>

    <IrisFooter v-if="layout.iris?.footer && !isArray(layout.iris?.footer)" :data="layout.iris?.footer" :colorThemed="irisTheme" />
</template>