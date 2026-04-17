<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { ref, onMounted, onUnmounted, inject, computed, watch, defineAsyncComponent } from 'vue'
import { Link } from '@inertiajs/vue3'
import SearchBar from "@/Components/SearchBar.vue"
import Image from '@/Components/Image.vue'
import Popover from '@/Components/Popover.vue'
import NotificationList from '@/Components/NotificationList/NotificationList.vue'
import Profile from "@/Pages/Grp/Profile.vue"

import Button from "@/Components/Elements/Buttons/Button.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCircle } from '@fas'
import { faSparkles } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import AskBot from '@/Components/AskBot.vue'
import { faLampDesk, faHourglassStart, faHourglassHalf, faChevronDown, faChevronRight } from '@fal'
library.add(faCircle, faLampDesk, faSparkles, faHourglassStart, faHourglassHalf, faChevronDown, faChevronRight)

/* const Profile = defineAsyncComponent(() => import("@/Pages/Grp/Profile.vue")) */

const props = defineProps<{
    urlPrefix: string
}>()

const layout = inject('layout', layoutStructure)
const isAskBotEnabled =  import.meta.env.VITE_ASK_BOT_UI;
// const layoutStore = useLayoutStore()
const showSearchDialog = ref(false)
const showAskBot = ref(false)
const expandedOrgs = ref<Set<string>>(new Set())

const dispatchingBadge = computed(() => layout?.dispatching_waiting_badge ?? [])

watch(dispatchingBadge, (badge) => {
    badge.forEach(orgData => expandedOrgs.value.add(orgData.organisation.slug))
}, { immediate: true })

const totalWaitingCount = computed(() =>
    dispatchingBadge.value.reduce((total, org) =>
        total + org.warehouses.reduce((wTotal, wh) =>
            wTotal + wh.waiting_items.count + wh.waiting_items_still_picking.count, 0
        ), 0
    )
)

function toggleOrg(orgSlug: string) {
    if (expandedOrgs.value.has(orgSlug)) {
        expandedOrgs.value.delete(orgSlug)
    } else {
        expandedOrgs.value.add(orgSlug)
    }
}

onMounted(() => {
    if (typeof window !== 'undefined') {
        document.addEventListener('keydown', (event) => {

            if( ( isUserMac ? event.metaKey : event.ctrlKey ) && event.key === 'k') {
                event.preventDefault()
                showSearchDialog.value = !showSearchDialog.value
            }
            if ((isUserMac ? event.metaKey : event.ctrlKey) && event.shiftKey && event.key === 'K') {
                event.preventDefault();
                showAskBot.value = !showAskBot.value;
            }
        })
    }
})

onUnmounted(() => {
    document.removeEventListener('keydown', () => false)
})

const isUserMac = navigator.platform.includes('Mac')  // To check the user's Operating System

</script>

<template>
    <!-- Avatar Group -->
    <div class="flex justify-between gap-x-2">
        <div class="flex items-center gap-x-0 sm:gap-x-4 sm:divide-x divide-gray-200">
            <!-- Button: Search -->
            <button @click="showSearchDialog = !showSearchDialog" id="search"
                class="h-7 w-fit flex items-center justify-center gap-x-3 ring-1 ring-gray-300 rounded-md px-3 text-gray-500 hover:bg-gray-200 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500">
                <span class="sr-only">{{ trans("Search") }}</span>
                <FontAwesomeIcon aria-hidden="true" size="sm" icon="fa-regular fa-search" />
                <div class="hidden whitespace-nowrap md:flex items-center justify-end text-gray-500/80 tracking-tight space-x-1">
                    <span v-if="isUserMac" class="ring-1 ring-gray-400 bg-gray-100 px-2 leading-none text-xl rounded">⌘</span>
                    <span v-else class="ring-1 ring-gray-400 bg-gray-100 px-2 py-0.5 text-xs rounded">Ctrl</span>
                    <span class="ring-1 ring-gray-400 bg-gray-100 px-1.5 py-0.5 text-xs rounded">K</span>
                </div>
                <SearchBar v-model="showSearchDialog" />
            </button>

            <!-- Search: AI -->
            <button v-if="true" @click="showAskBot = !showAskBot" id="ask-bot"
                class="bg-gradient-to-tr from-pink-200 xvia-pink-200 to-pink-100 border-none ring-1 ring-fuchsia-400 h-7 w-fit flex items-center justify-center gap-x-3 rounded-md px-3">
                <div class="flex gap-x-1 items-center ">
                    <FontAwesomeIcon icon="fas fa-sparkles" class="text-pink-500" fixed-width aria-hidden="true" />
                    <h1 class="xanimate-linear xbg-gradient-to-r xfrom-fuchsia-300 text-pink-500 xto-fuchsia-300 xbg-[length:200%_auto] xbg-clip-text font-bold xtext-transparent">
                        <span class="">AI</span>
                    </h1>
                </div>

                <div class="hidden whitespace-nowrap md:flex items-center justify-end text-gray-500/80 tracking-tight space-x-1">
                    <span v-if="isUserMac" class="ring-1 ring-fuchsia-400 bg-fuchsia-50 text-fuchsia-500 px-2 leading-none text-xl rounded">⌘</span>
                    <span v-else class="ring-1 ring-fuchsia-400 bg-fuchsia-50 text-fuchsia-500 px-2 py-0.5 text-xs rounded">Ctrl</span>
                    <span class="ring-1 ring-fuchsia-400 bg-fuchsia-50 text-fuchsia-500 px-2 py-0.5 text-xs rounded">Shift</span>
                    <span class="ring-1 ring-fuchsia-400 bg-fuchsia-50 text-fuchsia-500 px-1.5 py-0.5 text-xs rounded">K</span>
                </div>
                <AskBot v-model="showAskBot" />
            </button>

            <div class="pl-2 sm:pl-4 flex items-center gap-x-2">

                <!-- Badge: Dispatching Waiting Items -->
                <div v-if="dispatchingBadge.length > 0" class="relative flex items-center mr-2">
                    <Popover width="w-80" position="right-0">
                        <template #button>
                            <div tabindex="-1" class="relative text-orange-500 hover:text-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-400 cursor-pointer">
                                <FontAwesomeIcon aria-hidden="true" icon="fal fa-hourglass-start" size="lg" />
                                <span
                                    v-if="totalWaitingCount > 0"
                                    class="absolute -top-1.5 -right-2 min-w-[16px] h-4 flex items-center justify-center bg-orange-500 text-white text-[10px] font-bold rounded-full px-0.5 leading-none"
                                >
                                    {{ totalWaitingCount > 99 ? '99+' : totalWaitingCount }}
                                </span>
                            </div>
                        </template>
                        <template #content="{ close }">
                            <div class="max-h-96 overflow-y-auto">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                    {{ trans('Dispatching Waiting Items') }}
                                </p>

                                <div v-for="orgData in dispatchingBadge" :key="orgData.organisation.slug" class="mb-2">
                                    <!-- Organisation Header (expandable) -->
                                    <button
                                        @click="toggleOrg(orgData.organisation.slug)"
                                        class="w-full flex items-center justify-between px-2 py-1.5 rounded hover:bg-gray-50 text-left"
                                    >
                                        <span class="text-sm font-medium text-gray-700">
                                            {{ orgData.organisation.name }}
                                            <span class="ml-1 text-xs text-gray-400">({{ orgData.organisation.code }})</span>
                                        </span>
                                        <FontAwesomeIcon
                                            :icon="expandedOrgs.has(orgData.organisation.slug) ? 'fal fa-chevron-down' : 'fal fa-chevron-right'"
                                            class="text-gray-400 text-xs"
                                        />
                                    </button>

                                    <!-- Warehouses (expanded) -->
                                    <div v-if="expandedOrgs.has(orgData.organisation.slug)" class="pl-3 mt-1 space-y-1">
                                        <div v-for="warehouse in orgData.warehouses" :key="warehouse.slug" class="space-y-1">
                                            <p class="text-xs text-gray-400 font-medium">{{ warehouse.name }}</p>

                                            <!-- Waiting Items (Blocked) -->
                                            <Link
                                                v-if="warehouse.waiting_items.count > 0"
                                                :href="route(warehouse.waiting_items.route.name, warehouse.waiting_items.route.parameters)"
                                                @click="close()"
                                                class="flex items-center justify-between px-2 py-1 rounded hover:bg-orange-50 group"
                                            >
                                                <div class="flex items-center gap-x-1.5 text-xs text-gray-600 group-hover:text-orange-700">
                                                    <FontAwesomeIcon icon="fal fa-hourglass-start" class="text-orange-400" fixed-width />
                                                    <span>{{ trans('Waiting Items') }}</span>
                                                </div>
                                                <span class="text-xs font-semibold text-orange-600 bg-orange-100 rounded-full px-1.5 py-0.5">
                                                    {{ warehouse.waiting_items.count }}
                                                </span>
                                            </Link>

                                            <!-- Waiting Items Still Picking -->
                                            <Link
                                                v-if="warehouse.waiting_items_still_picking.count > 0"
                                                :href="route(warehouse.waiting_items_still_picking.route.name, warehouse.waiting_items_still_picking.route.parameters)"
                                                @click="close()"
                                                class="flex items-center justify-between px-2 py-1 rounded hover:bg-yellow-50 group"
                                            >
                                                <div class="flex items-center gap-x-1.5 text-xs text-gray-600 group-hover:text-yellow-700">
                                                    <FontAwesomeIcon icon="fal fa-hourglass-half" class="text-yellow-500" fixed-width />
                                                    <span>{{ trans('Still Picking') }}</span>
                                                </div>
                                                <span class="text-xs font-semibold text-yellow-700 bg-yellow-100 rounded-full px-1.5 py-0.5">
                                                    {{ warehouse.waiting_items_still_picking.count }}
                                                </span>
                                            </Link>

                                            <div
                                                v-if="warehouse.waiting_items.count === 0 && warehouse.waiting_items_still_picking.count === 0"
                                                class="px-2 py-1 text-xs text-gray-400 italic"
                                            >
                                                {{ trans('No waiting items') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Popover>
                </div>

                <div @click="() => layout.stackedComponents.push({ component: Profile, data: { currentTab: 'todo' }})">
                    <Button label="To do" size="xs" :style="'tertiary'" />
                </div>

                <!-- Button: Notifications -->
                <div class="relative px-2 rounded-full flex items-center">
                    <Popover>
                        <template #button>
                            <div tabindex="-1" class="relative text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                <FontAwesomeIcon aria-hidden="true" icon="fa-regular fa-bell" size="lg" />
                                <FontAwesomeIcon v-if="layout?.notifications?.some(notif => !notif.read)" icon='fas fa-circle' class='animate-pulse text-blue-500 absolute top-[1px] -right-0.5 text-[6px]' fixed-width aria-hidden='true' />
                            </div>
                        </template>
                        <template #content="{ close }">
                            <div class="w-[450px]">
                                <NotificationList :close />
                            </div>
                        </template>
                    </Popover>
                </div>

                <!-- Button: Profile -->
                <div @click="layout.stackedComponents.push({ component: Profile})"
                    class="flex max-w-xs overflow-hidden items-center rounded-full bg-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-gray-500 cursor-pointer">
                    <span class="sr-only">{{ trans("Open user menu") }}</span>
                    <Image class="h-8 w-8 rounded-full" :src="layout.avatar_thumbnail" alt="" />
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.underline-gradient {
    background-image: linear-gradient(90deg, #bae6fd, #a78bfa, theme('colors.pink.500'), #a78bfa, #bae6fd);
}
</style>
