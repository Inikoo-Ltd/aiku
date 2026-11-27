<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 03 Mar 2023 13:49:56 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import LeftSidebarNavigation from "@/Layouts/Grp/LeftSidebarNavigation.vue"
import LeftSidebarBottomNav from "@/Layouts/Grp/LeftSidebarBottomNav.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronLeft } from "@far"
import { faSignOutAlt, faSensor, faLifeRing, faHeadset, faCommentAlt, faSignOut, faServer, faTasks, faRobot, faProjectDiagram, faLightbulbOn, faHatWizard, faParachuteBox } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed, inject, onBeforeUnmount, onMounted, ref } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { ctrans } from "@/Composables/useTrans"
import { isNavigationActive } from "@/Composables/useUrl"
import { Link } from "@inertiajs/vue3"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import DeskIcon from "@/Components/Messaging/DeskIcon.vue"

library.add(faTasks, faChevronLeft, faSignOutAlt, faSensor, faLifeRing, faHeadset, faCommentAlt, faSignOut, faServer, faRobot, faProjectDiagram, faLightbulbOn, faHatWizard, faParachuteBox)

const layout = inject("layout", layoutStructure)

// Set the LeftSidebar value to local storage
const handleToggleLeftBar = () => {
    if (typeof window !== "undefined") {
        localStorage.setItem("leftSideBar", (!layout.leftSidebar.show).toString())
    }
    layout.leftSidebar.show = !layout.leftSidebar.show
}

const chatRoute = computed(() => {
    const organisation = layout.currentParams?.organisation
    const shop = layout.currentParams?.shop
    const fulfilment = layout.currentParams?.fulfilment
    const isChatAgent = !!layout.user?.is_agent

    if (organisation && shop) {
        return {
            name: isChatAgent ? "grp.org.shops.show.chat.inbox" : "grp.org.shops.show.chat.reports",
            parameters: { organisation, shop },
            root: "grp.org.shops.show.chat.",
        }
    }

    if (organisation && fulfilment) {
        return {
            name: "grp.org.fulfilments.show.chat.reports",
            parameters: { organisation, fulfilment },
            root: "grp.org.fulfilments.show.chat.",
        }
    }

    if (organisation) {
        return {
            name: isChatAgent ? "grp.org.chat.inbox" : "grp.org.chat.reports",
            parameters: { organisation },
            root: "grp.org.chat.",
        }
    }

    return { name: isChatAgent ? "grp.chat.inbox" : "grp.chat.reports", parameters: {}, root: "grp.chat." }
})

const scopedModuleRoute = (module: string, page = "index") => {
    const organisation = layout.currentParams?.organisation
    const shop = layout.currentParams?.shop

    if (organisation && shop) {
        return { name: `grp.org.shops.show.${module}.${page}`, parameters: { organisation, shop }, root: `grp.org.shops.show.${module}.` }
    }

    if (organisation) {
        return { name: `grp.org.${module}.${page}`, parameters: { organisation }, root: `grp.org.${module}.` }
    }

    return { name: `grp.${module}.${page}`, parameters: {}, root: `grp.${module}.` }
}

const tasksRoute = computed(() => scopedModuleRoute("tasks"))

const ticketsRoute = computed(() => scopedModuleRoute("tickets", "board"))

const bottomLinks = computed(() => allBottomLinks.value.filter((link) => !layout.user?.works_only_for_agents || [tasksRoute.value.name, ticketsRoute.value.name].includes(link.route)))

const isEngineer = computed(() => !!layout.user?.is_engineer)

const devopsProblems = computed<string[]>(() => layout.user?.devops_problems ?? [])

const devopsLink = computed(() => ({
    route: "grp.devops.dashboard",
    parameters: {},
    root: "grp.devops.",
    label: ctrans("Devops"),
    tooltip: devopsProblems.value.length ? devopsProblems.value.map((problem) => problem.replace(/`/g, "")).join(" • ") : ctrans("Devops: servers, database, backups, deploys and tests"),
    icon: "fal fa-server",
    problems: devopsProblems.value.length,
}))

const tasksLink = computed(() => ({ route: tasksRoute.value.name, parameters: tasksRoute.value.parameters, root: tasksRoute.value.root, label: ctrans("Tasks"), tooltip: ctrans("Tasks: ask a colleague or a department for something"), icon: "fal fa-tasks", problems: 0 }))

const allBottomLinks = computed(() => [
    { route: "grp.projects.index", parameters: {}, root: "grp.projects.", label: ctrans("Projects"), tooltip: ctrans("Projects: big pieces of work with milestones, tickets and tasks"), icon: "fal fa-project-diagram", problems: 0 },
    isEngineer.value ? devopsLink.value : tasksLink.value,
    { route: ticketsRoute.value.name, parameters: ticketsRoute.value.parameters, root: ticketsRoute.value.root, label: ctrans("Tickets"), tooltip: ctrans("Tickets: report a problem or ask for help"), icon: "fal fa-life-ring", problems: 0 },
    { route: chatRoute.value.name, parameters: chatRoute.value.parameters, root: chatRoute.value.root, label: ctrans("Chat"), tooltip: ctrans("Chat with customers and colleagues"), icon: "fal fa-comment-alt", problems: 0 },
    { route: "grp.chat.staff.feed", parameters: {}, root: "grp.chat.staff.", label: "Verba", tooltip: ctrans("Verba: messages, channels and calls with colleagues"), icon: "desk", problems: 0 },
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
</script>

<template>
    <div
        class="fixed top-0 md:flex md:flex-col md:inset-y-0 h-full transition-all duration-300 ease-in-out"
        :style="{
			paddingBottom: navigationPaddingBottom,
			'background-color': layout.app.navigation_theme[0],
			color: layout.app.navigation_theme[2],
		}"
        :class="[
			layout.leftSidebar.show ? 'w-8/12 md:w-48' : 'w-8/12 md:w-12',
			layout.hasTopBanner ? 'mt-11 lg:mt-16' : 'mt-11 lg:mt-10',
		]"
        id="leftSidebar">
        <!-- Toggle: collapse-expand LeftSideBar -->
        <div
            @click="handleToggleLeftBar"
            class="hidden absolute z-10 right-0 -translate-y-1/2 translate-x-1/2 w-8 lg:w-5 aspect-square border border-gray-300 rounded-full md:flex md:justify-center md:items-center cursor-pointer"
            :class="layout.hasTopBanner ? 'top-[calc(50dvh-2.75rem)] lg:top-[calc(50dvh-4rem)]' : 'top-[calc(50dvh-2.75rem)] lg:top-[calc(50dvh-2.5rem)]'"
            :title="layout.leftSidebar.show ? 'Collapse the bar' : 'Expand the bar'"
            :style="{
				'background-color': `color-mix(in srgb, ${layout.app.navigation_theme[0]} 85%, black)`,
				color: layout.app.navigation_theme[1],
			}">
            <div
                class="flex items-center justify-center transition-all duration-300 ease-in-out"
                :class="{ 'rotate-180': !layout.leftSidebar.show }">
                <FontAwesomeIcon
                    icon="far fa-chevron-left"
                    class="h-[10px] leading-none"
                    fixed-width aria-hidden="true"
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
                    :class="isNavigationActive(layout.currentRoute, link.root) && !(link.icon !== 'desk' && isNavigationActive(layout.currentRoute, 'grp.chat.staff.')) ? 'navigationActive' : 'navigation'"
                    v-tooltip="{
						content: layout.leftSidebar.show ? '' : link.label,
						delay: { show: 100, hide: 100 },
					}"
                    @start="() => (loadingRoute = link.route)"
                    @finish="() => (loadingRoute = null)">
                    <LoadingIcon v-if="loadingRoute === link.route" class="flex-shrink-0 h-4 w-4" />
                    <DeskIcon v-else-if="link.icon === 'desk'" mono class="flex-shrink-0 h-4 w-4" />
                    <FontAwesomeIcon
                        v-else
                        aria-hidden="true"
                        class="flex-shrink-0 h-4 w-4"
                        fixed-width
                        :icon="link.icon" />
                    <span v-if="link.problems" class="absolute left-5 top-0 flex h-3.5 min-w-3.5 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-semibold leading-none text-white" :aria-label="ctrans(':count problem(s)', { count: link.problems })">{{ link.problems }}</span>

                    <Transition name="slide-to-left">
						<span
                            v-if="layout.leftSidebar.show"
                            class="py-0.5 leading-none whitespace-nowrap"
                            :class="[
								layout.leftSidebar.show
									? 'truncate block md:block'
									: 'block md:hidden',
								link.icon === 'desk' && 'font-[Lobster] !font-normal text-[17px] tracking-[0.01em]',
							]">
							{{ link.label }}
						</span>
                        <span v-else class="leading-none whitespace-nowrap block md:hidden">
							{{ link.label }}
						</span>
                    </Transition>
                </Link>

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
