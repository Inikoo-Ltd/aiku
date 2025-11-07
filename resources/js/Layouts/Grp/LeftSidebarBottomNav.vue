<script setup lang='ts'>
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCity, faBuilding, faStoreAlt, faWarehouseAlt } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
import { useLayoutStore } from '@/Stores/layout' 
import { router, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
library.add(faCity, faBuilding, faStoreAlt, faWarehouseAlt)

const layout = useLayoutStore()

const bottomNavigation = computed(() => [
    // {
    //     // Group
    //     show: layout.group ? true : false,
    //     icon: 'fal fa-city',
    //     label: 'Group',
    //     activeState: !layout.currentParams?.organisation,
    //     data: [{
    //         label: layout.group?.label,
    //         route: {
    //             name: 'grp.dashboard.show',
    //             parameters: {}
    //         }
    //     }],
    // },
    {
        // Organisations
        show: layout.organisations.data?.length > 1 ? true : false,
        icon: 'fal fa-building',
        label: 'Organisations',
        activeState: layout.currentParams?.organisation ?? false,
        data: layout.organisations.data,
    },
    {
        // Shops
        show: (layout.currentParams?.organisation && (layout.organisations.data.find(organisation => organisation.slug == layout.currentParams?.organisation)?.authorised_shops?.length > 1)) ?? false,
        icon: 'fal fa-store-alt',
        label: 'Shops',
        activeState: layout.organisationsState?.[layout.currentParams?.organisation]?.currentShop,
        showAll: {
            label: 'Show all shops',
            route: {
                name: layout.navigation.org?.[layout.currentParams?.organisation]?.shops_index?.route.name,
                parameters: layout.navigation.org?.[layout.currentParams?.organisation]?.shops_index?.route.parameters
            }
        },
        data: layout.organisations.data.find(organisation => organisation.slug == layout.currentParams?.organisation)?.authorised_shops,
    },
    {
        // Warehouses
        show: (layout.currentParams?.organisation && (layout.organisations.data.find(organisation => organisation.slug == layout.currentParams?.organisation)?.authorised_warehouses.length > 1)) ?? false,
        icon: 'fal fa-warehouse-alt',
        label: 'Warehouses',
        activeState: layout.organisationsState.currentWarehouse,
        showAll: {
            label: 'Show all warehouses',
            route: {
                name: layout.navigation.org?.[layout.currentParams?.organisation]?.warehouses_index?.route?.name,
                parameters: layout.navigation.org?.[layout.currentParams?.organisation]?.warehouses_index?.route?.parameters
            }
        },
        data: layout.organisations.data.find(organisation => organisation.slug == layout.currentParams?.organisation)?.authorised_warehouses,
    }
])
// console.log(layout.group)
</script>

<template>
    <div class="flex justify-around px-2 transition-all duration-200 ease-in-out"
        :class="layout.leftSidebar.show ? '' : 'flex-col-reverse items-center gap-y-2 mb-2'"
    >
        <!-- <Link :href="route('grp.dashboard.show')" v-tooltip="trans('Go to Group dashboard')" :aria-label="'qqqq'"
            class="text-white flex-shrink cursor-pointer px-1 py-2 rounded-md flex flex-col items-center justify-center gap-y-1 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-white/75">
            <FontAwesomeIcon icon="fal fa-city" class='leading-none' fixed-width aria-hidden='true' />
            <span v-if="layout.leftSidebar.show" class="text-[8px] leading-none tracking-widest text-center">{{ layout.group?.label }}</span>
        </Link> -->

        <!-- Shops -->
        <template v-for="(bottomNav, indexBottomNav) in bottomNavigation" :key="indexBottomNav">
            <Menu v-if="bottomNav.show"
                as="div" class="relative"
                v-slot="{ open, close: closeMenu }"
            >
                <MenuButton v-tooltip="bottomNav.label" :aria-label="bottomNav.label"
                    class="rounded-md flex flex-col items-center justify-center gap-y-1 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-white/75"
                    :class="[
                        open ? 'bg-indigo-400 rounded-md text-white' : bottomNav.activeState ? 'text-white' :  'text-indigo-300 hover:text-white',
                        layout.leftSidebar.show ? 'px-4 py-2 ' : 'px-2 py-2',
                    ]">
                    <FontAwesomeIcon :icon='bottomNav.icon' class='leading-none' fixed-width aria-hidden='true' />
                    <span v-if="layout.leftSidebar.show" class="text-[7px] leading-none tracking-widest text-center">{{ bottomNav.activeState ? bottomNav.activeState.toString().slice(0, 3) : '' }}</span>
                </MenuButton>
                
                <transition>
                    <MenuItems class="absolute bottom-full -translate-y-2 w-52 p-1 origin-bottom-left rounded bg-white shadow-lg ring-1 ring-black/5 focus:outline-hidden">
                        <div class="flex items-center gap-x-1.5 px-1 mb-1">
                            <FontAwesomeIcon :icon='bottomNav.icon' class='text-gray-400 text-xxs' aria-hidden='true' />
                            <span class="text-[9px] leading-none text-gray-400">{{ bottomNav.label }}</span>
                            <hr class="w-full rounded-full border-slate-300">
                        </div>
                        
                        <!-- Section: Show All -->
                        <template v-if="bottomNav.showAll">
                            <div @click="() => (router.visit(route(bottomNav.showAll.route.name, bottomNav.showAll.route.parameters)), closeMenu())"
                                class="flex gap-x-2 items-center pl-3 py-1.5 text-xs cursor-pointer rounded text-slate-500 hover:bg-slate-200/75 hover:text-slate-600">
                                <FontAwesomeIcon icon='fal fa-store-alt' class='' aria-hidden='true' />
                                <span class="font-semibold">{{ bottomNav.showAll.label }}</span>
                            </div>
                            <hr class="w-11/12 mx-auto border-t border-slate-300 mt-1 mb-2.5">
                        </template>
                        
                        <!-- Section: Looping Item -->
                        <div v-if="bottomNav.data" class="max-h-52 overflow-y-auto space-y-1.5">
                            <template v-for="(showare, idxSH) in bottomNav.data">
                                <MenuItem v-if="showare.state != 'closed'"
                                    v-slot="{ active }"
                                    @click="() => router.visit(route(showare.route?.name, showare.route?.parameters))"
                                    :class="[
                                        showare.state == 'closed' ? 'bg-slate-200 select-none' : showare.slug == bottomNav.activeState ? 'bg-slate-500 text-white' : 'text-slate-600 hover:bg-slate-200/75 hover:text-indigo-600',
                                        'group flex gap-x-2 w-full justify-start items-center rounded px-2 py-2 text-sm cursor-pointer',
                                    ]"
                                    v-tooltip="showare.state == 'closed' ? `This ${bottomNav.label.slice(0, -1)} is closed.` : false"
                                >
                                    <div class="font-semibold">{{ showare.label }}</div>
                                </MenuItem>
                            </template>
                        </div>
                    </MenuItems>
                </transition>
            </Menu>
        </template>
    </div>
</template>