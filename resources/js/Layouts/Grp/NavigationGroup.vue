<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Feb 2024 08:27:43 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang='ts'>
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { useLayoutStore } from '@/Stores/layout'
import NavigationSimple from '@/Layouts/Grp/NavigationSimple.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Navigation } from '@/types/Navigation'
import { usePage } from '@inertiajs/vue3'
import { isNavigationActive } from '@/Composables/useUrl'
import { generateCurrentString } from '@/Composables/useConvertString'
import { ref, computed } from 'vue'


const props = defineProps<{
    orgNav: {
        [key: string]: Navigation[]
    }
    itemKey: string  // shop | warehouse
    icon: string

}>()

const isCurrentRouteActive = computed(() => {
    return Object.values(props.orgNav[Object.keys(props.orgNav)[0]]).some(nav => (isNavigationActive(layout.currentRoute, nav.root)))
})

const layout = useLayoutStore()
const isPanelOpen = ref(isCurrentRouteActive.value || props.itemKey == layout.organisationsState?.[layout.currentParams.organisation]?.currentType)

</script>

<template>
    <Disclosure >
        <div class="relative isolate ring-1 ring-white/20 rounded transition-all duration-200 ease-in-out mb-1"
            :class="layout.leftSidebar.show ? 'px-1' : 'px-0'"
            :style="{'box-shadow': `0 0 0 1px ${layout.app.theme[1]}55`}"
        >
            <!-- Label: Icon shops/warehouses and slug -->
            <DisclosureButton @click="isPanelOpen = !isPanelOpen" class="w-full flex justify-between items-end pt-2 px-2.5 pb-2"
                :style="{color: layout.app.theme[1] + '99'}"
            >
                <div class="flex gap-x-1.5 items-center">
                    <FontAwesomeIcon v-if="icon" :icon='icon' class='text-xxs' fixed-width aria-hidden='true' />
                    <template v-if="layout.leftSidebar.show">
                        <span class="text-sm leading-none uppercase">
                            {{ layout.organisationsState?.[layout.currentParams.organisation]?.[generateCurrentString(itemKey)] || Object.keys(orgNav)[0] }}
                        </span>
                        <span class="text-[7px] leading-none">({{ itemKey }})</span>
                    </template>
                </div>
                <FontAwesomeIcon icon='fal fa-chevron-down' class='transition'
                    :class="[isPanelOpen ? 'rotate-180' : '',
                        layout.leftSidebar.show ? 'justify-self-end text-xs' : 'h-[4px] aspect-square p-[2px] text-white bg-indigo-700 rounded border border-gray-100/50 absolute bottom-0 translate-y-1/2 left-1/2 -translate-x-1/2 text-[4px]'
                    ]" aria-hidden='true' />
            </DisclosureButton>

            <!-- {{ Object.keys(orgNav[layout.organisationsState?.[layout.currentParams.organisation]?.[generateCurrentString(itemKey)]]) }} -->
            <!-- If Shops/Warehouses length is 1 (Show the subnav straighly) -->
            <div v-show="isPanelOpen">
                <DisclosurePanel static v-if="Object.keys(orgNav || []).length === 1" class="flex flex-col gap-y-1 mb-1">
                    <!-- group only 1 -->
                    <template v-for="nav, navIndex, index in orgNav[Object.keys(orgNav)[0]]" :key="navIndex + index">
                        <NavigationSimple
                            :nav="nav"
                            :navKey="navIndex"
                        />
                    </template>
                </DisclosurePanel>

                <!-- If Shops/Warehouses length is more than 1 and current warehouse is exist -->
                <DisclosurePanel static v-else-if="layout.organisationsState?.[layout.currentParams.organisation]?.[generateCurrentString(itemKey)]"
                    class="flex flex-col gap-y-1 mb-1">
                    <!-- Looping: SubNav -->
                    <template v-for="nav, navIndex in orgNav[layout.organisationsState?.[layout.currentParams.organisation]?.[generateCurrentString(itemKey)]]" :key="navIndex">
                        <NavigationSimple
                            :nav="nav"
                            :navKey="navIndex"
                        />

                        <!-- <div v-if="(nav.route?.name ? isRouteSameAsCurrentUrl(route(nav.route.name, nav.route.parameters)) : false)"
                            class="absolute inset-0 bg-black/20 rounded -z-10"
                        /> -->
                    </template>
                </DisclosurePanel>
            </div>

            <div v-if="isCurrentRouteActive"
                class="absolute inset-0 bg-black/20 rounded -z-10"
            />
        </div>
    </Disclosure>
</template>
