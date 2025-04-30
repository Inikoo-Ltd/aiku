<script setup lang="ts">
import { useLayoutStore } from "@/Stores/retinaLayout";
import { Navigation } from "@/types/Navigation";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faRoute } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { Link } from "@inertiajs/vue3";
import { capitalize } from "@/Composables/capitalize";
import { isNavigationActive } from "@/Composables/useUrl";
import { onMounted, ref, onUnmounted } from "vue";
import RetinaTopBarSubsections from "@/Layouts/Retina/RetinaTopBarSubsections.vue";
import { faTachometerAlt, faFileInvoiceDollar, faHandHoldingBox, faPallet } from "@fal";
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faTachometerAlt, faFileInvoiceDollar, faRoute, faPallet, faHandHoldingBox);

const props = defineProps<{
    navKey: string | number  // shops_navigation | warehouses_navigation
    nav: Navigation
}>();


const layout = useLayoutStore()
const isTopMenuActive = ref(false)
const isLoading = ref(false)

onMounted(() => {
    isTopMenuActive.value = true;
    // console.log('NavigationSimple.vue', props.navKey, props.nav)
});

onUnmounted(() => {
    isTopMenuActive.value = false;
});

// Check if this route has nav.root
// const isRouteActive = () => {
//     return (layout.currentRoute).includes(props.nav.root || 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')  // 'aaaa' so it will return false
// }


</script>

<template>
    <!-- {{ layout.currentRoute }} -->
    <!-- <div class="text-xxs">{{ layout.currentRoute }} <br> {{ nav.route.name }}</div> -->
    <Link :href="nav?.route?.name ? route(nav.route?.name, nav?.route?.parameters) : '#'"
        class="group flex items-center px-2 text-sm gap-x-2" :class="[
            isNavigationActive(layout.currentRoute, props.nav.root)
                ? 'navigationActive'
                : 'navigation',
            layout.leftSidebar.show ? '' : '',
        ]"
        :style="[isNavigationActive(layout.currentRoute, props.nav.root) ? {
            'background-color': layout.app?.theme[1],
            'color': layout.app?.theme[2]
        } : {} ]"
        @start="() => isLoading = true"
        @finish="() => isLoading = false"
        :aria-current="navKey === layout.currentModule ? 'page' : undefined"
        v-tooltip="layout.leftSidebar.show ? false : capitalize(nav.label)"
    >
        <LoadingIcon v-if="isLoading" class="flex-shrink-0 h-4 w-4" />
        <FontAwesomeIcon v-else-if="nav.icon" aria-hidden="true" class="flex-shrink-0 h-4 w-4" fixed-width :icon="nav.icon" />
        <Transition name="slide-to-left">
            <span v-if="layout.leftSidebar.show" class="capitalize leading-none whitespace-nowrap block md:block"
                :class="[layout.leftSidebar.show ? '' : 'block md:hidden']">
                {{ nav.label }}
            </span>
            <span v-else class="capitalize leading-none whitespace-nowrap block md:hidden">
                {{ nav.label }}
            </span>
        </Transition>
    </Link>

    <!-- If this Navigation is active, then teleport the SubSections to #RetinaTopBarSubsections in <AppTopBar> -->
    <template v-if="isTopMenuActive && isNavigationActive(layout.currentRoute, props.nav.root || 'xx.xx.xx.xx')">
        <Teleport v-if="nav.topMenu?.subSections" to="#RetinaTopBarSubsections" :disabled="!isNavigationActive(layout.currentRoute, props.nav.root || 'xx.xx.xx.xx')">
            <RetinaTopBarSubsections
                :subSections="nav.topMenu.subSections"
            />
        </Teleport>
    </template>
</template>
