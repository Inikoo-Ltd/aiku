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

console.log(route().params);

const getVariantRoute = (item: any) => {
  return route('grp.org.shops.show.catalogue.families.show.variants.show', {
    organisation: item.organisation_slug,
    shop: item.shop_slug,
    family: item.family_slug,
    variant: item.slug,
  });
}

const getLeaderProductRoute = (item: any) => {
  return route('grp.org.shops.show.catalogue.families.show.products.show', {
    organisation: item.organisation_slug,
    shop: item.shop_slug,
    family: item.family_slug,
    product: item.leader_product_slug,
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
        <Link :href="getVariantRoute(item)" class="primaryLink">
          {{ item.code }}
        </Link>
      </template>
      <template #cell(shop_id)="{ item }">
        {{ item.shop_code }}
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


