<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Feb 2024 08:28:47 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang='ts'>
import { useLayoutStore } from '@/Stores/layout'
import { Navigation } from '@/types/Navigation'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faStoreAlt, faWarehouseAlt } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Link } from '@inertiajs/vue3'
import SubNavigation from '@/Layouts/Grp/SubNavigation.vue'
import {
    Disclosure, DisclosureButton, DisclosurePanel,
    Popover, PopoverButton, PopoverPanel
} from '@headlessui/vue'

library.add(faStoreAlt, faWarehouseAlt)

const props = defineProps<{
    navKey: string  // shops_navigation | warehouses_navigation
    subNav: {
        [shopKey: string]: { [navShopKey: string]: Navigation; }
    }
}>()

const layout = useLayoutStore()
const navigationName = props.navKey.split('_')[0].slice(0, -1)  // shops_navigation to shops | warehouses_navigation to warehouses
</script>

<template>
    <component :is="layout.leftSidebar.show ? Disclosure : Popover" v-slot="{ open }" as="div"
        class="relative hover:bg-indigo-300/30"
        :class="[navKey === layout.currentModule ? 'px-0.5' : '']">
        <!-- Label Navigation: Shops/Warehouses -->
        <component :is="layout.leftSidebar.show ? DisclosureButton : PopoverButton" as="div"
            class="flex items-center justify-between cursor-pointer"
            :class="layout.leftSidebar.show ? 'pt-2 py-1 pl-4 pr-2' : open ? 'py-2 bg-indigo-600' : 'py-2 hover:bg-indigo-500'"
        >
            <div class="leading-none text-white font-bold pb-1 select-none flex items-center gap-x-1" :class="layout.leftSidebar.show ? '' : 'mx-auto'">
                <FontAwesomeIcon v-if="navKey == 'shops_navigation'" icon='fal fa-store-alt' class='text-sm opacity-65' aria-hidden='true' />
                <FontAwesomeIcon v-if="navKey == 'warehouses_navigation'" icon='fal fa-warehouse-alt' class='text-sm opacity-65' aria-hidden='true' />

                <span v-if="layout.leftSidebar.show">
                    <template v-if="layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.[`authorised_${navigationName}s`].length === 1">
                        {{ layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.[`authorised_${navigationName}s`][0].name }}
                    </template>
                    <template v-else>{{ navKey.split('_')[0] }}</template>
                </span>
            </div>
            <FontAwesomeIcon v-if="layout.leftSidebar.show" icon='fal fa-chevron-down' class='text-white text-xs transition-all duration-200 ease-in-out'
                :class="[open ? 'rotate-180' : '']" aria-hidden='true' />
        </component>

        <!-- Looping: Subnav -->
        <transition>
            <teleport to="#leftSidebar" :disabled="layout.leftSidebar.show ? true : false">
                <component :is="layout.leftSidebar.show ? DisclosurePanel : PopoverPanel"
                    :class="layout.leftSidebar.show ? 'mt-1' : 'absolute top-0 left-12 max-h-96 min-h-fit overflow-y-auto bg-indigo-300 rounded-lg overflow-hidden z-10 mt-3 w-64 px-3 py-2'"
                >
                    <!-- If shop/warehouse not selected, then show all -->
                    <template v-if="!layout.currentParams[navigationName]">
                        <!-- LeftSidebar: Popover (if minimize)-->
                        <div v-if="!layout.leftSidebar.show" class="flex items-center gap-x-1 text-white font-bold mt-1">
                            <span class="capitalize leading-none text-sm">{{ navigationName }}s</span>
                            <hr class="w-full border border-white rounded-full mt-1">
                        </div>

                        <!-- If: Length available is only 1 -->
                        <template v-if="layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.[`authorised_${navigationName}s`].length === 1">
                            <SubNavigation v-for="(shopNavigation, navigationIndex) in subNav[layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.[`authorised_${navigationName}s`][0].slug]"
                                :navigation="shopNavigation" :indexNav="navigationIndex">
                            </SubNavigation>
                        </template>

                        <!-- Else: Length available is more than 1 -->
                        <template v-else>
                            <div v-for="(navigationShopWarehouse, indexShopWarehouse) in layout.organisations.data.find(organisation => organisation.slug == layout.currentParams.organisation)?.[`authorised_${navigationName}s`]" :key="indexShopWarehouse"
                                class="group flex flex-col justify-center text-sm py-0.5 gap-y-1" :class="[
                                    navigationShopWarehouse.slug === layout.currentModule ? '' : '',
                                    layout.leftSidebar.show ? '' : ''
                                ]"
                                :aria-current="navigationShopWarehouse.slug === layout.currentModule ? 'page' : undefined">
                                <Disclosure v-slot="{ open: subOpen }">
                                    <DisclosureButton>
                                        <div class="group flex items-center justify-between text-sm px-3 py-2 text-indigo-200 font-semibold transition-all duration-0 ease-in-out" :class="[
                                            navigationShopWarehouse.slug === layout.currentModule
                                                ? 'bg-white/20 text-white border-l-4 border-indigo-100'
                                                : 'border-l-4 border-transparent hover:border-indigo-100/30 hover:bg-indigo-100/10',
                                            subOpen ? 'bg-white/20 text-white border-l-4 border-indigo-100' : '',
                                            layout.leftSidebar.show ? 'ml-6' : 'text-indigo-500',
                                        ]" :aria-current="navigationShopWarehouse.slug === layout.currentModule ? 'page' : undefined">
                                            <div class="flex items-center gap-x-2">
                                                <FontAwesomeIcon v-if="navigationShopWarehouse.icon" aria-hidden="true" class="shrink-0 h-4 w-4" :icon="navigationShopWarehouse.icon" />
                                                <span class="capitalize leading-none whitespace-nowrap">
                                                    {{ navigationShopWarehouse.name }}
                                                </span>
                                            </div>
                                            <FontAwesomeIcon v-if="layout.leftSidebar.show" icon='fal fa-chevron-down' class='text-white text-xxs transition-all duration-200 ease-in-out' :class="[subOpen ? 'rotate-180' : '']" aria-hidden='true' />
                                        </div>
                                    </DisclosureButton>

                                    <DisclosurePanel>
                                        <div class="pl-2">
                                            <SubNavigation v-for="(shopNavigation, navigationIndex) in subNav[navigationShopWarehouse.slug]"
                                                :navigation="shopNavigation" :indexNav="navigationIndex">
                                            </SubNavigation>
                                        </div>
                                    </DisclosurePanel>
                                </Disclosure>

                            </div>
                        </template>
                    </template>

                    <!-- If shop selected, show only selected shop -->
                    <template v-else>
                        <div class="group flex flex-col justify-center text-sm py-0.5 gap-y-1"
                            :class="[
                                layout.currentParams[navigationName] === layout.currentModule ? '' : '',
                            ]"
                            :aria-current="layout.currentParams[navigationName] === layout.currentModule ? 'page' : undefined">
                            <!-- <p class="bg-indigo-300 py-0.5 pl-1 text-slate-700 font-bold">{{ layout.currentParams[navigationName] }}</p> -->

                            <!-- Looping: Navigation in Shop -->
                            <SubNavigation v-for="(shopNavigation, navigationIndex) in subNav[layout.currentParams[navigationName]]"
                                :navigation="shopNavigation" :indexNav="navigationIndex">
                            </SubNavigation>
                        </div>
                    </template>
                </component>
            </teleport>
        </transition>
    </component>
</template>
