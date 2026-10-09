<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Thu, 25 May 2023 15:03:05 Central European Summer Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import ShowcaseStats from '@/Components/ShowcaseStats.vue'
import ShowcaseContactCard from "@/Components/ShowcaseContactCard.vue"
import Icon from '@/Components/Icon.vue'
import { ctrans } from '@/Composables/useTrans'
import { useFormatTime } from '@/Composables/useFormatTime'

const props = defineProps<{
    data: {
        // contactCard: {
        // },
        // stats: {
        //     label: string,
        //     value: number,
        // }[],
        name: string
        xxx: {}
    },

}>()
</script>

<template>
    <!-- <div class="grid text-gray-600  grid-flow-col grid-cols-2 px-4 pt-4">
        <div class="">
            <ShowcaseContactCard :data="data.contactCard" />
        </div>

        <div class="pl-6">
            <ShowcaseStats :data="data.stats" />
        </div>


    </div> -->
    <div class="px-4 py-2">
        <div class="max-w-xl mt-1 grid grid-cols-1 gap-x-6 gap-y-8 xl:gap-x-8 h-fit ">
            <div class="w-full overflow-hidden rounded-xl border border-gray-300">
                <div class=" flex flex-col justify-center gap-x-4 border-b border-gray-900/5 bg-gray-50 p-6">
                    <div class="space-x-1">
                        <!-- <Icon :data="data.state_icon" /> -->
                        <span v-if="data.code" class="font-semibold capitalize">{{ data.code }}</span>
                        <span v-else class="text-gray-500 italic">({{ ctrans('No name') }})</span>
                    </div>
                    <div v-tooltip="ctrans('Date created')" class="text-sm/6 text-gray-500 w-fit">{{ useFormatTime(data.created_at) }}</div>
                </div>
                
                <dl class="-my-3 divide-y divide-gray-100 px-6 py-4 text-sm/6">                    
                    <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ ctrans("Unit quantity") }}</dt>
                        <dd class="flex items-start gap-x-2">
                            <div class="font-medium">{{ data.unit_quantity }}</div>
                        </dd>
                    </div>

                    <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ ctrans("Pallets count") }}</dt>
                        <dd class="flex items-start gap-x-2">
                            <div class="font-medium">{{ data.stats?.number_pallets || 0 }}</div>
                        </dd>
                    </div>
                    
                <!-- <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ ctrans("Quantity warehouse") }}</dt>
                        <dd class="flex items-start gap-x-2">
                            <div class="font-medium">{{ locale.number(data.stored_item?.total_quantity || 0) }}</div>
                        </dd>
                    </div>
                    
                    <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ ctrans("Pallet") }}</dt>
                        <dd class="flex items-start gap-x-2">
                            <Link v-if="generateLinkPallet()" :href="generateLinkPallet()" class="primaryLink">
                                {{ locale.number(data.stored_item?.pallets?.length || 0) }}
                            </Link>
                            <div v-else class="font-medium">{{ locale.number(data.stored_item?.pallets?.length || 0) }}</div>
                        </dd>
                    </div>
                    
                    <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ ctrans("Last audit") }}</dt>
                        <dd class="flex items-start gap-x-2">
                            <Link v-if="generateLinkAudit(data) && data.stored_item?.last_audit_at" :href="generateLinkAudit(data)" class="primaryLink">
                                {{ useFormatTime(data.stored_item?.last_audit_at) }}
                            </Link>
                            <div v-else class="font-medium">{{ useFormatTime(data.stored_item?.last_audit_at) }}</div>
                        </dd>
                    </div> -->
                </dl>
            </div>
        </div>
        <!-- <pre>{{ data.xxx }}</pre> -->
    </div>
</template>
