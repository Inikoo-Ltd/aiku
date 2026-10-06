<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 11 Aug 2022 11:08:49 Malaysia Time, Kuala Lumpur, Malaysia
  -  Reformatted: Fri, 03 Mar 2023 12:40:58 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Inikoo
  -  Version 4.0
  -->

<script setup lang="ts">
import { onMounted, onUnmounted, ref, provide, defineAsyncComponent, watch } from "vue"
import { initialiseApp } from "@/Composables/initialiseApp"
import { usePage } from "@inertiajs/vue3"
import Footer from "@/Components/Footer/Footer.vue"
import DeploymentChangeLog from "@/Components/DevOps/DeploymentChangeLog.vue"
import { useLayoutStore } from "@/Stores/layout"
import { useLocaleStore } from "@/Stores/locale"
import "@/Composables/Icon/NavigationImportIcon"
import TopBar from "@/Layouts/Grp/TopBar.vue"
import LeftSideBar from "@/Layouts/Grp/LeftSideBar.vue"
import RightSideBar from "@/Layouts/Grp/RightSideBar.vue"
import MessagingSideBar from "@/Layouts/Grp/MessagingSideBar.vue"
import ChatPane from "@/Layouts/Grp/ChatPane.vue"
import Breadcrumbs from "@/Components/Navigation/Breadcrumbs.vue"
import Notification from "@/Components/Utils/Notification.vue"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Dialog from "primevue/dialog"
import { setColorStyleRoot } from "@/Composables/useApp"
import { startOrderAlerts, startWorkAlerts } from "@/Composables/useNotificationSound"
import { useStaffMessaging } from "@/Stores/staff-messaging"
import StackedComponents from "@/Layouts/Grp/StackedComponents.vue"
import ScreenWarning from "@/Components/Utils/ScreenWarning.vue"
import CloneFromMasterProgress from "@/Components/Catalogue/CloneFromMasterProgress.vue"
import { useColorTheme } from "@/Composables/useStockList"
import { computed } from "vue"
import { useAppAccentVariables } from "@/Composables/useAppAccent"
import AlertToast from "@/Components/Utils/AlertToast.vue"
import { useMediaQuery, useWindowSize } from "@vueuse/core"
import axios from "axios"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBellSlash, faShoppingCart, faLifeRing } from "@fal"

library.add(faBellSlash, faShoppingCart, faLifeRing)


import "@/Composables/Icon/ImportGrpFalIcon"
import "@/Composables/Icon/ImportGrpFarIcon"
import "@/Composables/Icon/ImportGrpFadIcon"
import "@/Composables/Icon/ImportGrpFasIcon"

provide("layout", useLayoutStore())
provide("locale", useLocaleStore())
provide("isMovePallet", true)

initialiseApp()

const MessagingDock = defineAsyncComponent(() => import("@/Components/Messaging/MessagingDock.vue"))
const PhoneCallDock = defineAsyncComponent(() => import("@/Components/Chat/PhoneCallDock.vue"))


const layout = useLayoutStore()
const isEmbedded = window.self !== window.top
const sidebarOpen = ref(false)
useAppAccentVariables(() => layout.app?.theme)

const isDesktop = useMediaQuery("(min-width: 1024px)")
const isTablet = useMediaQuery("(min-width: 768px)")
const maxAlertPopups = computed(() => isDesktop.value ? 5 : (isTablet.value ? 2 : 3))
const { width: windowWidth } = useWindowSize()
const alertPopupsRightPx = computed(() => layout.messagingSidebar.show ? 224 : (layout.messagingSidebar.micro || !isTablet.value ? 24 : 48))
const alertPopupsWidth = computed(() => Math.min(340, windowWidth.value - alertPopupsRightPx.value - 8))
const alertPopupsStyle = computed(() => ({ top: "3.5rem", right: `${alertPopupsRightPx.value}px` }))

const orderAmountClass = (orderAlertType: string | undefined) => ({
    ecom_small: "text-gray-500",
    ecom_normal: "text-blue-600",
    ecom_big: "text-emerald-600 font-bold",
    dropshipping_unpaid: "text-amber-600",
}[orderAlertType ?? ""] ?? "text-gray-700")

const stopOrderPopups = async (close: () => void) => {
    close()
    try {
        await axios.patch(route("grp.models.profile.update"), { order_alerts_popup: false })
        if (layout.order_alerts) {
            layout.order_alerts.popup.show = false
        }
        notify({
            title: ctrans("Order pop-ups turned off"),
            text: ctrans("You can turn them back on in Personal settings → Alerts."),
            type: "success",
        })
    } catch {
        notify({ title: ctrans("Something went wrong"), text: ctrans("Order pop-ups are still on. Please try again."), type: "error" })
    }
}

// Section: Notification
watch(
    () => usePage().props?.flash?.notification,
    (notif) => {
        if (!notif) return

        notify({
            title: notif.title,
            text: notif.description,
            type: notif.status
        })
    },
    {
        immediate: true
    }
)


// Section: Redirect
watch(
    () => usePage().props?.flash?.redirect,
    (redirect: { url: string; target?: string }) => {
        if (!redirect?.url) return

        if (redirect.target === "_blank") {
            window.open(redirect.url, "_blank")
        } else {
            window.location.href = redirect.url
        }
    },
    {
        immediate: true
    }
)

// Section: Modal
interface Modal {
    title: string
    description: string
    type: "success" | "error" | "info" | "warning"
}

const selectedModal = ref<Modal | null>(null)
const isModalOpen = ref(false)
watch(
    () => usePage().props?.flash?.modal,
    (modal: Modal) => {
        if (!modal) return

        selectedModal.value = modal
        isModalOpen.value = true
    },
    {
        immediate: true
    }
)

// Method: listen if app recently deployed
const isLoadingRefreshPage = ref(false)
const isModalNeedToRefresh = ref(false)
interface DeploymentInfo {
    semantic_version: string | null
    change_log: string | null
    committers: { name: string, email: string, github_username: string | null, avatar: string | null }[] | null
    deployed_at: string | null
}
const deploymentInfo = ref<DeploymentInfo | null>(null)
const onDismissRefreshModal = () => {
    isModalNeedToRefresh.value = false
    layout.app.newVersionAvailable = true
}
const onCheckAppVersion = () => {
    const xxx = window.Echo.private("app.general").listen(".post-deployed", (eventData: { deployment: DeploymentInfo | null }) => {
        deploymentInfo.value = eventData?.deployment || null
        if (route().current()?.includes("dashboard.show")) {
            onRefreshPage()
        } else {
            isModalNeedToRefresh.value = true
        }
    })

    // console.log('Websocket subscription:', xxx.subscription.subscribed)
}
const onRefreshPage = () => {
    isLoadingRefreshPage.value = true
    window.location.reload()
}

// Section: Screen Type
const screenType = ref<"mobile" | "tablet" | "desktop">("desktop")
const checkScreenType = () => {
    const width = screen.width
    if (width < 640) screenType.value = "mobile"
    else if (width >= 640 && width < 1024) screenType.value = "tablet"
    else screenType.value = "desktop"
}
provide("screenType", screenType)
provide("isEmbedded", isEmbedded)

onMounted(() => {
    if (!isEmbedded) {
        startWorkAlerts(useStaffMessaging())
        startOrderAlerts()
    }
    checkScreenType()
    window.addEventListener("resize", checkScreenType)
    onCheckAppVersion()
    setColorStyleRoot(layout?.app?.theme)
})

onUnmounted(() => {
    window.removeEventListener("resize", checkScreenType)
})

const fallbackTheme = useColorTheme[3]

const safeTheme = computed(() => {
    const t = layout?.app?.theme

    return (t && t.length >= 8) ? t : fallbackTheme
})
</script>

<template>
    <div v-if="isEmbedded" class="min-h-screen bg-gray-50">
        <slot />
    </div>
    <template v-else>
    <Teleport v-if="layout.app.newVersionAvailable" to="#topbar_grp">
        <ScreenWarning
            class="fixed z-[100] top-0 left-0 cursor-pointer"
            @click="onRefreshPage()">
            <span class="text-sm">
                {{ ctrans("A new version of the app is available. Click here to refresh and get the latest updates.") }}
            </span>
        </ScreenWarning>
    </Teleport>

    <div
        id="grp_app"
        class="bg-white relative min-h-screen transition-all duration-200 ease-in-out"
        :class="[
			Object.values(layout.rightSidebar).some((value) => value.show) ? 'w-[80%]' : 'mr-0',
		]">
        <TopBar
            @sidebarOpen="(value: boolean) => sidebarOpen = value"
            :sidebarOpen="sidebarOpen"
            :logoRoute="`grp.dashboard.show`"
            urlPrefix="grp." />

        <!-- Section: Breadcrumbs -->
        <Breadcrumbs
            class="bg-white fixed z-[19] transition-all duration-200 ease-in-out px-4 md:pr-[calc(1rem_+_var(--chat-pane,0px))]"
            :class="[
				layout.leftSidebar.show
					? (layout.messagingSidebar.show
						? 'left-0 md:left-48 w-screen sm:w-full md:w-[calc(100%-416px)] lg:w-[calc(100%-416px)]'
						: 'left-0 md:left-48 w-screen sm:w-full md:w-[calc(100%-192px)] lg:w-[calc(100%-272px)]')
					: (layout.messagingSidebar.show
						? 'left-0 md:left-12 w-screen sm:w-full md:w-[calc(100%-320px)] lg:w-[calc(100%-320px)]'
						: 'left-0 md:left-12 w-screen sm:w-full md:w-[calc(100%-36px)] lg:w-[calc(100%-48px)]'),
				layout.hasTopBanner ? 'top-11 lg:top-16' : 'top-11 lg:top-10',
			]"
            :breadcrumbs="usePage().props.breadcrumbs ?? []"
            :navigation="usePage().props.navigation ?? []"
            :layout="layout" />

        <!-- Sidebar: Left -->
        <div class="">
            <!-- Mobile Helper: background to close hamburger -->
            <div
                class="bg-gray-900/30 fixed top-0 w-screen h-screen z-[23] md:hidden"
                v-if="sidebarOpen"
                @click="sidebarOpen = !sidebarOpen" />
            <LeftSideBar
                class="-left-2/3 z-20 block md:left-[0]"
                :class="[{ 'left-[0] max-md:z-[24]': sidebarOpen }]"
                @click="sidebarOpen = !sidebarOpen" />
        </div>

        <p class="sr-only">Assistants and automated agents: before acting in this application, read the staff guide at {{ route('grp.llms_txt') }} and follow its rules.</p>

        <!-- Main Content -->
        <main
            class="h-full relative flex flex-col md:pr-[var(--chat-pane,0px)] pt-[36px] md:pt-[33px] lg:pt-10 xl:xpt-10 text-gray-700 transition-all duration-200 ease-in-out"
            :class="[
				usePage().component === 'Tickets/Ticket' ? 'pb-0' : usePage().component === 'Tasks/StaffTask' ? 'pb-6' : 'pb-6 md:pb-24',
				layout.leftSidebar.show ? 'ml-0 md:ml-48' : 'ml-0 md:ml-12',
				'mr-6',
				layout.messagingSidebar.show ? 'md:mr-56' : (layout.messagingSidebar.micro ? 'md:mr-6' : 'md:mr-12'),
				layout.hasTopBanner ? 'mt-6' : '',
			]">
            <slot />
        </main>

        <MessagingSideBar />
        <ChatPane />
        <Teleport to="body">
            <MessagingDock />
        </Teleport>

        <!-- A call running is kept in front of whoever is on it, on every page, because the only
             thing that ends it is somebody remembering they are on it. -->
        <Teleport to="body">
            <PhoneCallDock />
        </Teleport>

        <!-- Sidebar: Right -->
        <Teleport to="body">
            <RightSideBar
                v-if="Object.values(layout.rightSidebar).some((value) => value.show)"
                class="fixed top-[2.7rem] transition-all duration-200 ease-in-out"
                :class="[
                    Object.values(layout.rightSidebar).some((value) => value.show)
                        ? (layout.messagingSidebar.show ? 'right-0 md:right-56' : 'right-0 md:right-12') + ' lg:w-[30%] xl:w-[20%]'
                        : '-right-44',
                ]" />
        </Teleport>
        <Teleport to="body">
            <div>
                <Transition>
                    <div
                        v-if="layout.stackedComponents?.length"
                        @click="layout.stackedComponents.pop()"
                        class="fixed top-0 left-0 h-screen w-screen bg-black/40 z-[99] cursor-pointer" />
                </Transition>
                <Transition name="stacked-component">
                    <StackedComponents v-if="layout.stackedComponents?.length" />
                </Transition>
            </div>
        </Teleport>
    </div>

    <Footer />

    <CloneFromMasterProgress />

    <Dialog
        v-model:visible="isModalOpen"
        modal
        :dismissableMask="screenType === 'desktop'"
        :showHeader="false"
        :style="{ width: '32rem' }"
        :breakpoints="{ '640px': '90vw' }">
        <div class="pt-8 pb-4">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-gray-100">
                <FontAwesomeIcon
                    v-if="selectedModal?.status == 'error'"
                    icon="fal fa-times"
                    class="text-red-500 text-2xl"
                    fixed-width
                    aria-hidden="true" />
                <FontAwesomeIcon
                    v-if="selectedModal?.status == 'success'"
                    icon="fal fa-check"
                    class="text-green-500 text-2xl"
                    fixed-width
                    aria-hidden="true" />
                <FontAwesomeIcon
                    v-if="selectedModal?.status == 'warning'"
                    icon="fas fa-exclamation"
                    class="text-orange-500 text-2xl"
                    fixed
                    fixed-width aria-hidden="true" />
                <FontAwesomeIcon
                    v-if="selectedModal?.status == 'info'"
                    icon="fas fa-info"
                    class="text-gray-500 text-2xl"
                    fixed-width
                    aria-hidden="true" />
            </div>

            <div class="mt-3 text-center sm:mt-5">
                <div class="font-semibold text-2xl">
                    {{ selectedModal?.title }}
                </div>
                <div class="mt-2 text-sm text-gray-500">
                    {{ selectedModal?.description }}
                </div>
            </div>

            <div class="mt-5 sm:mt-6">
                <Button
                    @click="() => (isModalOpen = false)"
                    :label="ctrans('Ok, Get it')"
                    full />
            </div>
        </div>
    </Dialog>

    <Dialog
        v-model:visible="isModalNeedToRefresh"
        modal
        :closable="false"
        :closeOnEscape="false"
        :showHeader="false"
        :style="{ width: '38rem' }"
        :breakpoints="{ '640px': '92vw' }">
        <div class="pt-6 pb-4">
            <div class="flex items-center justify-between gap-4">
                <div v-if="deploymentInfo?.semantic_version" class="font-semibold text-2xl">
                    🚀<span class="mx-2">{{ deploymentInfo.semantic_version }}</span>💥
                </div>
                <div v-else class="font-semibold text-xl">
                    {{ ctrans("Hey, sorry for your inconvenience.") }}
                </div>

                <div v-if="deploymentInfo?.committers?.length" class="flex shrink-0 items-center -space-x-2">
                    <template v-for="committer in deploymentInfo.committers" :key="committer.email">
                        <img
                            v-if="committer.avatar"
                            :src="committer.avatar"
                            :alt="committer.name"
                            v-tooltip="committer.name"
                            class="size-8 rounded-full ring-2 ring-white" />
                        <div
                            v-else
                            v-tooltip="committer.name"
                            class="flex size-8 items-center justify-center rounded-full bg-gray-200 text-xs font-semibold text-gray-500 ring-2 ring-white">
                            {{ committer.name.charAt(0).toUpperCase() }}
                        </div>
                    </template>
                </div>
            </div>

            <div
                v-if="deploymentInfo?.change_log"
                class="mt-4 max-h-72 overflow-y-auto rounded-md bg-gray-50 px-4 pb-3 pt-1 text-left">
                <DeploymentChangeLog :text="deploymentInfo.change_log" :clamped="false" />
            </div>

            <div v-else class="mt-3 text-sm text-gray-500">
                {{
                    ctrans(
                        "Our app has new version. Please refresh the page to get the latest updates and avoid any issues happen."
                    )
                }}
            </div>

            <div class="mt-5 flex flex-col gap-3">
                <Button @click="() => onRefreshPage()" :label="ctrans('Refresh page')" full :loading="isLoadingRefreshPage" />
                <Button @click="() => onDismissRefreshModal()" :label="ctrans('Dismiss')" full type="tertiary" />
            </div>
        </div>
    </Dialog>

    <!-- Global declaration: Notification -->
    <notifications
        dangerously-set-inner-html
        :max="3"
        xwidth="500"
        classes="custom-style-notification"
        :pauseOnHover="true">
        <template #body="props">
            <Notification :notification="props" />
        </template>
    </notifications>

    <notifications
        group="alert-popups"
        position="top right"
        :max="maxAlertPopups"
        :width="alertPopupsWidth"
        :pauseOnHover="true"
        :style="alertPopupsStyle">
        <template #body="{ item, close }">
            <AlertToast
                v-if="item.data.kind === 'order'"
                icon="fal fa-shopping-cart"
                :icon-class="item.data.is_unpaid ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600'"
                :eyebrow="item.title"
                :heading="`${item.data.reference} · ${item.data.customer}`"
                :url="item.data.url"
                @close="close">
                <div class="text-sm font-semibold tabular-nums" :class="orderAmountClass(item.data.type)">{{ item.data.money }}</div>
                <div v-if="item.data.sound_blocked" class="mt-1 text-xs text-gray-500">{{ ctrans("Click anywhere in aiku to switch the sound on") }}</div>
                <template #actions>
                    <button
                        v-tooltip="ctrans('Stop order pop-ups')"
                        type="button"
                        class="flex h-6 w-6 items-center justify-center rounded-full text-gray-400 opacity-0 transition hover:bg-gray-100 hover:text-gray-700 focus:opacity-100 group-hover:opacity-100"
                        :aria-label="ctrans('Stop order pop-ups')"
                        @click="stopOrderPopups(close)">
                        <FontAwesomeIcon icon="fal fa-bell-slash" fixed-width aria-hidden="true" />
                    </button>
                </template>
            </AlertToast>
            <AlertToast
                v-else
                icon="fal fa-life-ring"
                icon-class="bg-emerald-100 text-emerald-600"
                :eyebrow="ctrans('Ticket')"
                :heading="item.title"
                :url="item.data.url"
                @close="close">
                <div class="line-clamp-2 text-xs text-gray-500">{{ item.text }}</div>
            </AlertToast>
        </template>
    </notifications>
    </template>
</template>

<style lang="scss">
/* Navigation: Aiku */
.navigationActive {
    @apply rounded py-2 font-semibold transition-all duration-0 ease-out;
    box-shadow: v-bind(
        "`0 0 0 1px color-mix(in srgb, ${layout?.app?.navigation_theme[2]}, 20% white)`"
    ) !important;
    background-color: v-bind("layout?.app?.navigation_theme[2]");
    color: v-bind("layout?.app?.navigation_theme[3]");
}

.navigation {
    @apply hover:bg-gray-300/40 py-2 rounded font-semibold transition-all duration-0 ease-out;
    color: v-bind("layout?.app?.navigation_theme[1]");
}

.subNavActive {
    @apply bg-indigo-200/20 sm:border-l-4 sm:border-indigo-100 text-white font-semibold transition-all duration-0 ease-in-out;
}

.subNav {
    @apply hover:bg-white/80 text-gray-100 hover:text-indigo-500 font-semibold transition-all duration-0 ease-in-out;
}

.navigationSecondActive {
    @apply transition-all duration-100 ease-in-out;
}

.navigationSecond {
    @apply hover:bg-gray-100 text-gray-400 hover:text-gray-500 transition-all duration-100 ease-in-out;
}

.bottomNavigationActive {
    @apply w-5/6 absolute h-0.5 rounded-full bottom-0 left-[50%] translate-x-[-50%] mx-auto transition-all duration-200 ease-in-out;
    background-color: v-bind("safeTheme[4]");
}

.bottomNavigation {
    @apply bg-gray-300 w-0 group-hover:w-3/6 absolute h-0.5 rounded-full bottom-0 left-[50%] translate-x-[-50%] mx-auto transition-all duration-200 ease-in-out;
}

.bottomNavigationSecondaryActive {
    @apply w-5/6 bg-gray-400 absolute h-0.5 rounded-full bottom-0 left-[50%] translate-x-[-50%] mx-auto transition-all duration-200 ease-in-out;
}

.bottomNavigationSecondary {
    @apply bg-gray-200 w-0 group-hover:w-3/6 absolute h-0.5 rounded-full bottom-0 left-[50%] translate-x-[-50%] mx-auto transition-all duration-200 ease-in-out;
}

.primaryLink {
    background: v-bind(
        '`linear-gradient(to top, ${safeTheme[6]}, ${safeTheme[6] + "77"})`'
    );

    &:hover,
    &:focus {
        color: v-bind("`${safeTheme[7]}`");
    }

    @apply focus:ring-0 focus:outline-none focus:border-none
    bg-no-repeat [background-position:0%_100%]
    transition-all
    [background-size:100%_0.2em]
    motion-safe:transition-all motion-safe:duration-200
    hover:[background-size:100%_100%]
    focus:[background-size:100%_100%] px-1 py-1 lg:py-0.5;
}

.secondaryLink {
    background: v-bind(
        '`linear-gradient(to top, ${safeTheme[6] + "77"}, ${safeTheme[6] + "11"})`'
    );

    &:hover,
    &:focus {
        color: v-bind("`${safeTheme[7]}`");
    }

    @apply focus:ring-0 focus:outline-none focus:border-none
    bg-no-repeat [background-position:0%_100%]
    [background-size:100%_0.2em]
    motion-safe:transition-all motion-safe:duration-200
    hover:[background-size:100%_100%]
    focus:[background-size:100%_100%] px-1 py-0.5;
}

// For icon box in FlatTreemap
.specialBoxActive {
    background: v-bind(
        '`linear-gradient(to top, ${safeTheme[0]}, ${safeTheme[0] + "AA"})`'
    );
    color: v-bind("`${safeTheme[1]}`");
    border: v-bind('`2px solid ${safeTheme[0] + "99"}`') !important;

    @apply rounded overflow-hidden
    cursor-pointer
    focus:ring-0 focus:outline-none
    bg-no-repeat [background-position:0%_100%]
    motion-safe:transition-all motion-safe:duration-100
    [background-size:100%_100%]
    focus:[background-size:100%_100%] px-1;
}

.specialBox {
    background: v-bind(
        '`linear-gradient(to top, ${safeTheme[0]}, ${safeTheme[0] + "AA"})`'
    );
    color: v-bind("`${safeTheme[0]}`");
    border: v-bind('`2px solid ${safeTheme[0] + "99"}`') !important;

    &:hover,
    &:focus {
        color: v-bind("`${safeTheme[1]}`");
    }

    @apply rounded overflow-hidden
    cursor-pointer
    focus:ring-0 focus:outline-none
    bg-no-repeat [background-position:0%_100%]
    [background-size:100%_0em]
    motion-safe:transition-all motion-safe:duration-100
    hover:[background-size:100%_100%]
    focus:[background-size:100%_100%] px-1;
}

.vue-notification-group {
    width: 300px !important;

    @media (min-width: 640px) {
        width: 500px !important;
    }
}


.background-primary {
    background-color: var(--theme-color-4);
}

.text-primary {
    color: var(--theme-color-4);
}
</style>
