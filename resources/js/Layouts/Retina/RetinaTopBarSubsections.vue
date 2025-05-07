<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { useLayoutStore } from "@/Stores/retinaLayout"
import { capitalize } from "@/Composables/capitalize"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faDotCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { SubSection } from "@/types/Navigation"
import {faPallet, faTruck, faTruckCouch, faTruckRamp} from "@fal";
import { ref } from "vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faDotCircle, faTruck, faPallet, faTruckRamp, faTruckCouch)

const layoutStore = useLayoutStore()

const props = defineProps<{
    subSections: SubSection[]
}>()

// Check current Route have provided routeName
const isSubSectionActive = (routeName: string) => {
    if(!routeName) return false

    return (layoutStore.currentRoute).includes(routeName)
}

const isLoading = ref<string | boolean>(false)
</script>

<template>
    <template v-for="(subSection, idxSubSec) in subSections">
        <component v-if="subSection"
            :is="subSection.route?.name ? Link : 'div'"
            :href="subSection.route?.name ? route(subSection.route.name, subSection.route.parameters) : '#'"
            class="group relative text-gray-700 group text-l font-bold flex justify-end items-center cursor-pointer py-3 gap-x-2 px-4 md:px-4 lg:px-4"
            :class="[
                isSubSectionActive(subSection.root)
                    ? 'topbarNavigationActive'
                    : 'topbarNavigation'
            ]"
            :title="capitalize(subSection.tooltip ?? subSection.label ?? '')"
            @start="() => isLoading = 'subSection' + idxSubSec"
            @finish="() => isLoading = false"
        >
            <div :class="[
                isSubSectionActive(subSection.root)
                    ? 'bottomNavigationActive'
                    : 'bottomNavigation'
            ]" />
            
            <LoadingIcon v-if="isLoading === 'subSection' + idxSubSec" class="h-5 lg:h-5 w-auto " />
            <FontAwesomeIcon v-else-if="subSection.icon" :icon="subSection.icon" fixed-width class="h-5 lg:h-5 w-auto group-hover:opacity-100 opacity-70 transition duration-100 ease-in-out" aria-hidden="true" />
            <FontAwesomeIcon v-else icon="fas fa-dot-circle" fixed-width class="h-5 lg:h-5" aria-hidden="true" />
            <span v-if="subSection.label" class="hidden lg:inline capitalize whitespace-nowrap">{{ subSection.label }}</span>
        </component>
    </template>
</template>
