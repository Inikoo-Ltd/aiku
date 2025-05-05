<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { Disclosure } from "@headlessui/vue"

import { layoutStructure } from "@/Composables/useLayoutStructure";

import { faChevronDown } from "@far"
import { faDotCircle } from "@fas"
import { faTerminal, faCog, faAbacus, faFolder, faFax,faUserCircle, faBarcode, faBallotCheck, faUsdCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

import MenuTopRight from "@/Layouts/Retina/MenuTopRight.vue"
import { inject } from "vue"
import ScreenWarning from "@/Components/Utils/ScreenWarning.vue"
import Image from "@/Components/Image.vue";

library.add(faChevronDown, faDotCircle, faTerminal, faCog, faAbacus, faFolder, faFax,faUserCircle, faBarcode, faBallotCheck, faUsdCircle)

defineProps<{
    sidebarOpen: boolean
    logoRoute: string
}>()

defineEmits<{
    (e: "sidebarOpen", value: boolean): void
}>()

const layout = inject('layout')



const layoutStore = inject("layout", layoutStructure);
const logo = layout?.iris?.header?.header?.data?.fieldValue?.logo
console.log(layout,layoutStore)
const isStaging = layout.app.environment === 'staging'
console.log('environment', isStaging)

</script>

<template>
    <Disclosure as="nav" class="fixed z-[21] w-full " v-slot="{ open }" :style="{
        'color': layout.app.theme[2]
    }">
        <ScreenWarning v-if="isStaging" />

        <div class="mt-1 flex h-11 lg:h-10 flex-shrink-0 gap-x-2">
            <div class="flex">
                <!-- Mobile: Hamburger -->
                <button class="block md:hidden w-10 h-10 relative focus:outline-none"
                    @click="$emit('sidebarOpen', !sidebarOpen)">
                    <span class="sr-only">Open sidebar</span>
                    <div class="block w-5 absolute left-1/2 top-1/2   transform  -translate-x-1/2 -translate-y-1/2">
                        <span aria-hidden="true"
                            class="block absolute rounded-full h-0.5 w-5 bg-gray-900 transform transition duration-200 ease-in-out"
                            :class="{ 'rotate-45': sidebarOpen, ' -translate-y-1.5': !sidebarOpen }"></span>
                        <span aria-hidden="true"
                            class="block absolute rounded-full h-0.5 w-5 bg-gray-900 transform transition duration-100 ease-in-out"
                            :class="{ 'opacity-0': sidebarOpen }"></span>
                        <span aria-hidden="true"
                            class="block absolute rounded-full h-0.5 w-5 bg-gray-900 transform transition duration-200 ease-in-out"
                            :class="{ '-rotate-45': sidebarOpen, ' translate-y-1.5': !sidebarOpen }"></span>
                    </div>
                </button>

                <!-- App Title: Image and Title -->
                <div class="xml-3 w-12 h-11 flex flex-1 items-center justify-center md:justify-start transition-all duration-300 ease-in-out"
                    :class="[
                        layout.leftSidebar.show ? 'md:w-44' : 'md:w-12'
                    ]" :style="{
                        // 'background-color': layout.app.theme[0],
                        'color': layout.app.theme[2],
                        // 'border-bottom': `1px solid ${layout.app.theme[1]}3F`
                    }">
                    <!-- {{ layout.iris?.domain }} -->
                    <a v-if="!logo?.image?.source" :href="layout.iris?.website?.domain ? `https://${layout.iris?.website?.domain}/` : '#'"
                        target="_blank"
                        class="hidden md:flex flex-nowrap items-center h-full overflow-hidden gap-x-1.5 transition-all duration-200 ease-in-out"
                        :class="[
                             layout.leftSidebar.show ? 'py-1 pl-4' : 'pl-3 w-full'
                        ]">
                         <img src="/art/logo-yellow.svg" class="aspect-square h-5" />
                        <Transition name="slide-to-left">
                            <p v-if="layout.leftSidebar.show"
                                class="capitalize text-lg bg-clip-text font-bold whitespace-nowrap leading-none lg:truncate py-1"
                                :style="{ color: layout.app.theme[2] }">
                                {{ layout.website?.label }}
                            </p>
                        </Transition>
                    </a>

                    <a v-else :href="layout.iris?.website?.domain ? `https://${layout.iris?.website?.domain}/` : '#'"
                        target="_blank"
                        class="hidden md:flex flex-nowrap items-center h-full overflow-hidden gap-x-1.5 transition-all duration-200 ease-in-out"
                        :class="[
                             layout.leftSidebar.show ? 'py-1 pl-4' : 'pl-3 w-full'
                        ]">
                          <Image :src="logo?.image?.source" />
                    </a>
                </div>
            </div>

            <div class="flex items-center w-full justify-between pr-6 space-x-3">
                <!-- Section: Subsections (Something will teleport to this section) -->
                <div class="flex h-full" id="RetinaTopBarSubsections">
                </div>

                <!-- Section: Search, Notification, Profile -->
                <MenuTopRight />
            </div>
        </div>
    </Disclosure>
</template>
