<script setup lang="ts">
import { getStyles } from "@/Composables/styles";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";

import { faGalaxy, faTimesCircle } from "@fas";
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

const items = [
  { icon: faTimesCircle, label: 'Soy Wax Candles' },
  { icon: faTimesCircle, label: 'Dinner Candles' },
  { icon: faTimesCircle, label: 'Tea Light Candles' },
  { icon: faTimesCircle, label: 'Gemstone Candles' },
  { icon: faTimesCircle, label: 'Aromatherapy Candles' },
  { icon: faTimesCircle, label: 'Scented Candles' },
  { icon: faTimesCircle, label: 'Spell Candles' },
  { icon: faTimesCircle, label: 'Candle Holders' },
  { icon: faTimesCircle, label: 'White Label Candles' },
  { icon: faTimesCircle, label: 'Candles Starters' },
];
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 py-12">
    <h2 class="text-2xl font-bold mb-6">Browse By Sub-department:</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
      <button
        v-for="item in items"
        :key="item.label"
        class="flex items-center gap-2 border rounded-xl px-4 py-3 text-sm font-medium text-gray-800 bg-white hover:bg-gray-50 transition-all"
      >
        <FontAwesomeIcon :icon="item.icon" class="text-lg" />
        {{ item.label }}
      </button>
    </div>
  </div>
</template>
