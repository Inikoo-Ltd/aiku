<script setup lang='ts'>
import { ctrans } from '@/Composables/useTrans'
import { defineAsyncComponent, inject } from 'vue'
import Image from "@common/Components/Image.vue";
import Popover from '@/Components/Popover.vue'
const Profile = defineAsyncComponent(() => import("@/Pages/Grp/Profile.vue"))
import WaitingWarehouseList from "@/Layouts/Grp/WaitingWarehouseList.vue"
import WaitingCrmList from "@/Layouts/Grp/WaitingCrmList.vue"

import { layoutStructure } from "@/Composables/useLayoutStructure"
import { capitalize } from "@/Composables/capitalize"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCircle } from '@fas'
import { faLifeRing, faShoppingCart, faCube } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import ReturnCrmList from './ReturnCrmList.vue';
import MasterUpdatedList from './MasterUpdatedList.vue';
import ProductsNeedReviewList from './ProductsNeedReviewList.vue';
import FaireSkippedList from './FaireSkippedList.vue';
import TicketBadgeList from './TicketBadgeList.vue';
import TaskBadgeList from './TaskBadgeList.vue';
import CreatedTaskBadgeList from './CreatedTaskBadgeList.vue';
import RailBadgeVisibilityToggle from './RailBadgeVisibilityToggle.vue';
import axios from 'axios'
import { faTasks } from '@fal'
import CustomersWaiting from './CustomersWaiting.vue';
import WhatsappCallAlert from './WhatsappCallAlert.vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useMediaQuery } from '@vueuse/core'
library.add(faCircle, faLifeRing, faShoppingCart, faCube)

const layout = inject('layout', layoutStructure)

const sumCounts = (rows: Record<string, { count: number }> | null | undefined, keys?: string[]) =>
    Object.entries(rows ?? {}).filter(([key]) => !keys || keys.includes(key)).reduce((total, [, row]) => total + row.count, 0)

const myTicketsCount = computed(() => sumCounts(layout.ticket_badges?.mine, ['to_do', 'in_progress', 'waiting']))
const myTicketsWaiting = computed(() => layout.ticket_badges?.mine?.waiting?.count ?? 0)
const myTicketsUnread = computed(() => (layout.ticket_badges?.recent ?? []).filter((update) => !update.read).length)
const queueCount = computed(() => (layout.ticket_badges?.queue?.assigned_to_me?.count ?? 0) + (layout.ticket_badges?.queue?.collaborating?.count ?? 0))
const queueOverdue = computed(() => layout.ticket_badges?.queue?.overdue?.count ?? 0)
const hasTicketBadges = computed(() => Boolean(layout.ticket_badges?.queue) || Boolean(layout.ticket_badges?.mine && (myTicketsCount.value > 0 || myTicketsUnread.value > 0)))
const hiddenBadgesStorageKey = () => `rail-hidden-badges:${layout.user?.id ?? 'guest'}`

const readCachedHiddenBadges = (): string[] | null => {
    try {
        const cached = JSON.parse(localStorage.getItem(hiddenBadgesStorageKey()) ?? 'null')
        return Array.isArray(cached) ? cached.filter((key): key is string => typeof key === 'string') : null
    } catch {
        return null
    }
}

const cacheHiddenBadges = (keys: string[]) => {
    try {
        localStorage.setItem(hiddenBadgesStorageKey(), JSON.stringify(keys))
    } catch { }
}

const hiddenBadges = ref<string[]>(readCachedHiddenBadges() ?? [...(layout.user?.settings?.rail_hidden_badges ?? [])])
const isHiddenBadge = (key: string) => hiddenBadges.value.includes(key)
const isOffRail = (key: string) => !layout.messagingSidebar.show && isHiddenBadge(key)
const dimmedClass = (key: string) => layout.messagingSidebar.show && isHiddenBadge(key) ? '[&>:not(.rail-eye)]:opacity-40' : ''

watch(() => layout.user?.settings?.rail_hidden_badges, (savedKeys) => {
    if (!Array.isArray(savedKeys)) return
    hiddenBadges.value = [...savedKeys]
    cacheHiddenBadges(hiddenBadges.value)
}, { immediate: true })

const toggleBadge = (key: string) => {
    hiddenBadges.value = isHiddenBadge(key) ? hiddenBadges.value.filter((hidden) => hidden !== key) : [...hiddenBadges.value, key]
    cacheHiddenBadges(hiddenBadges.value)
    if (layout.user?.settings) layout.user.settings.rail_hidden_badges = hiddenBadges.value
    axios.patch(route('grp.models.profile.update'), { rail_hidden_badges: hiddenBadges.value })
}

const shownOnRail = (key: string, isShown: boolean) => isShown && !isOffRail(key)

const myTasksCount = computed(() => sumCounts(layout.task_badges?.mine, ['todo', 'in_progress']))
const myTasksOverdue = computed(() => layout.task_badges?.mine?.overdue?.count ?? 0)
const myTasksUnread = computed(() => (layout.task_badges?.recent ?? []).filter((update) => !update.read).length)
const hasTaskBadges = computed(() => Boolean(layout.task_badges) && (sumCounts(layout.task_badges?.mine) > 0 || myTasksUnread.value > 0))
const createdTasks = computed(() => layout.task_badges?.created ?? null)
const myTasksOnRail = computed(() => hasTaskBadges.value && shownOnRail('tasks', true))
const createdTasksOnRail = computed(() => (createdTasks.value?.open ?? 0) > 0 && shownOnRail('tasks_created', true))
const hasOrderBadges = computed(() => (layout?.dispatching_waiting_count ?? 0) + (layout?.crm_waiting_count ?? 0) + (layout?.crm_return_count ?? 0) + (layout?.faire_skipped_count ?? 0) > 0)
const hasCatalogueBadges = computed(() => (layout?.master_updated_count ?? 0) + (layout?.products_need_review_count ?? 0) > 0)
const ticketsOnRail = computed(() => shownOnRail('tickets_queue', Boolean(layout.ticket_badges?.queue)) || shownOnRail('tickets_mine', Boolean(layout.ticket_badges?.mine && (myTicketsCount.value > 0 || myTicketsUnread.value > 0))))
const ordersOnRail = computed(() => ([['dispatching_waiting', layout?.dispatching_waiting_count], ['crm_waiting', layout?.crm_waiting_count], ['crm_return', layout?.crm_return_count], ['faire_skipped', layout?.faire_skipped_count]] as [string, number][]).some(([key, count]) => shownOnRail(key, (count ?? 0) > 0)))
const catalogueOnRail = computed(() => ([['master_updated', layout?.master_updated_count], ['products_need_review', layout?.products_need_review_count]] as [string, number][]).some(([key, count]) => shownOnRail(key, (count ?? 0) > 0)))

type BadgeGroup = 'orders' | 'catalogue'

const GROUP_IDLE_MS = 5000

const isShortScreen = useMediaQuery('(max-height: 800px)')
const isCompact = computed(() => !layout.messagingSidebar.show && isShortScreen.value)
const expandedGroup = ref<BadgeGroup | null>(null)
const isPointerInside = ref(false)
const controlsElement = ref<HTMLElement | null>(null)
let collapseTimer: ReturnType<typeof setTimeout> | null = null

const ordersCount = computed(() => (layout?.dispatching_waiting_count ?? 0) + (layout?.crm_waiting_count ?? 0) + (layout?.crm_return_count ?? 0) + (layout?.faire_skipped_count ?? 0))
const catalogueCount = computed(() => (layout?.master_updated_count ?? 0) + (layout?.products_need_review_count ?? 0))

const isFolded = (group: BadgeGroup) => isCompact.value && expandedGroup.value !== group

const tasksOnRail = computed(() => myTasksOnRail.value || createdTasksOnRail.value)
const ordersSectionShown = computed(() => (isFolded('orders') ? ordersCount.value > 0 : ordersOnRail.value))
const catalogueSectionShown = computed(() => (isFolded('catalogue') ? catalogueCount.value > 0 : catalogueOnRail.value))

const clearCollapseTimer = () => {
    if (collapseTimer) {
        clearTimeout(collapseTimer)
        collapseTimer = null
    }
}

const scheduleCollapse = () => {
    clearCollapseTimer()

    if (!expandedGroup.value || isPointerInside.value) {
        return
    }

    collapseTimer = setTimeout(() => {
        collapseTimer = null

        if (controlsElement.value?.querySelector('[data-headlessui-state="open"]')) {
            scheduleCollapse()

            return
        }

        expandedGroup.value = null
    }, GROUP_IDLE_MS)
}

const expandGroup = (group: BadgeGroup) => {
    expandedGroup.value = group
    scheduleCollapse()
}

const onPointerEnter = () => {
    isPointerInside.value = true
    clearCollapseTimer()
}

const onPointerLeave = () => {
    isPointerInside.value = false
    scheduleCollapse()
}

onBeforeUnmount(clearCollapseTimer)

// ponytail: only ever mounted inside MessagingSideBar, so read the expand state straight off layout instead of threading a prop
</script>

<template>
    <div
        ref="controlsElement"
        class="border-b border-[var(--chat-line)] flex-shrink-0"
        :class="layout.messagingSidebar.show ? 'px-2 py-2 space-y-2' : 'flex flex-col items-center gap-y-3 py-3'"
        @pointerenter="onPointerEnter"
        @pointerleave="onPointerLeave">
        <div :class="layout.messagingSidebar.show ? 'flex items-center gap-2 min-w-0' : 'contents'">
        <!-- Button: Profile -->
        <div @click="layout.stackedComponents.push({ component: Profile})"
            class="flex overflow-hidden items-center rounded-full bg-[var(--chat-line)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--chat-accent)] cursor-pointer shrink-0"
            :class="layout.messagingSidebar.show ? '' : 'order-first'">
            <span class="sr-only">{{ ctrans("Open user menu") }}</span>
            <Image class="h-8 w-8 rounded-full" :src="layout.avatar_thumbnail" alt="" />
        </div>
        <span v-if="layout.messagingSidebar.show" v-tooltip="capitalize(layout.user?.contact_name || layout.user?.username)" class="min-w-0 flex-1 truncate text-sm text-white xtext-[var(--chat-muted)]">{{ capitalize(layout.user?.contact_name || layout.user?.username) }}</span>
        </div>

        <div v-if="layout.messagingSidebar.show" class="border-t border-[var(--chat-line)]" aria-hidden="true" />

        <div :class="layout.messagingSidebar.show ? 'flex flex-col gap-2' : 'contents'">

        <WhatsappCallAlert />
        <CustomersWaiting />

        <div
            v-if="ticketsOnRail"
            class="shrink-0"
            :class="layout.messagingSidebar.show ? 'flex flex-wrap items-center gap-2' : 'flex flex-col items-center'">
        <FontAwesomeIcon icon="fal fa-life-ring" class="w-4 shrink-0 text-center text-white xtext-[var(--chat-muted)] text-xs" :class="layout.messagingSidebar.show ? '' : 'mb-1'" fixed-width v-tooltip="ctrans('Tickets')" aria-hidden="true" />
        <!-- Badge: Ticket work queue (engineers and QA) -->
        <div v-if="shownOnRail('tickets_queue', Boolean(layout.ticket_badges?.queue))" class="relative flex items-center justify-center shrink-0" :class="dimmedClass('tickets_queue')">
            <RailBadgeVisibilityToggle v-if="layout.messagingSidebar.show" :hidden="isHiddenBadge('tickets_queue')" @toggle="toggleBadge('tickets_queue')" />
            <Popover width="w-72" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div v-tooltip="ctrans('Tickets assigned to me')" class="relative w-8 h-8 flex items-center justify-center opacity-80 hover:opacity-100 cursor-pointer font-medium tabular-nums bg-lime-300 text-lime-900" :class="!layout.messagingSidebar.show && layout.ticket_badges?.mine && (myTicketsCount > 0 || myTicketsUnread > 0) ? 'rounded-t-xl' : 'rounded-xl'">
                        <Transition name="spin-to-right"><span :key="queueCount"><span :class="queueCount > 99 ? 'text-xxs' : 'text-xs'">{{ queueCount > 99 ? '99+' : queueCount }}</span></span></Transition>
                        <FontAwesomeIcon v-if="queueOverdue" icon="fas fa-circle" class="absolute top-0 -right-0.5 text-fuchsia-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                    </div>
                </template>
                <template #content="{ close }">
                    <TicketBadgeList :title="ctrans('Tickets to fix')" :rows="layout.ticket_badges.queue" :close="close" />
                </template>
            </Popover>
        </div>

        <!-- Badge: My tickets -->
        <div v-if="shownOnRail('tickets_mine', Boolean(layout.ticket_badges?.mine && (myTicketsCount > 0 || myTicketsUnread > 0)))" class="relative flex items-center justify-center shrink-0" :class="dimmedClass('tickets_mine')">
            <RailBadgeVisibilityToggle v-if="layout.messagingSidebar.show" :hidden="isHiddenBadge('tickets_mine')" @toggle="toggleBadge('tickets_mine')" />
            <Popover width="w-72" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div v-tooltip="ctrans('My tickets')" class="relative w-8 h-8 flex items-center justify-center opacity-80 hover:opacity-100 cursor-pointer font-medium tabular-nums bg-lime-100 text-lime-900" :class="!layout.messagingSidebar.show && layout.ticket_badges?.queue ? 'rounded-b-xl' : 'rounded-xl'">
                        <Transition name="spin-to-right"><span :key="myTicketsCount"><span :class="myTicketsCount > 99 ? 'text-xxs' : 'text-xs'">{{ myTicketsCount > 99 ? '99+' : myTicketsCount }}</span></span></Transition>
                        <FontAwesomeIcon v-if="myTicketsWaiting || myTicketsUnread" icon="fas fa-circle" class="absolute top-0 -right-0.5 text-lime-400 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                    </div>
                </template>
                <template #content="{ close }">
                    <TicketBadgeList :title="ctrans('My tickets')" :rows="layout.ticket_badges.mine" :recent="layout.ticket_badges.recent ?? []" :close="close" />
                </template>
            </Popover>
        </div>

        </div>

        <div
            v-if="ticketsOnRail && (tasksOnRail || ordersSectionShown || catalogueSectionShown)"
            class="border-[var(--chat-line)] shrink-0"
            :class="layout.messagingSidebar.show ? 'w-full border-t' : 'w-6 border-t'"
            aria-hidden="true" />

        <template v-if="tasksOnRail">
            <div class="shrink-0" :class="layout.messagingSidebar.show ? 'flex flex-wrap items-center gap-2' : 'flex flex-col items-center'">
                <FontAwesomeIcon :icon="faTasks" class="w-4 shrink-0 text-center text-xs text-white" :class="layout.messagingSidebar.show ? '' : 'mb-1'" fixed-width v-tooltip="ctrans('Tasks')" aria-hidden="true" />
                <!-- Badge: My tasks -->
                <div v-if="myTasksOnRail" class="relative flex shrink-0 items-center justify-center" :class="dimmedClass('tasks')">
                    <RailBadgeVisibilityToggle v-if="layout.messagingSidebar.show" :hidden="isHiddenBadge('tasks')" @toggle="toggleBadge('tasks')" />
                    <Popover width="w-72" position="right-full mr-2 top-0">
                        <template #button>
                            <div v-tooltip="ctrans('My tasks')" class="relative flex h-8 w-8 cursor-pointer items-center justify-center bg-cyan-300 font-medium tabular-nums text-cyan-900 opacity-80 hover:opacity-100" :class="!layout.messagingSidebar.show && createdTasksOnRail ? 'rounded-t-xl' : 'rounded-xl'">
                                <Transition name="spin-to-right"><span :key="myTasksCount"><span :class="myTasksCount > 99 ? 'text-xxs' : 'text-xs'">{{ myTasksCount > 99 ? '99+' : myTasksCount }}</span></span></Transition>
                                <FontAwesomeIcon v-if="myTasksOverdue" icon="fas fa-circle" class="absolute top-0 -right-0.5 animate-ping text-[5px] text-red-500" fixed-width aria-hidden="true" />
                                <FontAwesomeIcon v-else-if="myTasksUnread" icon="fas fa-circle" class="absolute top-0 -right-0.5 animate-ping text-[5px] text-cyan-400" fixed-width aria-hidden="true" />
                            </div>
                        </template>
                        <template #content="{ close }">
                            <TaskBadgeList :badges="layout.task_badges!" :close="close" />
                        </template>
                    </Popover>
                </div>
                <!-- Badge: Tasks I created -->
                <div v-if="createdTasksOnRail && createdTasks" class="relative flex shrink-0 items-center justify-center" :class="dimmedClass('tasks_created')">
                    <RailBadgeVisibilityToggle v-if="layout.messagingSidebar.show" :hidden="isHiddenBadge('tasks_created')" @toggle="toggleBadge('tasks_created')" />
                    <Popover width="w-80" position="right-full mr-2 top-0">
                        <template #button>
                            <div
                                v-tooltip="createdTasks.needs_answer ? ctrans('Tasks I created · :count waiting for your answer', { count: String(createdTasks.needs_answer) }) : ctrans('Tasks I created')"
                                class="relative flex h-8 w-8 cursor-pointer items-center justify-center bg-cyan-100 font-medium tabular-nums text-cyan-900 opacity-80 hover:opacity-100"
                                :class="!layout.messagingSidebar.show && myTasksOnRail ? 'rounded-b-xl' : 'rounded-xl'">
                                <Transition name="spin-to-right"><span :key="createdTasks.open"><span :class="createdTasks.open > 99 ? 'text-xxs' : 'text-xs'">{{ createdTasks.open > 99 ? '99+' : createdTasks.open }}</span></span></Transition>
                                <template v-if="createdTasks.needs_answer">
                                    <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 animate-ping text-[5px] text-amber-500" fixed-width aria-hidden="true" />
                                    <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-[5px] text-amber-500" fixed-width aria-hidden="true" />
                                </template>
                            </div>
                        </template>
                        <template #content="{ close }">
                            <CreatedTaskBadgeList :created="createdTasks" :myId="layout.user?.id" :close="close" />
                        </template>
                    </Popover>
                </div>
            </div>

            <div v-if="ordersSectionShown || catalogueSectionShown" class="shrink-0 border-[var(--chat-line)]" :class="layout.messagingSidebar.show ? 'w-full border-t' : 'w-6 border-t'" aria-hidden="true" />
        </template>

        <button v-if="isFolded('orders') && ordersCount > 0" type="button"
            v-tooltip="ctrans('Orders: :count waiting, click to show', { count: String(ordersCount) })"
            class="relative flex h-8 w-8 shrink-0 flex-col items-center justify-center gap-0.5 rounded-md bg-amber-300/80 font-medium tabular-nums leading-none text-amber-800 hover:bg-amber-300"
            @click="expandGroup('orders')">
            <FontAwesomeIcon :icon="faShoppingCart" class="text-[9px]" fixed-width aria-hidden="true" />
            <span :class="ordersCount > 99 ? 'text-[9px]' : 'text-[11px]'">{{ ordersCount > 99 ? '99+' : ordersCount }}</span>
            <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-orange-500 text-[5px]" fixed-width aria-hidden="true" />
        </button>

        <div v-if="(!layout.messagingSidebar.show || hasOrderBadges) && !isFolded('orders')" :class="layout.messagingSidebar.show ? 'flex flex-wrap items-center gap-2' : 'contents'">
        <FontAwesomeIcon v-if="layout.messagingSidebar.show" icon="fal fa-shopping-cart" class="w-4 shrink-0 text-center text-white xtext-[var(--chat-muted)] text-xs" fixed-width v-tooltip="ctrans('Orders')" aria-hidden="true" />
        <!-- Badge: Warehouse Waiting Items -->
        <div v-if="shownOnRail('dispatching_waiting', layout?.dispatching_waiting_count > 0)" class="relative flex items-center justify-center shrink-0" :class="[layout.messagingSidebar.show ? '' : 'h-9 w-9', dimmedClass('dispatching_waiting')]">
            <RailBadgeVisibilityToggle v-if="layout.messagingSidebar.show" :hidden="isHiddenBadge('dispatching_waiting')" @toggle="toggleBadge('dispatching_waiting')" />
            <Popover width="w-80" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div v-tooltip="ctrans('Orders waiting in the warehouse')" class="relative bg-amber-300 text-amber-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
                        <Transition name="spin-to-right"><span :key="layout?.dispatching_waiting_count"><span :class="layout?.dispatching_waiting_count > 99 ? 'text-xxs' : 'text-xs'">{{ layout?.dispatching_waiting_count > 99 ? '99+' : layout?.dispatching_waiting_count }}</span></span></Transition>
                        <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-orange-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                        <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-orange-500 text-[5px]" fixed-width aria-hidden="true" />
                    </div>
                </template>
                <template #content="{ open, close }">
                    <WaitingWarehouseList :open="open" :close="close" />
                </template>
            </Popover>
        </div>

        <!-- Badge: CRM Waiting Items -->
        <div v-if="shownOnRail('crm_waiting', layout?.crm_waiting_count > 0)" class="relative flex items-center justify-center shrink-0" :class="[layout.messagingSidebar.show ? '' : 'h-9 w-9', dimmedClass('crm_waiting')]">
            <RailBadgeVisibilityToggle v-if="layout.messagingSidebar.show" :hidden="isHiddenBadge('crm_waiting')" @toggle="toggleBadge('crm_waiting')" />
            <Popover width="w-80" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div v-tooltip="ctrans('Orders waiting in CRM')" class="relative bg-purple-300 text-purple-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
                        <Transition name="spin-to-right"><span :key="layout?.crm_waiting_count"><span :class="layout?.crm_waiting_count > 99 ? 'text-xxs' : 'text-xs'">{{ layout?.crm_waiting_count > 99 ? '99+' : layout?.crm_waiting_count }}</span></span></Transition>
                        <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-purple-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                        <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-purple-500 text-[5px]" fixed-width aria-hidden="true" />
                    </div>
                </template>
                <template #content="{ open, close }">
                    <WaitingCrmList :open="open" :close="close" />
                </template>
            </Popover>
        </div>

        <!-- Badge: CRM Return Items -->
        <div v-if="shownOnRail('crm_return', layout?.crm_return_count > 0)" class="relative flex items-center justify-center shrink-0" :class="[layout.messagingSidebar.show ? '' : 'h-9 w-9', dimmedClass('crm_return')]">
            <RailBadgeVisibilityToggle v-if="layout.messagingSidebar.show" :hidden="isHiddenBadge('crm_return')" @toggle="toggleBadge('crm_return')" />
            <Popover width="w-80" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div v-tooltip="ctrans('Orders with returns')" class="relative bg-blue-300 text-blue-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
                        <Transition name="spin-to-right"><span :key="layout?.crm_return_count"><span :class="layout?.crm_return_count > 99 ? 'text-xxs' : 'text-xs'">{{ layout?.crm_return_count > 99 ? '99+' : layout?.crm_return_count }}</span></span></Transition>
                        <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-blue-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                        <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-blue-500 text-[5px]" fixed-width aria-hidden="true" />
                    </div>
                </template>
                <template #content="{ open, close }">
                    <ReturnCrmList :open="open" :close="close" />
                </template>
            </Popover>
        </div>

        <!-- Badge: Faire orders that could not be imported -->
        <div v-if="shownOnRail('faire_skipped', layout?.faire_skipped_count > 0)" class="relative flex items-center justify-center shrink-0" :class="[layout.messagingSidebar.show ? '' : 'h-9 w-9', dimmedClass('faire_skipped')]">
            <RailBadgeVisibilityToggle v-if="layout.messagingSidebar.show" :hidden="isHiddenBadge('faire_skipped')" @toggle="toggleBadge('faire_skipped')" />
            <Popover width="w-80" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div v-tooltip="ctrans('Faire orders not imported')" class="relative bg-sky-300 text-sky-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
                        <Transition name="spin-to-right"><span :key="layout?.faire_skipped_count"><span :class="layout?.faire_skipped_count > 99 ? 'text-xxs' : 'text-xs'">{{ layout?.faire_skipped_count > 99 ? '99+' : layout?.faire_skipped_count }}</span></span></Transition>
                        <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-sky-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                        <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-sky-500 text-[5px]" fixed-width aria-hidden="true" />
                    </div>
                </template>
                <template #content="{ open, close }">
                    <FaireSkippedList :open="open" :close="close" />
                </template>
            </Popover>
        </div>
        </div>

        <div v-if="ordersSectionShown && catalogueSectionShown" class="border-t border-[var(--chat-line)] shrink-0"
            :class="layout.messagingSidebar.show ? 'w-full' : 'w-6'" aria-hidden="true" />

        <button v-if="isFolded('catalogue') && catalogueCount > 0" type="button"
            v-tooltip="ctrans('Catalogue: :count to check, click to show', { count: String(catalogueCount) })"
            class="relative flex h-8 w-8 shrink-0 flex-col items-center justify-center gap-0.5 rounded-md bg-rose-300/80 font-medium tabular-nums leading-none text-rose-800 hover:bg-rose-300"
            @click="expandGroup('catalogue')">
            <FontAwesomeIcon :icon="faCube" class="text-[9px]" fixed-width aria-hidden="true" />
            <span :class="catalogueCount > 99 ? 'text-[9px]' : 'text-[11px]'">{{ catalogueCount > 99 ? '99+' : catalogueCount }}</span>
            <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-rose-500 text-[5px]" fixed-width aria-hidden="true" />
        </button>

        <div v-if="(!layout.messagingSidebar.show || hasCatalogueBadges) && !isFolded('catalogue')" :class="layout.messagingSidebar.show ? 'flex flex-wrap items-center gap-2' : 'contents'">
        <FontAwesomeIcon v-if="layout.messagingSidebar.show" icon="fal fa-cube" class="w-4 shrink-0 text-center text-white xtext-[var(--chat-muted)] text-xs" fixed-width v-tooltip="ctrans('Catalogue')" aria-hidden="true" />
        <!-- Badge: Products not following master prices -->
        <div v-if="shownOnRail('master_updated', layout?.master_updated_count > 0)" class="relative flex items-center justify-center shrink-0" :class="[layout.messagingSidebar.show ? '' : 'h-9 w-9', dimmedClass('master_updated')]">
            <RailBadgeVisibilityToggle v-if="layout.messagingSidebar.show" :hidden="isHiddenBadge('master_updated')" @toggle="toggleBadge('master_updated')" />
            <Popover width="w-80" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div v-tooltip="ctrans('Prices not matching master')" class="relative bg-rose-300 text-rose-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
                        <Transition name="spin-to-right"><span :key="layout?.master_updated_count"><span :class="layout?.master_updated_count > 99 ? 'text-xxs' : 'text-xs'">{{ layout?.master_updated_count > 99 ? '99+' : layout?.master_updated_count }}</span></span></Transition>
                        <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-rose-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                        <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-rose-500 text-[5px]" fixed-width aria-hidden="true" />
                    </div>
                </template>
                <template #content="{ open, close }">
                    <MasterUpdatedList :open="open" :close="close" />
                </template>
            </Popover>
        </div>

        <!-- Badge: Master name or description changed, shop keeps its own translation -->
        <div v-if="shownOnRail('products_need_review', layout?.products_need_review_count > 0)" class="relative flex items-center justify-center shrink-0" :class="[layout.messagingSidebar.show ? '' : 'h-9 w-9', dimmedClass('products_need_review')]">
            <RailBadgeVisibilityToggle v-if="layout.messagingSidebar.show" :hidden="isHiddenBadge('products_need_review')" @toggle="toggleBadge('products_need_review')" />
            <Popover width="w-80" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div v-tooltip="ctrans('Master text changed')" class="relative bg-emerald-300 text-emerald-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
                        <Transition name="spin-to-right"><span :key="layout?.products_need_review_count"><span :class="layout?.products_need_review_count > 99 ? 'text-xxs' : 'text-xs'">{{ layout?.products_need_review_count > 99 ? '99+' : layout?.products_need_review_count }}</span></span></Transition>
                        <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-emerald-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                        <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-emerald-500 text-[5px]" fixed-width aria-hidden="true" />
                    </div>
                </template>
                <template #content="{ open, close }">
                    <ProductsNeedReviewList :open="open" :close="close" />
                </template>
            </Popover>
        </div>

        </div>
        </div>

    </div>
</template>
