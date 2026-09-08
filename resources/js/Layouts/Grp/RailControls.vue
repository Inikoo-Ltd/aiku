<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue'
import Image from "@common/Components/Image.vue";
import Popover from '@/Components/Popover.vue'
import Profile from "@/Pages/Grp/Profile.vue"
import WaitingWarehouseList from "@/Layouts/Grp/WaitingWarehouseList.vue"
import WaitingCrmList from "@/Layouts/Grp/WaitingCrmList.vue"

import { layoutStructure } from "@/Composables/useLayoutStructure"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCircle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import ReturnCrmList from './ReturnCrmList.vue';
import MasterUpdatedList from './MasterUpdatedList.vue';
import FaireSkippedList from './FaireSkippedList.vue';
library.add(faCircle)

const layout = inject('layout', layoutStructure)

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

        <!-- Badge: Warehouse Waiting Items -->
        <div v-if="layout?.dispatching_waiting_count > 0" class="relative flex items-center justify-center shrink-0" :class="layout.messagingSidebar.show ? '' : 'h-9 w-9'">
            <Popover width="w-80" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div class="relative bg-amber-300 text-amber-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
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
                    <div class="relative bg-purple-300 text-purple-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
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
                    <div class="relative bg-blue-300 text-blue-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
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
                    <div class="relative bg-rose-300 text-rose-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
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

        <!-- Badge: Faire orders that could not be imported -->
        <div v-if="layout?.faire_skipped_count > 0" class="relative flex items-center justify-center shrink-0" :class="layout.messagingSidebar.show ? '' : 'h-9 w-9'">
            <Popover width="w-80" position="right-full mr-2 top-0">
                <template #button="{ open }">
                    <div class="relative bg-sky-300 text-sky-700 rounded-md w-8 h-8 flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer font-medium tabular-nums">
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
