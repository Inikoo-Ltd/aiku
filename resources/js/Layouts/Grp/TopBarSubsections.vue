<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { useLayoutStore } from "@/Stores/layout";
import { capitalize } from "@/Composables/capitalize";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faDotCircle } from "@fas";
import { faPallet, faUsers, faMapSigns, faTruckCouch, faSignOut } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { SubSection } from "@/types/Navigation";
import { ref } from "vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faDotCircle, faPallet, faUsers, faMapSigns, faTruckCouch, faSignOut);

const layoutStore = useLayoutStore();

const props = defineProps<{
    subSections: SubSection[]
}>();

// Check current Route is had provided routeName
const isSubSectionActive = (routeName: string) => {
    if (!routeName) return false;

    return (layoutStore.currentRoute).includes(routeName);
};

const isLoading = ref<string | boolean>(false)
</script>

<template>
    <template v-for="(subSection, idxSubSec) in subSections">
        <component
            v-if="subSection"
            :is="subSection.route?.name ? Link : 'div'"
            :href="subSection.route?.name ? route(subSection.route.name, subSection.route.parameters) : '#'"
            class="group relative text-gray-700 group text-sm flex justify-end items-center cursor-pointer py-3 gap-x-2 px-3 md:px-4 lg:px-4"
            :class="[]"
            v-tooltip="capitalize(subSection.tooltip ?? subSection.label ?? '')"
            @start="() => isLoading = 'subSection' + idxSubSec"
            @finish="() => isLoading = false"
        >
            <div :class="[
                isSubSectionActive(subSection.root)
                    ? 'bottomNavigationActive'
                    : 'bottomNavigation'
            ]" />
            <!-- {{ route(subSection.route.name, subSection.route.parameters) }} -->
            <!-- <FontAwesomeIcon :icon="subSection.icon" fixed-width class="h-5 lg:h-3.5 w-auto group-hover:opacity-100 opacity-70 transition duration-100 ease-in-out" aria-hidden="true" /> -->
            <LoadingIcon v-if="isLoading === 'subSection' + idxSubSec" class="h-5 lg:h-3.5 w-auto " />
            <FontAwesomeIcon v-else-if="subSection.icon" :icon="subSection.icon" fixed-width class="h-5 lg:h-3.5 w-auto group-hover:opacity-100 opacity-70" aria-hidden="true" />
            <FontAwesomeIcon v-else icon="fas fa-dot-circle" fixed-width class="h-5 lg:h-3.5" aria-hidden="true" />
            <span v-if="subSection.label" class="hidden lg:inline whitespace-nowrap">{{ subSection.label }}</span>
        </component>
    </template>
</template>
