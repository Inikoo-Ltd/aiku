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
const getMasterVariantRoute = (item: any) => {
  if(route().current() == 'grp.masters.master_shops.show.master_families.show'){
    return route('grp.masters.master_shops.show.master_families.master_variants.show', {
      masterShop: route().params.masterShop,
      masterFamily: route().params.masterFamily,
      masterVariant: item.slug,
    });
  }
}

console.log('props_ssss',props)

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
      <template #cell(code)="{ item }">
        <Link :href="getMasterVariantRoute(item)" class="primaryLink">
          {{ item.code }}
        </Link>
      </template>
    </Table>
</template>


