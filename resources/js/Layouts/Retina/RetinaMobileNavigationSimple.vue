<script setup lang="ts">
import { useLayoutStore } from "@/Stores/retinaLayout"
import { Navigation } from "@/types/Navigation"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAsterisk } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Link } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { isNavigationActive } from "@/Composables/useUrl"
import { onMounted, ref, onUnmounted, inject } from "vue"
import RetinaTopBarSubsections from "@/Layouts/Retina/RetinaTopBarSubsections.vue"
import { faRoute, faTachometerAlt, faFileInvoiceDollar, faHandHoldingBox, faPallet } from "@fal"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faAsterisk, faTachometerAlt, faFileInvoiceDollar, faRoute, faPallet, faHandHoldingBox)

const props = defineProps<{
    navKey: string | number  // shops_navigation | warehouses_navigation
    nav: Navigation
}>()

const layout = useLayoutStore()
const isTopMenuActive = ref(false)
const isLoading = ref(false)

onMounted(() => {
    isTopMenuActive.value = true
})

onUnmounted(() => {
    isTopMenuActive.value = false
})
</script>

<template>
    <Link :href="nav?.route?.name ? route(nav.route?.name, nav?.route?.parameters) : '#'"
        class="group flex items-center px-2 text-[20px] gap-x-2"
        :class="[
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
    >
        <LoadingIcon v-if="isLoading" class="flex-shrink-0" />
        <FontAwesomeIcon v-else-if="nav.icon" aria-hidden="true" :rotation="nav.icon_rotation" class="flex-shrink-0" fixed-width :icon="nav.icon" />
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
