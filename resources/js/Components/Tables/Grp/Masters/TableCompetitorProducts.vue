<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 05 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject } from "vue"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { useFormatTime } from "@/Composables/useFormatTime"

library.add(faExternalLink)

defineProps<{
    data: object
    tab?: string
    currencyCode: string
}>()

const locale = inject("locale", aikuLocaleStructure)
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(name)="{ item }">
            <img v-if="item.image_url" :src="item.image_url" alt="" loading="lazy" class="float-left mr-2 h-10 w-10 rounded object-cover" />
            <a v-if="item.url" :href="item.url" target="_blank" rel="noopener noreferrer" class="font-medium text-gray-700 hover:underline">
                {{ item.name }}
                <FontAwesomeIcon icon="fal fa-external-link" class="text-xs" fixed-width />
            </a>
            <span v-else class="font-medium text-gray-700">{{ item.name }}</span>
            <div class="text-xs text-gray-500">
                <span class="whitespace-nowrap">{{ item.code }}</span>
                <span v-if="item.minimum_order" class="ml-2">{{ ctrans("Minimum :n", { n: item.minimum_order }) }}</span>
            </div>
        </template>
        <template #cell(price)="{ item }">
            <span v-if="item.price !== null" class="tabular-nums">{{ locale.currencyFormat(currencyCode, item.price) }}</span>
            <span v-else class="text-xs text-gray-400">{{ ctrans("Price hidden") }}</span>
        </template>
        <template #cell(units)="{ item }">
            <span class="tabular-nums">{{ item.units }}</span>
        </template>
        <template #cell(fetched_at)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.fetched_at) }}</span>
        </template>
    </Table>
</template>
