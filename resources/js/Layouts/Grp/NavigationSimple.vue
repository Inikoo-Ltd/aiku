<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Feb 2024 08:28:30 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang='ts'>
import { Navigation } from '@/types/Navigation'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faRoute } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Link } from '@inertiajs/vue3'
import { capitalize } from "@/Composables/capitalize"
import { isNavigationActive } from '@/Composables/useUrl'
import { onMounted, ref, onUnmounted, inject } from 'vue'
import TopBarSubsections from '@/Layouts/Grp/TopBarSubsections.vue'
import { faHandHoldingBox, faIndustry } from '@fal'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
library.add(faRoute, faHandHoldingBox, faIndustry)

const props = defineProps<{
    nav: Navigation
    navKey?: string | number  // shops_navigation | warehouses_navigation
}>()

const layout = inject('layout', layoutStructure)
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
    <component :is="nav.route?.name ? Link : 'div'" :href="nav.route?.name ? route(nav.route.name, nav.route.parameters) : '#'"
        class="w-full group flex items-center px-2 text-sm gap-x-2" :class="[
            isNavigationActive(layout.currentRoute, props.nav.root)
                ? 'navigationActive'
                : 'navigation',
            layout.leftSidebar.show ? '' : '',
        ]"
        @start="() => isLoading = true"
        @finish="() => isLoading = false"
        :aria-current="navKey === layout.currentModule ? 'page' : undefined"
        v-tooltip="{ content: capitalize(nav.tooltip), delay: { show: layout.leftSidebar.show ? 500 : 100, hide: 100 } }"
    >
        <LoadingIcon v-if="isLoading" class="flex-shrink-0 h-4 w-4" />
        <FontAwesomeIcon v-else-if="nav.icon" aria-hidden="true" class="flex-shrink-0 h-4 w-4" fixed-width :icon="nav.icon" />
        
        <Transition name="slide-to-left">
            <span v-if="layout.leftSidebar.show" class="py-0.5 leading-none whitespace-nowrap "
                :class="[layout.leftSidebar.show ? 'truncate block md:block' : 'block md:hidden']">
                {{ nav.label }}
            </span>
            <span v-else class="leading-none whitespace-nowrap block md:hidden">
                {{ nav.label }}
            </span>
        </Transition>

        <!-- If this Navigation is active, then teleport the SubSections to #TopBarSubsections in <AppTopBar> -->
        <template v-if="isTopMenuActive && nav.topMenu?.subSections && isNavigationActive(layout.currentRoute, props.nav.root || 'xx.xx.xx.xx')">
            <Teleport to="#TopBarSubsections" :disabled="!isNavigationActive(layout.currentRoute, props.nav.root || 'xx.xx.xx.xx')">
                <TopBarSubsections
                    v-if="nav.topMenu?.subSections"
                    :subSections="nav.topMenu.subSections"
                />
            </Teleport>
        </template>
    </component>

</template>
