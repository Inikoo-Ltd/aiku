<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { defineAsyncComponent, inject } from 'vue'
import Image from "@common/Components/Image.vue";
import Popover from '@/Components/Popover.vue'
const Profile = defineAsyncComponent(() => import("@/Pages/Grp/Profile.vue"))
import WaitingWarehouseList from "@/Layouts/Grp/WaitingWarehouseList.vue"
import WaitingCrmList from "@/Layouts/Grp/WaitingCrmList.vue"

import { layoutStructure } from "@/Composables/useLayoutStructure"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCircle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import ReturnCrmList from './ReturnCrmList.vue';
import MasterUpdatedList from './MasterUpdatedList.vue';
import ProductsNeedReviewList from './ProductsNeedReviewList.vue';
import FaireSkippedList from './FaireSkippedList.vue';
import TicketBadgeList from './TicketBadgeList.vue';
import { computed } from 'vue'
library.add(faCircle)

const layout = inject('layout', layoutStructure)

const sumCounts = (rows: Record<string, { count: number }> | null | undefined, keys?: string[]) =>
    Object.entries(rows ?? {}).filter(([key]) => !keys || keys.includes(key)).reduce((total, [, row]) => total + row.count, 0)

const myTicketsCount = computed(() => sumCounts(layout.ticket_badges?.mine, ['to_do', 'in_progress', 'waiting']))
const myTicketsWaiting = computed(() => layout.ticket_badges?.mine?.waiting?.count ?? 0)
const myTicketsUnread = computed(() => (layout.ticket_badges?.recent ?? []).filter((update) => !update.read).length)
const queueCount = computed(() => (layout.ticket_badges?.queue?.assigned_to_me?.count ?? 0) + (layout.ticket_badges?.queue?.collaborating?.count ?? 0))
const queueOverdue = computed(() => layout.ticket_badges?.queue?.overdue?.count ?? 0)

// ponytail: only ever mounted inside MessagingSideBar, so read the expand state straight off layout instead of threading a prop
</script>

<template>
    <div
        class="border-b border-[var(--chat-line)] flex-shrink-0"
        :class="layout.messagingSidebar.show ? 'flex flex-wrap items-center gap-2 px-2 py-2' : 'flex flex-col items-center gap-y-3 py-3'">
        <!-- Button: Profile -->
        <div @click="layout.stackedComponents.push({ component: Profile})"
            class="flex overflow-hidden items-center rounded-full bg-[var(--chat-line)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--chat-accent)] cursor-pointer shrink-0"
            :class="layout.messagingSidebar.show ? '' : 'order-first'">
            <span class="sr-only">{{ trans("Open user menu") }}</span>
            <Image class="h-8 w-8 rounded-full" :src="layout.avatar_thumbnail" alt="" />
        </div>

        <div v-if="layout.ticket_badges?.queue || (layout.ticket_badges?.mine && (myTicketsCount > 0 || myTicketsUnread > 0))" class="flex flex-col items-center shrink-0">
        <FontAwesomeIcon icon="fal fa-life-ring" class="text-[var(--chat-muted)] text-xs mb-1" fixed-width :title="trans('Tickets')" aria-hidden="true" />
        <!-- Badge: Ticket work queue (engineers and QA) -->
        <div v-if="layout.ticket_badges?.queue" class="relative flex items-center justify-center shrink-0">
            <Popover width="w-72" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div :title="trans('Tickets assigned to me')" class="relative w-8 h-8 flex items-center justify-center opacity-80 hover:opacity-100 cursor-pointer font-medium tabular-nums bg-lime-300 text-lime-900" :class="layout.ticket_badges?.mine && (myTicketsCount > 0 || myTicketsUnread > 0) ? 'rounded-t-xl' : 'rounded-xl'">
                        <Transition name="spin-to-right"><span :key="queueCount"><span :class="queueCount > 99 ? 'text-xxs' : 'text-xs'">{{ queueCount > 99 ? '99+' : queueCount }}</span></span></Transition>
                        <FontAwesomeIcon v-if="queueOverdue" icon="fas fa-circle" class="absolute top-0 -right-0.5 text-fuchsia-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                    </div>
                </template>
                <template #content="{ close }">
                    <TicketBadgeList :title="trans('Tickets to fix')" :rows="layout.ticket_badges.queue" :close="close" />
                </template>
            </Popover>
        </div>

        <!-- Badge: My tickets -->
        <div v-if="layout.ticket_badges?.mine && (myTicketsCount > 0 || myTicketsUnread > 0)" class="relative flex items-center justify-center shrink-0">
            <Popover width="w-72" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div :title="trans('My tickets')" class="relative w-8 h-8 flex items-center justify-center opacity-80 hover:opacity-100 cursor-pointer font-medium tabular-nums bg-lime-100 text-lime-900" :class="layout.ticket_badges?.queue ? 'rounded-b-xl' : 'rounded-xl'">
                        <Transition name="spin-to-right"><span :key="myTicketsCount"><span :class="myTicketsCount > 99 ? 'text-xxs' : 'text-xs'">{{ myTicketsCount > 99 ? '99+' : myTicketsCount }}</span></span></Transition>
                        <FontAwesomeIcon v-if="myTicketsWaiting || myTicketsUnread" icon="fas fa-circle" class="absolute top-0 -right-0.5 text-lime-400 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                    </div>
                </template>
                <template #content="{ close }">
                    <TicketBadgeList :title="trans('My tickets')" :rows="layout.ticket_badges.mine" :recent="layout.ticket_badges.recent ?? []" :close="close" />
                </template>
            </Popover>
        </div>

        </div>

        <div v-if="layout.ticket_badges?.queue || (layout.ticket_badges?.mine && (myTicketsCount > 0 || myTicketsUnread > 0))" class="w-6 border-t border-[var(--chat-line)] shrink-0" aria-hidden="true" />

        <!-- Badge: Warehouse Waiting Items -->
        <div v-if="layout?.dispatching_waiting_count > 0" class="relative flex items-center justify-center shrink-0" :class="layout.messagingSidebar.show ? '' : 'h-9 w-9'">
            <Popover width="w-80" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div :title="trans('Orders waiting in the warehouse')" class="relative bg-amber-300 text-amber-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
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
        <div v-if="layout?.crm_waiting_count > 0" class="relative flex items-center justify-center shrink-0" :class="layout.messagingSidebar.show ? '' : 'h-9 w-9'">
            <Popover width="w-80" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div :title="trans('Orders waiting in CRM')" class="relative bg-purple-300 text-purple-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
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
        <div v-if="layout?.crm_return_count > 0" class="relative flex items-center justify-center shrink-0" :class="layout.messagingSidebar.show ? '' : 'h-9 w-9'">
            <Popover width="w-80" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div :title="trans('Orders with returns')" class="relative bg-blue-300 text-blue-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
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

        <!-- Badge: Products not following master prices -->
        <div v-if="layout?.master_updated_count > 0" class="relative flex items-center justify-center shrink-0" :class="layout.messagingSidebar.show ? '' : 'h-9 w-9'">
            <Popover width="w-80" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div :title="trans('Prices not matching master')" class="relative bg-rose-300 text-rose-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
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
        <div v-if="layout?.products_need_review_count > 0" class="relative flex items-center justify-center shrink-0" :class="layout.messagingSidebar.show ? '' : 'h-9 w-9'">
            <Popover width="w-80" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div :title="trans('Master text changed')" class="relative bg-emerald-300 text-emerald-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
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

        <!-- Badge: Faire orders that could not be imported -->
        <div v-if="layout?.faire_skipped_count > 0" class="relative flex items-center justify-center shrink-0" :class="layout.messagingSidebar.show ? '' : 'h-9 w-9'">
            <Popover width="w-80" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div :title="trans('Faire orders not imported')" class="relative bg-sky-300 text-sky-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
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
</template>
