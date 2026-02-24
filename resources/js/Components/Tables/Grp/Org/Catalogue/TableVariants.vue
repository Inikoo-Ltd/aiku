<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 May 2024 18:50:57 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCircle, faDoNotEnter } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
import { inject, ref } from 'vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import Modal from '@/Components/Utils/Modal.vue'
import { faCheck, faTimes } from '@fal'

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

function shopRoute(item: Family) {
    switch (route().current()) {
        case 'grp.masters.master_shops.show.master_families.master_variants.show':
            return route(
                "grp.org.shops.show.catalogue.families.show.variants.show",
                [item.organisation_slug, item.shop_slug, item.family_slug, item.slug ])
        default:
            return route(
                "grp.org.shops.show.catalogue.dashboard",
                [item.organisation_slug, item.shop_slug])
    }
}

const viewedProduct = ref([]);
const isOpenModal = ref(false);

const openModalProducts = (item) => {
  isOpenModal.value = true;
  viewedProduct.value = item;
}

const resetModalProducts = () => {
  isOpenModal.value = false;
  viewedProduct.value = [];
}

const linkRedirectAsset = (item) => {
   return route("grp.helpers.redirect_asset", {asset: item.asset_id});
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
         <Link :href="shopRoute(item)" class="primaryLink">
              {{ item.shop_code }}
          </Link>
      </template>
      <template #cell(leader_product_name)="{ item }">
        <Link :href="getLeaderProductRoute(item)" class="secondaryLink">
          {{ item.leader_product_name }}
        </Link>
      </template>
      <template #cell(number_dimensions)="{ item }"> 
        <div v-for="(value, key) in item.options" :key="key">
          <span class="font-semibold">{{ key }}</span>: [&nbsp;<span class="italic">{{ formatOptions(value) }}</span>&nbsp;]
        </div>
      </template> 
      <template #cell(number_used_slots)="{ item }"> 
        <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-medium font-medium border transition-colors duration-150 cursor-pointer" v-tooltip="trans('View All')"@click="openModalProducts(item.product_list)"> 
          <span :class="item.number_used_slots == item.number_max_slots  ? 'text-green-500' : 'text-red-500'"> {{ item.number_used_slots }} </span> / {{ item.number_max_slots }} {{ trans(' Slots') }}
        </span>
      </template> 
      <template #cell(number_used_slots_for_sale)="{ item }"> 
        <!-- {{ item.product_list }} -->
        <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-medium font-medium border transition-colors duration-150 cursor-pointer" v-tooltip="trans('View All')" @click="openModalProducts(item.product_list)"> 
          <span :class="item.number_used_slots_for_sale == item.product_list.length ? 'text-green-500' : 'text-red-500'"> {{ item.number_used_slots_for_sale }} </span> / {{ item.product_list.length }} {{ trans(' Products for sale') }}
        </span>
      </template>
    </Table>
    <Modal :isOpen="isOpenModal" @onClose="resetModalProducts()" :width="'w-5/8 px-0'">
      <div class="border-b px-6 pb-3 font-medium">
        {{ trans('List of Products') }}
      </div>
      <div class="mx-3 mt-3 pt-1 border text-sm">
        <div class="grid grid-cols-8 pb-2 pt-1 mb-1 border-b">  
          <div class="px-3 col-span-2">
            {{  trans('Product Code') }}
          </div>
          <div class="px-3 col-span-5">
            {{  trans('Product Name') }}
          </div>
          <div class="pr-3 text-center">
            {{  trans('For Sale') }}
          </div>
        </div>
        <div v-for="product in viewedProduct" class="grid grid-cols-8 py-2">
          <div class="px-3 col-span-2">
            <Link :href="linkRedirectAsset(product)" class="primaryLink">
              {{ product.code }}
            </Link>
          </div>
          <div class="px-3 col-span-5">
            {{ product.name }}
          </div>
          <div class="pr-3 text-center">
            <FontAwesomeIcon :icon="faCheck" v-if="product.is_for_sale" class="text-green-500"/>
            <FontAwesomeIcon :icon="faTimes" v-else class="text-red-500"/>
          </div>
        </div>
      </div>
    </Modal>
</template>