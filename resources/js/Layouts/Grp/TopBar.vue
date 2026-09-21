<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Feb 2024 07:54:36 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faGoogle } from "@fortawesome/free-brands-svg-icons"
import { Link, router } from "@inertiajs/vue3"
import { computed, defineAsyncComponent, reactive, inject, ref } from "vue"
import { useScrollArrows } from "@/Composables/useScrollArrows"
import MenuPopoverList from "@/Layouts/Grp/MenuPopoverList.vue"
import TopBarSelectButton from "@/Layouts/Grp/TopBarSelectButton.vue"
import { Menu, MenuButton, MenuItems, Disclosure, MenuItem } from "@headlessui/vue"
import { trans } from "laravel-vue-i18n"
import Image from "@common/Components/Image.vue"
import { faChevronDown } from "@far"
import {
    faTerminal,
    faUserAlien,
    faCog,
    faCity,
    faBuilding,
    faNetworkWired,
    faUserHardHat,
    faCalendar,
    faStopwatch,
    faStoreAlt,
    faWarehouseAlt,
    faChartNetwork,
    faFolderTree,
    faFolder,
    faCube,
    faUserPlus,
    faBox,
    faBoxesAlt,
    faMoneyCheckAlt,
    faCashRegister,
    faCoins,
    faFileInvoiceDollar,
    faReceipt,
    faPersonDolly,
    faPeopleArrows,
    faConciergeBell,
    faGarage,
    faHamsa,
    faCodeMerge,
    faSortShapesDownAlt,
    faHatChef,
    faTags,
    faCommentDollar,
    faNewspaper,
    faMailBulk,
    faBell,
    faLaptopHouse,
    faHandHoldingBox,
    faStream,
    faShippingFast,
    faChessClock,
    faHouseDamage,
    faSign,
    faClipboardListCheck,
    faClipboardList,
    faPiggyBank, faLongArrowRight, faTruckContainer, faNarwhal, faUsersClass, faAlbumCollection, faBooks, faUserTie, faCodeBranch, faSatelliteDish, faAnalytics, faUserCircle, faAppleCrate, faChevronRight, faChevronLeft, faExchange } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import MenuTopRight from "@/Layouts/Grp/MenuTopRight.vue"
const TopBarDropdownScope = defineAsyncComponent(() => import("@/Layouts/Grp/TopBarDropdownScope.vue"))
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { faBallot } from "@fas"
import ScreenWarning from "@/Components/Utils/ScreenWarning.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useTruncate } from "@/Composables/useTruncate"

library.add(faExchange, faChevronLeft, faGoogle, faChevronDown, faTerminal, faUserAlien, faCog, faCity, faBuilding, faNetworkWired, faUserHardHat, faCalendar, faStopwatch, faStoreAlt, faWarehouseAlt, faChartNetwork, faFolderTree, faFolder, faCube, faUserPlus,
    faBox, faBoxesAlt, faMoneyCheckAlt, faCashRegister, faCoins, faFileInvoiceDollar, faReceipt, faPersonDolly, faPeopleArrows, faStream, faAppleCrate,
    faConciergeBell, faGarage, faHamsa, faCodeMerge, faSortShapesDownAlt, faHatChef, faTags, faCommentDollar, faNewspaper, faMailBulk, faBell, faLaptopHouse, faHandHoldingBox,
    faShippingFast, faChessClock, faBallot, faHouseDamage, faSign, faClipboardListCheck, faClipboardList, faPiggyBank, faLongArrowRight, faTruckContainer, faNarwhal, faUsersClass, faAlbumCollection, faBooks, faUserTie, faCodeBranch, faSatelliteDish, faAnalytics, faUserCircle, faChevronRight
)

defineProps<{
    sidebarOpen: boolean
    logoRoute: string
    urlPrefix: string
}>()

defineEmits<{
    (e: "sidebarOpen", value: boolean): void
}>()

// To handle skeleton image in dropdown
const imageSkeleton: { [key: string]: boolean } = reactive({})

const layoutStore = inject("layout", layoutStructure)

const subsectionScroller = ref<HTMLElement | null>(null)
const { canScrollLeft: canScrollSubsectionsLeft, canScrollRight: canScrollSubsectionsRight, scrollBy: scrollSubsections } = useScrollArrows(subsectionScroller)

const currentLocation = computed(() => {
    const params = layoutStore.currentParams ?? {}
    const entity: any = params.organisation
        ? layoutStore.organisations.data?.find((item: any) => item.slug == params.organisation)
            ?? layoutStore.agents.data?.find((item: any) => item.slug == params.organisation)
            ?? layoutStore.digital_agency?.data?.find((item: any) => item.slug == params.organisation)
        : null
    const place = params.shop
        ? entity?.authorised_shops?.find((shop: any) => shop.slug == params.shop)?.label ?? params.shop
        : params.fulfilment
            ? entity?.authorised_fulfilments?.find((fulfilment: any) => fulfilment.slug == params.fulfilment)?.label ?? params.fulfilment
            : params.warehouse
                ? entity?.authorised_warehouses?.find((warehouse: any) => warehouse.slug == params.warehouse)?.label ?? params.warehouse
                : null

    return {
        organisation: entity?.label ?? layoutStore.group?.label ?? "",
        place,
    }
})

// For label
const label = {
    // organisationSelect: trans("Select organisation"),
    // agentSelect: trans("Select Agent"),
    shopSelect: trans("Go to shop"),
    warehouseSelect: trans("Select warehouses"),
    fulfilmentSelect: trans("Select fulfilments")
}

</script>

<template>
    <Disclosure id="topbar_grp" as="nav" class="fixed top-0 z-[21] w-full bg-gray-50 text-gray-700 transition-all duration-300 ease-in-out" :class="['pr-4', layoutStore.messagingSidebar?.show ? 'md:pr-56' : (layoutStore.messagingSidebar?.micro ? 'md:pr-4' : 'md:pr-12')]" v-slot="{ open }">
        <ScreenWarning v-if="layoutStore.hasTopBanner" class="relative top-0" />

        <div class="px-0">
            <div class="flex h-11 lg:h-10 flex-shrink-0 w-full">
                <div class="flex items-center border-b border-gray-300">
                    <!-- Mobile: Hamburger -->
                    <button class="block md:hidden w-10 h-10 relative focus:outline-none" @click="$emit('sidebarOpen', !sidebarOpen)">
                        <span class="sr-only">Open sidebar</span>
                        <div class="block w-5 absolute left-1/2 top-1/2   transform  -translate-x-1/2 -translate-y-1/2">
                            <span aria-hidden="true" class="block absolute rounded-full h-0.5 w-5 bg-gray-900 transform transition duration-200 ease-in-out"
                                  :class="{'rotate-45': sidebarOpen,' -translate-y-1.5': !sidebarOpen }"></span>
                            <span aria-hidden="true" class="block absolute rounded-full h-0.5 w-5 bg-gray-900 transform transition duration-100 ease-in-out" :class="{'opacity-0': sidebarOpen } "></span>
                            <span aria-hidden="true" class="block absolute rounded-full h-0.5 w-5 bg-gray-900 transform transition duration-200 ease-in-out"
                                  :class="{'-rotate-45': sidebarOpen, ' translate-y-1.5': !sidebarOpen}"></span>
                        </div>
                    </button>

                    <!-- App Title: Image and Title -->
                    <div v-if="!layoutStore.user?.settings?.hide_logo" class="overflow-hidden relative flex flex-1 items-center justify-center md:justify-start transition-all duration-300 ease-in-out"
                         :class="[layoutStore.leftSidebar.show ? 'md:w-48 md:pr-4' : 'md:w-12']"
                         :style="{
                            'background-color': layoutStore.app.theme[0],
                            'color': layoutStore.app.theme[1],
                            'border-bottom': `1px solid ${layoutStore.app.theme[2]}3F`
                        }"
                    >
                        <Transition name="spin-to-down">
                            <Link :href="layoutStore.currentParams?.organisation ? route('grp.org.dashboard.show', layoutStore.currentParams?.organisation) : route('grp.dashboard.show')"
                                  :key="layoutStore.currentParams?.organisation"
                                  class="py-3 hidden md:flex flex-nowrap items-center h-full overflow-hidden gap-x-1.5 transition-all duration-200 ease-in-out"
                                  :class="[layoutStore.leftSidebar.show ? 'pl-4' : 'pl-2.5 w-full']"
                            >
                                <Image :src="layoutStore.organisations.data?.find((item) => item.slug == (layoutStore.currentParams?.organisation || false))?.logo || layoutStore.group?.logo" class="aspect-square h-5" />
                                <Transition name="slide-to-left">
                                    <p v-if="layoutStore.leftSidebar.show" class="text-lg bg-clip-text font-bold whitespace-nowrap leading-none lg:truncate">
                                        {{ layoutStore.currentParams?.organisation
                                        ? layoutStore.organisations.data?.find((item) => item.slug == layoutStore.currentParams?.organisation)?.label
                                        ?? layoutStore.agents.data?.find((item) => item.slug == layoutStore.currentParams?.organisation)?.label
                                        ?? layoutStore.group?.label
                                        : layoutStore.group?.label }}
                                    </p>
                                </Transition>
                            </Link>
                        </Transition>
                    </div>

                    <!-- Dropdown: TopBars -->
                    <Menu v-if="layoutStore.group || (layoutStore.organisations.data?.length > 1)" as="div" class="ml-2 relative text-left" v-slot="{ open: isOrgMenuOpen, close: closeOrgMenu }">
                        <MenuButton v-slot="{ open }"
                                    class="inline-flex lg:min-w-32 lg:w-[184px] h-[26px] lg:h-8 overflow-ellipsis rounded border border-gray-300 w-full whitespace-nowrap justify-between items-center gap-x-2 px-2.5 py-2 text-xxs font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75 max-lg:h-7 max-lg:w-auto max-lg:gap-x-2.5 max-lg:rounded-md max-lg:border-0 max-lg:px-3 max-lg:py-0 max-lg:text-gray-500 max-lg:ring-1 max-lg:ring-gray-300"
                                    :class="[!!(layoutStore.organisations.data?.find((item) => item.slug == layoutStore.currentParams?.organisation)) || !!layoutStore.agents.data?.find((item) => item.slug == layoutStore.currentParams?.organisation) ? 'bg-slate-200 text-slate-600 hover:bg-slate-300' : 'hover:bg-slate-200 text-slate-600']">
                            <div class="flex items-center gap-x-1 w-full truncate overflow-ellipsis line-clamp-2 max-lg:w-auto">
                                <FontAwesomeIcon :icon="layoutStore.currentParams?.organisation
                                            ? layoutStore.organisations.data?.find((item) => item.slug == layoutStore.currentParams?.organisation)?.label
                                                ? 'fal fa-building'
                                                : layoutStore.agents.data?.find((item) => item.slug == layoutStore.currentParams?.organisation)?.label
                                                    ? 'fal fa-people-arrows'
                                                    : layoutStore.digital_agency.data?.find((item) => item.slug == layoutStore.currentParams?.organisation)?.label
                                                        ? 'fal fa-laptop-house'
                                                        : 'fal fa-city'
                                            : 'fal fa-city'" class="opacity-60 text-xs" fixed-width aria-hidden="true" />
                                <Transition name="spin-to-down">
                          <span :key="layoutStore.currentParams?.organisation
                                              ? layoutStore.organisations.data?.find((item) => item.slug == layoutStore.currentParams?.organisation)?.label
                                                  ?? layoutStore.agents.data?.find((item) => item.slug == layoutStore.currentParams?.organisation)?.label
                                                  ?? layoutStore.digital_agency.data?.find((item) => item.slug == layoutStore.currentParams?.organisation)?.label
                                                  ?? layoutStore.group?.label
                                              : layoutStore.group?.label"
                                class="hidden lg:inline whitespace-pre-line">
                                              {{ useTruncate(layoutStore.currentParams?.organisation
                              ? layoutStore.organisations.data?.find((item) => item.slug == layoutStore.currentParams?.organisation)?.label
                              ?? layoutStore.agents.data?.find((item) => item.slug == layoutStore.currentParams?.organisation)?.label
                              ?? layoutStore.digital_agency.data?.find((item) => item.slug == layoutStore.currentParams?.organisation)?.label
                              ?? layoutStore.group?.label
                              : layoutStore.group?.label, 44) }}</span>
                                </Transition>
                            </div>
                            <FontAwesomeIcon icon="far fa-chevron-down" class="hidden text-xs transition-all duration-200 ease-in-out lg:inline-block" :class="[open ? 'rotate-180' : '']" aria-hidden="true" />
                            <FontAwesomeIcon icon="fal fa-exchange" class="text-xs lg:hidden" fixed-width aria-hidden="true" />
                        </MenuButton>

                        <transition
                            enter-active-class="transition-opacity duration-200 ease-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition-opacity duration-150 ease-in"
                            leave-to-class="opacity-0">
                            <div v-if="isOrgMenuOpen" class="fixed inset-0 z-[1] bg-gray-900/30 lg:hidden" aria-hidden="true" />
                        </transition>
                        <transition
                            enter-active-class="max-lg:transition-transform max-lg:duration-200 max-lg:ease-out"
                            enter-from-class="max-lg:-translate-x-full"
                            leave-active-class="max-lg:transition-transform max-lg:duration-150 max-lg:ease-in"
                            leave-to-class="max-lg:-translate-x-full">
                            <MenuItems
                                class="px-1 py-1 space-y-2.5 min-w-24 w-fit max-w-96 absolute left-0 mt-2 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black/5 focus:outline-none max-lg:fixed max-lg:inset-y-0 max-lg:z-[2] max-lg:mt-0 max-lg:w-72 max-lg:max-w-[85vw] max-lg:overflow-y-auto max-lg:rounded-none max-lg:px-2 max-lg:py-3">
                                <div class="flex items-center justify-between gap-x-2 px-1 pb-1 lg:hidden">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-700">{{ trans("Switch organisation") }}</p>
                                        <p class="mt-0.5 truncate text-xs text-slate-500">
                                            {{ trans("You're in") }}
                                            <span class="font-medium text-slate-700">{{ currentLocation.organisation }}</span>
                                            <template v-if="currentLocation.place"> › <span class="font-medium text-slate-700">{{ currentLocation.place }}</span></template>
                                        </p>
                                    </div>
                                    <button type="button" class="rounded p-2 text-gray-400 hover:text-gray-700" :aria-label="trans('Close')" @click="closeOrgMenu">
                                        <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                                    </button>
                                </div>
                                <!-- Dropdown: Group -->
                                <TopBarDropdownScope
                                    v-if="layoutStore.group && layoutStore.has_group_access"
                                    class=""
                                    :menuItems="[{
                                        label: layoutStore.group?.label,
                                    }]"
                                    menuKey="group"
                                    :imageSkeleton="imageSkeleton"
                                    :label="trans('Corporates')"
                                    icon="fal fa-user-tie"
                                />

                                <!-- Dropdown: Organisation (E-Commerce) -->
                                <TopBarDropdownScope
                                    v-if="layoutStore.organisations.data?.length"
                                    :menuItems="layoutStore.organisations.data"
                                    :imageSkeleton="imageSkeleton"
                                    :label="trans('E-Commerce')"
                                    icon="fal fa-cash-register"
                                    :closeMenu="closeOrgMenu"
                                />

                                <!-- Dropdown: Agents -->
                                <TopBarDropdownScope
                                    v-if="layoutStore.agents?.data?.length"
                                    :menuItems="layoutStore.agents?.data"
                                    :imageSkeleton="imageSkeleton"
                                    :label="trans('Agents')"
                                    icon="fal fa-people-arrows"
                                    :closeMenu="closeOrgMenu"
                                />

                                <!-- Dropdown: Digital Agency -->
                                <TopBarDropdownScope
                                    v-if="layoutStore.digital_agency?.data?.length"
                                    :menuItems="layoutStore.digital_agency?.data"
                                    :imageSkeleton="imageSkeleton"
                                    :label="trans('Digital Agency')"
                                    icon="fal fa-laptop-house"
                                    :closeMenu="closeOrgMenu"
                                />
                            </MenuItems>
                        </transition>
                    </Menu>
                </div>

                <div class="flex items-center justify-between isolate xpr-6 gap-x-3 flex-1 min-w-0 border-b border-gray-200">
                    <!-- Section: Dropdown + subsections -->
                    <div class="flex items-center gap-x-2 pl-2 xoverflow-x-auto flex-1 min-w-0">
                        <!-- Section: Dropdown -->
                        <div
                            v-if="
                    layoutStore.group
                    || (layoutStore.organisations.data?.length > 1)
                    || (
                            layoutStore.organisations.data?.find(organisation => organisation.slug == layoutStore.currentParams.organisation) &&
                            (
                                (route(layoutStore.currentRoute, layoutStore.currentParams)).includes('shops')
                                || layoutStore.currentRoute.includes('grp.org.dashboard.')
                            )
                        )
                    || (layoutStore.navigation.org?.[layoutStore.currentParams.organisation]?.warehouses_navigation && (route(layoutStore.currentRoute, layoutStore.currentParams)).includes('warehouse'))
                "
                            class="hidden lg:flex border border-gray-300 rounded"
                        >


                            <!-- Dropdown: Shops and Fulfilment-->
                            <Menu
                                v-if="
                    layoutStore.currentParams?.organisation
                    && ((layoutStore.isShopPage || layoutStore.isFulfilmentPage)
                        || layoutStore.currentRoute.includes('grp.org.dashboard.'))
                    && (layoutStore.organisations.data?.find(organisation => organisation.slug == layoutStore.currentParams.organisation)?.authorised_shops.length
                        || layoutStore.agents.data?.find(agent => agent.slug == layoutStore.currentParams.organisation)?.authorised_fulfilments.length)
                "
                                as="div" class="relative inline-block text-left"
                                v-slot="{ close: closeMenu }"
                            >
                                <TopBarSelectButton
                                    :icon="layoutStore.isFulfilmentPage ? 'fal fa-hand-holding-box' : 'fal fa-store-alt'"
                                    :activeButton="
                        !!((layoutStore.isFulfilmentPage && layoutStore.organisationsState[layoutStore.currentParams.organisation].currentFulfilment)
                        || (layoutStore.isShopPage && layoutStore.organisationsState[layoutStore.currentParams.organisation].currentShop))
                    "
                                    :label="
                        layoutStore.isFulfilmentPage
                            ? layoutStore.organisationsState?.[layoutStore.currentParams.organisation]?.currentFulfilment || label.fulfilmentSelect
                            : layoutStore.isShopPage
                                ? layoutStore.organisationsState?.[layoutStore.currentParams.organisation]?.currentShop || label.shopSelect
                                : 'Select shops/fulfilments'
                    "
                                    :key="`shop` + layoutStore.currentParams.shop + layoutStore.currentParams.fulfilment"
                                />

                                <transition>
                                    <MenuItems class="absolute left-0 mt-2 w-64 origin-top-right divide-y-0 divide-gray-400 rounded bg-white shadow-lg ring-1 ring-black/5 focus:outline-none">
                                        <MenuItem v-slot="{ active }" as="div"
                                                @click="() => router.visit(route('grp.org.shops.index', {
                                                    organisation: layoutStore.currentParams.organisation
                                                }))"
                                                class="group cursor-pointer flex gap-x-2 w-full border-b border-gray-300 justify-between items-center px-2 py-1 mt-0.5 text-xs"
                                                :class="layoutStore.currentRoute === 'grp.org.shops.index' ? 'bg-indigo-100 font-semibold text-gray-700' : 'hover:bg-gray-100 text-gray-500 hover:text-gray-700'"
                                            >
                                                <div class="w-full text-center">
                                                    {{ trans("Show all shops") }}
                                                    <FontAwesomeIcon icon="fal fa-long-arrow-right" class="group-hover:translate-x-1 transition-all" fixed-width aria-hidden="true" />
                                                </div>
                                        </MenuItem>

                                        <!-- Popover: shops list -->
                                        <MenuPopoverList
                                            v-if="layoutStore.organisations.data?.find(organisation => organisation.slug == layoutStore.currentParams.organisation)?.authorised_shops?.length || layoutStore.agents.data?.find(agent => agent.slug == layoutStore.currentParams.organisation)?.authorised_shops?.length"
                                            xicon="fal fa-store-alt"
                                            :navKey="'shop'"
                                            :closeMenu="closeMenu"
                                            class="pt-1"
                                        />

                                        <!-- Popover: fulfilment list -->
                                        <MenuPopoverList
                                            v-if="layoutStore.organisations.data?.find(organisation => organisation.slug == layoutStore.currentParams.organisation)?.authorised_fulfilments.length || layoutStore.agents.data?.find(agent => agent.slug == layoutStore.currentParams.organisation)?.authorised_fulfilments.length"
                                            xicon="fal fa-hand-holding-box"
                                            :navKey="'fulfilment'"
                                            :closeMenu="closeMenu"
                                            class="pb-1"
                                        />
                                    </MenuItems>
                                </transition>
                            </Menu>

                            <!-- Dropdown: Warehouse -->
                            <!-- {{ layoutStore.currentParams?.organisation }}
                            {{ Object.keys(layoutStore.navigation.org[layoutStore.currentParams?.organisation]?.warehouses_navigation || []).length > 1 }} -->
                            <Menu
                                v-if="
                    layoutStore.currentParams?.organisation
                    && Object.keys(layoutStore.navigation.org[layoutStore.currentParams?.organisation]?.warehouses_navigation || []).length > 1
                    && (route(layoutStore.currentRoute, layoutStore.currentParams)).includes('warehouses')
                "
                                as="div"
                                class="relative inline-block text-left"
                                v-slot="{ close: closeMenu }"
                            >
                                <TopBarSelectButton
                                    icon="fal fa-warehouse-alt"
                                    :activeButton="!!(layoutStore.currentParams.warehouse)"
                                    :label="(layoutStore.organisations.data?.find(organisation => organisation.slug == layoutStore.currentParams.organisation)?.authorised_warehouses?.find(warehouse => warehouse.slug == layoutStore.currentParams.warehouse)?.label || layoutStore.agents.data?.find(agent => agent.slug == layoutStore.currentParams.organisation)?.authorised_warehouses?.find(warehouse => warehouse.slug == layoutStore.currentParams.warehouse)?.label) ?? label.warehouseSelect"
                                />

                                <transition>
                                    <MenuItems class="absolute left-0 mt-2 w-56 origin-top-right divide-y divide-gray-100 rounded bg-white shadow-lg ring-1 ring-black/5 focus:outline-none">
                                        <MenuPopoverList icon="fal fa-warehouse-alt" :navKey="'warehouse'" :closeMenu="closeMenu" />
                                    </MenuItems>
                                </transition>
                            </Menu>


                        </div>

                        <!-- Section: Subsections (Something will teleport to this section) -->
                        <div class="relative flex h-full min-w-0">
                            <div ref="subsectionScroller" class="flex h-full min-w-0 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden [&>*]:shrink-0 md:[&>*>span]:inline" id="TopBarSubsections">
                            </div>
                            <button
                                v-if="canScrollSubsectionsLeft"
                                type="button"
                                class="absolute inset-y-0 left-0 flex w-6 items-center justify-center bg-gray-50 text-gray-500 shadow-[6px_0_6px_-4px_rgba(0,0,0,0.12)] hover:text-gray-800"
                                :aria-label="trans('Scroll left')"
                                @click="scrollSubsections(-1)">
                                <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width aria-hidden="true" />
                            </button>
                            <button
                                v-if="canScrollSubsectionsRight"
                                type="button"
                                class="absolute inset-y-0 right-0 flex w-6 items-center justify-center bg-gray-50 text-gray-500 shadow-[-6px_0_6px_-4px_rgba(0,0,0,0.12)] hover:text-gray-800"
                                :aria-label="trans('Scroll right')"
                                @click="scrollSubsections(1)">
                                <FontAwesomeIcon icon="fal fa-chevron-right" fixed-width aria-hidden="true" />
                            </button>
                        </div>

                    </div>

                    <!-- Section: Search, Notification, Profile -->
                    <MenuTopRight :urlPrefix="urlPrefix" />
                </div>
            </div>
        </div>
    </Disclosure>
</template>

