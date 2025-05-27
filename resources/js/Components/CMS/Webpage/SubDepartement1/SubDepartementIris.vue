<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import EmptyState from "@/Components/Utils/EmptyState.vue";
import { faGalaxy, faTimesCircle } from "@fas";
import { getStyles } from "@/Composables/styles"
import {
  faBaby, faCactus, faCircle, faObjectGroup, faUser, faHouse,
  faTruck, faTag, faPhone, faInfoCircle
} from "@fal";
import {
  faBackpack, faTruckLoading, faTruckMoving, faTruckContainer,
  faUser as faUserRegular, faWarehouse, faWarehouseAlt, faShippingFast,
  faInventory, faDollyFlatbedAlt, faBoxes, faShoppingCart, faBadgePercent,
  faChevronRight, faCaretRight, faPhoneAlt, faGlobe, faPercent,
  faPoundSign, faClock
} from "@far";
import { faLambda } from "@fad";

// Tambahkan semua ikon ke library
library.add(
  faTimesCircle, faUser, faCactus, faBaby, faObjectGroup, faGalaxy, faLambda,
  faBackpack, faHouse, faTruck, faTag, faPhone, faInfoCircle,
  faTruckLoading, faTruckMoving, faTruckContainer, faUserRegular, faWarehouse,
  faWarehouseAlt, faShippingFast, faInventory, faDollyFlatbedAlt, faBoxes,
  faShoppingCart, faBadgePercent, faChevronRight, faCaretRight, faPhoneAlt,
  faGlobe, faPercent, faPoundSign, faClock
);

const props = defineProps<{
  fieldValue: Record<string, any>
  webpageData?: any
  blockData?: object
  screenType: 'mobile' | 'tablet' | 'desktop'
}>();


</script>

<template>
  <div class="mx-auto px-4 py-12" :style="getStyles(fieldValue.container?.properties, screenType)">
    <h2 class="text-2xl font-bold mb-6">Browse By Sub-department:</h2>

    <div v-if="fieldValue.sub_departments?.length">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <button v-for="item in fieldValue.sub_departments" :key="item.code"
          class="flex items-center gap-2 border rounded-xl px-4 py-3 text-sm font-medium text-gray-800 bg-white hover:bg-gray-50 transition-all">
          <FontAwesomeIcon :icon="item.icon || faCircle" class="text-lg" />
          {{ item.name }}
        </button>
      </div>
    </div>

    <div v-else class="text-center text-gray-500 py-6">
      <EmptyState :data="{
        title:'No Sub-departments Available', description:'Please check back later or contact support.' }" />
    </div>
  </div>
</template>
