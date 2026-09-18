<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 03 Mar 2023 13:49:56 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import LeftSidebarNavigation from "@/Layouts/Grp/LeftSidebarNavigation.vue"
import LeftSidebarBottomNav from "@/Layouts/Grp/LeftSidebarBottomNav.vue"
import { Popover, PopoverButton, PopoverPanel } from "@headlessui/vue"
import { useLogoutAuth } from "@/Composables/useAppMethod"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronLeft } from "@far"
import { faSignOutAlt, faSensor, faLifeRing, faHeadset, faCommentAlt, faSignOut, faServer, faTasks } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed, inject, onBeforeUnmount, onMounted, ref } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { isNavigationActive } from "@/Composables/useUrl"
import { Link } from "@inertiajs/vue3"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faTasks, faChevronLeft, faSignOutAlt, faSensor, faLifeRing, faHeadset, faCommentAlt, faSignOut, faServer)

const layout = inject("layout", layoutStructure)

// Set the LeftSidebar value to local storage
const handleToggleLeftBar = () => {
    if (typeof window !== "undefined") {
        localStorage.setItem("leftSideBar", (!layout.leftSidebar.show).toString())
    }
    layout.leftSidebar.show = !layout.leftSidebar.show
}

const logoutData = computed(() => ({
    label: ctrans("Logout"),
    tooltip: ctrans("Logout the app"),
    icon: "fal fa-sign-out-alt"
}))

const scopedModuleRoute = (module: string) => {
    const organisation = layout.currentParams?.organisation
    const shop = layout.currentParams?.shop

    if (organisation && shop) {
        return { name: `grp.org.shops.show.${module}.index`, parameters: { organisation, shop }, root: `grp.org.shops.show.${module}.` }
    }

    if (organisation) {
        return { name: `grp.org.${module}.index`, parameters: { organisation }, root: `grp.org.${module}.` }
    }

    return { name: `grp.${module}.index`, parameters: {}, root: `grp.${module}.` }
}

const tasksRoute = computed(() => scopedModuleRoute("tasks"))

const ticketsRoute = computed(() => scopedModuleRoute("tickets"))

const bottomLinks = computed(() => [
    { route: tasksRoute.value.name, parameters: tasksRoute.value.parameters, root: tasksRoute.value.root, label: ctrans("Tasks"), tooltip: ctrans("Tasks: ask a colleague or a department for something"), icon: "fal fa-tasks" },
    { route: ticketsRoute.value.name, parameters: ticketsRoute.value.parameters, root: ticketsRoute.value.root, label: ctrans("Tickets"), tooltip: ctrans("Tickets: report a problem or ask for help"), icon: "fal fa-life-ring" },
    { route: "grp.chat.dashboard", parameters: {}, root: "grp.chat.", label: ctrans("Chat"), tooltip: ctrans("Chat with customers and colleagues"), icon: "fal fa-comment-alt" },
])

const loadingRoute = ref<string | null>(null)

const BOTTOM_LINKS_OFFSET = 80
const bottomLinksGroup = ref<HTMLElement | null>(null)
const bottomLinksHeight = ref<number | null>(null)
let bottomLinksObserver: ResizeObserver | null = null

const navigationPaddingBottom = computed(() => (bottomLinksHeight.value === null ? "240px" : `${bottomLinksHeight.value + BOTTOM_LINKS_OFFSET}px`))

onMounted(() => {
    if (typeof ResizeObserver === "undefined" || !bottomLinksGroup.value) return
    bottomLinksObserver = new ResizeObserver(([entry]) => (bottomLinksHeight.value = (entry.target as HTMLElement).offsetHeight))
    bottomLinksObserver.observe(bottomLinksGroup.value)
})

onBeforeUnmount(() => bottomLinksObserver?.disconnect())

const isLoadingLogout = ref(false)
const onLogoutAuth = () => {
    useLogoutAuth(layout.user, {
        onStart: () => (isLoadingLogout.value = true),
        onError: () => (isLoadingLogout.value = false)
    })
}
</script>

<template>
    <div
        class="fixed top-0 md:flex md:flex-col md:inset-y-0 h-full transition-all duration-300 ease-in-out"
        :style="{
			paddingBottom: navigationPaddingBottom,
			'background-color': layout.app.theme[0],
			color: layout.app.theme[2],
		}"
        :class="[
			layout.leftSidebar.show ? 'w-8/12 md:w-48' : 'w-8/12 md:w-12',
			layout.hasTopBanner ? 'mt-11 lg:mt-16' : 'mt-11 lg:mt-10',
		]"
        id="leftSidebar">
        <!-- Toggle: collapse-expand LeftSideBar -->
        <div
            @click="handleToggleLeftBar"
            class="hidden absolute z-10 right-0 top-2/4 -translate-y-full translate-x-1/2 w-8 lg:w-5 aspect-square border border-gray-300 rounded-full md:flex md:justify-center md:items-center cursor-pointer"
            :title="layout.leftSidebar.show ? 'Collapse the bar' : 'Expand the bar'"
            :style="{
				'background-color': `color-mix(in srgb, ${layout.app.theme[0]} 85%, black)`,
				color: layout.app.theme[1],
			}">
            <div
                class="flex items-center justify-center transition-all duration-300 ease-in-out"
                :class="{ 'rotate-180': !layout.leftSidebar.show }">
                <FontAwesomeIcon
                    icon="far fa-chevron-left"
                    class="h-[10px] leading-none"
                    aria-hidden="true"
                    :class="layout.leftSidebar.show ? '-translate-x-[1px]' : ''" />
            </div>
        </div>

        <div class="flex flex-grow flex-col h-full overflow-hidden">
            <LeftSidebarNavigation />
        </div>

        <div ref="bottomLinksGroup" class="absolute bottom-20 w-full px-2 pt-3">
            <div class="flex flex-col justify-center gap-y-1.5">
                <Link
                    v-for="link in bottomLinks"
                    :key="link.route"
                    :href="route(link.route, link.parameters)"
                    class="relative w-full group flex items-center px-2 text-sm gap-x-2"
                    :class="isNavigationActive(layout.currentRoute, link.root) ? 'navigationActive' : 'navigation'"
                    v-tooltip="{
						content: link.tooltip,
						delay: { show: layout.leftSidebar.show ? 500 : 100, hide: 100 },
					}"
                    @start="() => (loadingRoute = link.route)"
                    @finish="() => (loadingRoute = null)">
                    <LoadingIcon v-if="loadingRoute === link.route" class="flex-shrink-0 h-4 w-4" />
                    <FontAwesomeIcon
                        v-else
                        aria-hidden="true"
                        class="flex-shrink-0 h-4 w-4"
                        fixed-width
                        :icon="link.icon" />

                    <Transition name="slide-to-left">
						<span
                            v-if="layout.leftSidebar.show"
                            class="py-0.5 leading-none whitespace-nowrap"
                            :class="[
								layout.leftSidebar.show
									? 'truncate block md:block'
									: 'block md:hidden',
							]">
							{{ link.label }}
						</span>
                        <span v-else class="leading-none whitespace-nowrap block md:hidden">
							{{ link.label }}
						</span>
                    </Transition>
                </Link>

                <Popover class="relative w-full" v-slot="{ open }">
                    <PopoverButton
                        class="flex w-full focus:outline-none focus:ring-0 focus:border-none">
                        <div
                            class="w-full group flex items-center px-2 text-sm gap-x-2"
                            :class="open ? 'navigationActive' : 'navigation'">
                            <FontAwesomeIcon aria-hidden="true" class="flex-shrink-0 h-4 w-4" fixed-width icon="fal fa-sign-out-alt" />
                            <span v-if="layout.leftSidebar.show" class="truncate py-0.5 leading-none whitespace-nowrap">{{ logoutData.label }}</span>
                        </div>
                    </PopoverButton>

                    <transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95">
                        <PopoverPanel
                            class="absolute -top-3 left-1/2 -translate-y-full bg-white rounded-md px-4 py-3 border border-gray-200 shadow">
                            <div class="min-w-32 flex flex-col justify-center gap-y-2">
                                <div class="whitespace-nowrap text-gray-500 text-xs">
                                    {{ ctrans("Are you sure want to logout?") }}
                                </div>
                                <div class="mx-auto">
                                    <Button
                                        @click="onLogoutAuth()"
                                        :loading="isLoadingLogout"
                                        :label="ctrans('Yes, Logout')"
                                        type="red" />
                                </div>
                            </div>
                        </PopoverPanel>
                    </transition>
                </Popover>
            </div>
        </div>

        <div v-if="false" class="absolute bottom-[68px] w-full">
            <LeftSidebarBottomNav />
        </div>
    </div>
</template>

<style>




/* Hide scrollbar for Chrome, Safari and Opera */
.custom-hide-scrollbar::-webkit-scrollbar {
    display: none;
}

/* Hide scrollbar for IE, Edge and Firefox */
.custom-hide-scrollbar {
    -ms-overflow-style: none; /* IE and Edge */
    scrollbar-width: none; /* Firefox */
}
</style>
