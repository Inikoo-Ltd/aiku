<script setup lang="ts">
import { useLayoutStore } from "@/Stores/retinaLayout";
import { Navigation } from "@/types/Navigation";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faAsterisk } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { Link } from "@inertiajs/vue3";
import { capitalize } from "@/Composables/capitalize";
import { isNavigationActive } from "@/Composables/useUrl";
import { onMounted, ref, onUnmounted, inject } from "vue";
import RetinaTopBarSubsections from "@/Layouts/Retina/RetinaTopBarSubsections.vue";
import { faRoute, faTachometerAlt, faFileInvoiceDollar, faHandHoldingBox, faPallet } from "@fal";
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faAsterisk, faTachometerAlt, faFileInvoiceDollar, faRoute, faPallet, faHandHoldingBox);

const props = defineProps<{
    navKey: string | number  // shops_navigation | warehouses_navigation
    nav: Navigation
}>();

const locale = inject('locale', {})
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

const activeClass = `bg-[${layout.app?.theme[3]}] text-[${layout.app?.theme[0]}]`
const inactiveClass = `bg-[${layout.app?.theme[4]}] text-[${layout.app?.theme[1]}]`
</script>

<template>
    <!-- {{ layout.app?.theme }} -->
    <!-- <div class="text-xxs">{{ layout.currentRoute }} <br> {{ nav.route.name }}</div> -->
    <Link :href="nav?.route?.name ? route(nav.route?.name, nav?.route?.parameters) : '#'"
        class="group flex items-center px-2 text-sm gap-x-2" :class="[
            isNavigationActive(layout.currentRoute, props.nav.root)
                ? 'navigationActive'
                : 'navigation',
            layout.leftSidebar.show ? '' : 'pl-3',
        ]"
        :style="[isNavigationActive(layout.currentRoute, props.nav.root) ? {
            'background-color': layout.app?.theme[1],
            'color': layout.app?.theme[2]
        } : {} ]"
        @start="() => isLoading = true"
        @finish="() => isLoading = false"
        xaria-current="navKey === layout.currentModule ? 'page' : undefined"
        v-tooltip="layout.leftSidebar.show ? false : capitalize(nav.label)"
    >
        <LoadingIcon v-if="isLoading" class="flex-shrink-0 h-4 w-4" />
        <FontAwesomeIcon v-else-if="nav.icon" aria-hidden="true" :rotation="nav.icon_rotation" class="flex-shrink-0 h-4 w-4" fixed-width :icon="nav.icon" />
        <div class=" items-center justify-between w-full leading-none"
            :class="[
                layout.leftSidebar.show ? 'flex' : 'block md:hidden'
            ]"
        >
            <Transition name="slide-to-left">
                <span v-if="layout.leftSidebar.show" class="capitalize leading-none whitespace-nowrap block md:block">
                    {{ nav.label }}
                    <FontAwesomeIcon v-if="nav.indicator" icon="fas fa-circle" class="align-middle text-red-600 text-[0.5rem] animate-pulse" fixed-width aria-hidden="true" />
                </span>
                <span v-else class="capitalize leading-none whitespace-nowrap block md:hidden">
                    {{ nav.label }}
                    <FontAwesomeIcon v-if="nav.indicator" icon="fas fa-circle" class="align-middle text-red-600 text-[0.5rem] animate-pulse" fixed-width aria-hidden="true" />
                </span>
            </Transition>


            <Transition name="spin-to-right">
                <div v-if="layout.leftSidebar.show && nav.right_label" class="h-4 w-4 rounded-full flex justify-center items-center text-xs tabular-nums pr-2"
                    :class="[
                        isNavigationActive(layout.currentRoute, props.nav.root) ? activeClass : inactiveClass,
                    ]"
                    v-tooltip="nav.right_label.tooltip"
                >
                    <span v-if="nav.right_label.label">{{ nav.right_label.label }}</span>
                    <span v-if="nav.right_label.number">{{ locale.number(nav.right_label.number) }}</span>
                    <FontAwesomeIcon v-if="nav.right_label.is_important" icon="fas fa-asterisk" class="text-red-500 text-[5px]" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon v-if="nav.right_label.icon" :icon="nav.right_label.icon" fixed-width aria-hidden="true" />
                </div>
            </Transition>
        </div>
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
