<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 May 2024 18:50:57 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import { Link } from '@inertiajs/vue3'
import { Shop } from "@/types/shop"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCircle, faDoNotEnter } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'

library.add(faCircle, faDoNotEnter)

const props = defineProps<{
    data: {}
    tab?: string
}>()

const layout = inject('layout', layoutStructure)

const getMasterVariantRoute = (item: any) => {
  return route('grp.masters.master_shops.show.master_families.master_variants.show', {
    masterShop: route().params.masterShop,
    masterFamily: route().params.masterFamily,
    masterVariant: item.slug,
  });
}

const getLeaderProductRoute = (item: any) => {
  return route('grp.masters.master_shops.show.master_families.master_products.show', {
    masterShop: route().params.masterShop,
    masterFamily: route().params.masterFamily,
    masterProduct: item.leader_product_slug,
  });
}

const formatOptions = (value) => {
  try {
    return JSON.parse(value).join(', ')
  } catch (e) {
    return ''
  }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
      <template #cell(code)="{ item }">
        <Link :href="getMasterVariantRoute(item)" class="primaryLink">
          {{ item.code }}
        </Link>
      </template>
      <template #cell(leader_product_name)="{ item }">
        <Link :href="getLeaderProductRoute(item)" class="primaryLink">
          {{ item.leader_product_name }}
        </Link>
      </template>
      <template #cell(number_dimensions)="{ item }"> 
        <div v-for="(value, key) in item.options" :key="key">
          <span class="font-semibold">{{ key }}</span>: [&nbsp;<span class="italic">{{ formatOptions(value) }}</span>&nbsp;]
        </div>
      </template> 
      <template #cell(number_used_slots)="{ item }">
        {{ trans(':_used_slot out of :_max_slot slots has been filled', {_used_slot: item.number_used_slots, _max_slot: item.number_max_slots}) }}
      </template> 
      <template #cell(number_used_slots_for_sale)="{ item }"> 
        {{ trans(':_used_slot products is set for sale', {_used_slot: item.number_used_slots_for_sale}) }}
      </template>
    </Table>
</template>


