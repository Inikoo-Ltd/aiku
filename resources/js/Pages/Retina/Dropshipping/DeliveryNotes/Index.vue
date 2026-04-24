<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { ref, watch } from 'vue'

import { PageHeadingTypes } from '@/types/PageHeading'
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBoxOpen } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faBoxOpen)

defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    data: any
}>()

const locale = useLocaleStore();
const startDate = ref('');
const endDate = ref('');

watch([startDate, endDate], ([newStartDate, newEndDate]) => {
    router.get(route('retina.dropshipping.delivery_notes.index'), {
        startDate: newStartDate,
        endDate: newEndDate
    }, {
        preserveState: true,
        replace: true,
    })
})

function channelRoute(item: any) {
    if (item.customer_sales_channel_slug) {
        return route("retina.dropshipping.customer_sales_channels.show", [item.customer_sales_channel_slug])
    }
    return null
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    
    <div class="flex items-center gap-3 px-4 py-3">
        <input type="date" 
            v-model="startDate" 
            class="border border-gray-300 rounded px-3 py-1.5 text-sm">
        <input type="date" 
            v-model="endDate" 
            class="border border-gray-300 rounded px-3 py-1.5 text-sm">
    </div>

    <Table :resource="data" class="mt-5">
        <template #cell(type)="{ item }">
            <div class="flex items-center gap-2">
                <FontAwesomeIcon :icon="['fal', 'fa-box-open']" class="text-lg" />
            </div>
        </template>
        <template #cell(reference)="{ item }">
            <a :href="route('retina.dropshipping.packing_lists.pdf', item.slug)" target="_blank" class="primaryLink py-0.5 whitespace-nowrap">
                {{ item.reference || item.slug }}
            </a>
        </template>

        <template #cell(customer_sales_channel_name)="{ item }">
            <div v-if="item.customer_sales_channel_slug" class="flex items-center gap-2">
                <Link :href="channelRoute(item)" class="primaryLink py-0.5 whitespace-nowrap">
                    {{ item.customer_sales_channel_name || 'Channel' }}
                </Link>
            </div>
            <div v-else class="text-gray-500 text-sm">
                N/A
            </div>
        </template>

        <!-- Column: Date -->
        <template #cell(date)="{ item }">
            <div class="text-gray-500 text-right whitespace-nowrap">
                {{ useFormatTime(item.date, { localeCode: locale.language.code, formatTime: "aiku" }) }}
            </div>
        </template>

        <template #cell(state)="{ item }">
            <div>
                {{ item.state }}
            </div>
        </template>
    </Table>
</template>
