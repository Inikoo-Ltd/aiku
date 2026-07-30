<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 May 2024 18:45:58 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import { bucketQuery } from "@/Composables/bucketQuery"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Link } from '@inertiajs/vue3'

import { faTimes, faStickyNote } from '@fal'
import TagPallet from '@/Components/TagPallet.vue'
import Tag from '@/Components/Tag.vue'

library.add(faTimes, faStickyNote)

defineProps<{
    data: {}
    tab?: string
}>()




function palletHref(pallet: any) {
    const bucket = route().current()?.match(/^retina\.fulfilment\.storage\.pallets\.(storing|in_process|incidents|returned)_pallets\.index$/)?.[1]

    return route('retina.fulfilment.storage.pallets.show', { ...route().params, pallet: pallet.slug ? pallet.slug : pallet.id }) + bucketQuery(bucket)
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(reference)="{ item: pallet }">
          
            <Link :href="palletHref(pallet)" class="primaryLink">
                {{ pallet.reference }}
            </Link>
        </template>

        <template #cell(status)="{ item: pallet }">
            <TagPallet :stateIcon="pallet.status_icon" />
        </template>


        <!-- Column: Rental name -->
        <template #cell(rental)="{ item: pallet }">
            {{ pallet.rental_name }}
        </template>

        <!-- Column: Pallet Reference -->
        <template #cell(stored_items)="{ item: pallet }">
            <div v-if="pallet.stored_items.length" class="flex flex-wrap gap-x-1 gap-y-1.5">
                <Tag v-for="stored_item of pallet.stored_items" :theme="stored_item.id"
                    :label="`${stored_item.reference} (${stored_item.quantity})`" :closeButton="false" :stringToColor="true">
                    <template #label>
                        <div class="whitespace-nowrap text-xs">
                            {{ stored_item.reference }} (<span class="font-light">{{ stored_item.quantity }}</span>)
                        </div>
                    </template>
                </Tag>
            </div>
            <div v-else class="text-gray-400 text-xs italic">
            </div>
        </template>

        <template #cell(notes)="{ item }">
          <div class="text-xs	max-w-52">{{ item.notes }}</div>
        </template>

    </Table>
</template>
