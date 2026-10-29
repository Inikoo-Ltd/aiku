<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { useLayoutStore } from "@/Stores/layout";
import { capitalize } from "@/Composables/capitalize";
import { FontAwesomeIcon, FontAwesomeLayers } from "@fortawesome/vue-fontawesome";
import { faClock as falClock, faPencil as falPencil } from "@fal";
import { faDotCircle } from "@fas";
import { faPallet, faUsers, faMapSigns, faTruckCouch, faSignOut, faSitemap, faUserFriends, faColumns, faChartLine, faBoxOpen, faHistory, faBarcode, faTags, faChartNetwork, faHandHoldingUsd, faMoneyCheckAlt, faFileInvoice, faComments, faPersonDolly, faBoxUsd, faPeopleCarry, faTasks, faClipboardList, faAnalytics, faWarehouseAlt, faIndustryAlt, faInboxIn, faClipboardCheck, faCogs } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { SubSection } from "@/types/Navigation";
import { ref } from "vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(falClock, falPencil, faDotCircle, faPallet, faUsers, faMapSigns, faTruckCouch, faSignOut, faSitemap, faUserFriends, faColumns, faChartLine, faBoxOpen, faHistory, faBarcode, faTags, faChartNetwork, faHandHoldingUsd, faMoneyCheckAlt, faFileInvoice, faComments, faPersonDolly, faBoxUsd, faPeopleCarry, faTasks, faClipboardList, faAnalytics, faWarehouseAlt, faIndustryAlt, faInboxIn, faClipboardCheck, faCogs);

const layoutStore = useLayoutStore();

const props = defineProps<{
    subSections: SubSection[]
}>();

const isSubSectionActive = (subSection: SubSection) => {
    return [subSection.root, ...(subSection.roots ?? [])]
        .filter(Boolean)
        .some((root) => (layoutStore.currentRoute).includes(root));
};

const isLoading = ref<string | boolean>(false)

const getTooltip = (subSection: SubSection) => {
    if (!subSection.tooltip || subSection.tooltip === subSection.label) {
        return false
    }

    return capitalize(subSection.tooltip)
}
</script>

<template>
    <template v-for="(subSection, idxSubSec) in subSections">
        <component
            v-if="subSection"
            :is="subSection.route?.name ? Link : 'div'"
            :href="subSection.route?.name ? route(subSection.route.name, subSection.route.parameters) : '#'"
            class="group relative text-gray-700 text-sm flex justify-end items-center cursor-pointer py-3 gap-x-2 px-3 md:px-4 lg:px-4 outline-none focus-visible:bg-gray-100"
            :class="[]"
            v-tooltip="getTooltip(subSection)"
            @start="() => isLoading = 'subSection' + idxSubSec"
            @finish="() => isLoading = false"
        >
            <div :class="[
                isSubSectionActive(subSection)
                    ? 'bottomNavigationActive'
                    : 'bottomNavigation'
            ]" />
            <!-- {{ route(subSection.route.name, subSection.route.parameters) }} -->
            <!-- <FontAwesomeIcon :icon="subSection.icon" fixed-width class="h-5 lg:h-3.5 w-auto group-hover:opacity-100 opacity-70 transition duration-100 ease-in-out" aria-hidden="true" /> -->
            <LoadingIcon v-if="isLoading === 'subSection' + idxSubSec" class="h-5 lg:h-3.5 w-auto " />
            <FontAwesomeLayers v-else-if="subSection.icon_layers" class="inline-block shrink-0 h-5 w-5 lg:h-4 lg:w-4 mr-1 text-base lg:text-sm group-hover:opacity-100 opacity-70" aria-hidden="true">
                <FontAwesomeIcon :icon="subSection.icon_layers[0]" transform="shrink-2 left-2 up-1" />
                <FontAwesomeIcon :icon="subSection.icon_layers[1]" transform="shrink-8 right-5 down-5" />
            </FontAwesomeLayers>
            <FontAwesomeIcon v-else-if="subSection.icon" :icon="subSection.icon" fixed-width class="h-5 lg:h-3.5 w-auto group-hover:opacity-100 opacity-70" aria-hidden="true" />
            <FontAwesomeIcon v-else icon="fas fa-dot-circle" fixed-width class="h-5 lg:h-3.5" aria-hidden="true" />
            <span v-if="subSection.label" class="hidden lg:inline whitespace-nowrap">{{ subSection.label }}</span>
        </component>
    </template>
</template>
