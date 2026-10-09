<script setup lang='ts'>
import { useFormatTime } from '@/Composables/useFormatTime'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { Link } from '@inertiajs/vue3'
import { ctrans } from '@/Composables/useTrans'
import { inject } from 'vue'

const props = defineProps<{
    data: {
        reference: string
        start_at: string
        end_at: string
        state_label: string
        exclude_weekend: boolean
        rental: {
            slug: string
            name: string
        } | null
        recurring_bill: {
            slug: string
            reference: string
        } | null
    }
}>()

const layout = inject('layout', layoutStructure)
</script>

<template>
    <div class="px-6">
        <div class="max-w-xl mt-6 grid grid-cols-1 gap-x-6 gap-y-8 xl:gap-x-8 h-fit ">
            <div class="w-full overflow-hidden rounded-xl" :style="{ border: `2px solid ${layout.app?.theme?.[0]}88`}">
                <div class=" flex flex-col justify-center gap-x-4 border-b border-gray-900/5 bg-gray-50 p-6">
                    <div class="font-bold">
                        <!-- <Icon :data="data.type_icon" /> -->
                        {{ data.reference }}
                    </div>
                    <div class="text-sm/6 text-gray-500">{{ useFormatTime(data.start_at) }} - {{ useFormatTime(data.end_at) }}</div>
                </div>
                
                <dl class="-my-3 divide-y divide-gray-100 px-6 py-4 text-sm/6">                    
                    <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ ctrans("State") }}</dt>
                        <dd class="flex items-start gap-x-2">
                            {{ data.state_label }}
                        </dd>
                    </div>

                    <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ ctrans("Exclude weekend") }}</dt>
                        <dd class="flex items-start gap-x-2">
                            <div class="font-medium">{{ data.exclude_weekend ? 'Yes' : 'No' }}</div>
                        </dd>
                    </div>

                    <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ ctrans("Rental") }}</dt>
                        <dd class="flex items-start gap-x-2">
                            <Link v-if="data.rental" :href="route('grp.org.fulfilments.show.catalogue.rentals.show', [route().params.organisation, route().params.fulfilment, data.rental?.slug])" class="font-medium secondaryLink">{{ data.rental?.name || '-' }}</Link>
                            <span v-else>-</span>
                        </dd>
                    </div>

                    <div class="flex justify-between gap-x-4 py-3">
                        <dt class="text-gray-500">{{ ctrans("Recurring Bill") }}</dt>
                        <dd class="flex items-start gap-x-2">
                            <Link v-if="data.recurring_bill" :href="route('grp.org.fulfilments.show.catalogue.rentals.show', [route().params.organisation, route().params.fulfilment, data.recurring_bill?.slug])" class="font-medium secondaryLink">{{ data.recurring_bill?.reference || '-' }}</Link>
                            <span v-else>-</span>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</template>