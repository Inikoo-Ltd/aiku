<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import EmptyState from "@/Components/Utils/EmptyState.vue";
import { faGalaxy, faTimesCircle } from "@fas";
import { getStyles } from "@/Composables/styles"
import Image from "@/Components/Image.vue";
import { computed } from "vue";
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
  fieldValue: {
    collections : Array<Object>
    sub_departments :Array<Object>
      container : {
        properties : object
      }
  }
  webpageData?: any
  blockData?: object
  screenType: 'mobile' | 'tablet' | 'desktop'
}>();

const fallbackPerRow = {
  desktop: 4,
  tablet: 3,
  mobile: 2,
};

const perRow = computed(() => {
  return (
    props.fieldValue?.setting?.per_row?.[props.screenType] ||
    fallbackPerRow[props.screenType] ||
    1
  );
});

const gridColsClass = computed(() => `grid-cols-${perRow.value}`);

const screenClass = computed(() => {
  switch (props.screenType) {
    case "mobile":
      return "px-4 py-6 text-sm";
    case "tablet":
      return "px-6 py-8 text-base";
    case "desktop":
    default:
      return "px-12 py-12 text-base";
  }
});

const mergedItems = computed(() => {
  const subs = props.modelValue?.sub_departments ?? []
  const collections = props.modelValue?.collections ?? []

  return [...subs, ...collections]
})

</script>

<template>
    <div v-if="fieldValue?.sub_departments?.length"
    class="mx-auto"
    :class="screenClass"
    :style="getStyles(fieldValue?.container?.properties, screenType)"
  >
    <h2 class="text-2xl font-bold mb-6" aria-label="Browse Sub-departments Section">
      Browse By Sub-department:
    </h2>

    <div >
      <div class="grid gap-4" :class="gridColsClass">
        <a
          v-for="item in mergedItems"
          :key="item.code"
          :href="`${item.url}`"
          class="flex items-center gap-3 border rounded px-4 py-3 text-sm font-medium text-gray-800 bg-white hover:bg-gray-50 transition-all w-full"
          :aria-label="`Go to ${item.name}`"
        >
        
          <div class="flex items-center justify-center min-w-5 min-h-5 w-5 h-5 shrink-0">
            <FontAwesomeIcon
              v-if="item.icon"
              :icon="item.icon"
              class="text-xl w-5 h-5"
            />
            <Image
              v-else
              :src="item.image"
              class="max-w-full max-h-full object-contain"
              :alt="item.name"
            />
          </div>
          <span class="flex-1 text-center">{{ item.name }}</span>
        </a>
      </div>
    </div>

    
  </div>


<div v-else></div>
</template>

