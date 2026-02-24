<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import EmptyState from "@/Components/Utils/EmptyState.vue";
import { faGalaxy, faTimesCircle } from "@fas";
import { getStyles } from "@/Composables/styles"
import Image from "@/Components/Image.vue";
import { computed, ref } from "vue";
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
import { trans } from "laravel-vue-i18n"
import LinkIris from "@/Components/Iris/LinkIris.vue";
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

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
    collections: Array<Object>
    sub_departments: Array<Object>
    container: {
      properties: object
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
    props.fieldValue?.settings?.per_row?.[props.screenType] ||
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
  const subs = props.fieldValue?.sub_departments ?? []
  const collections = props.fieldValue?.collections ?? []

  return [...subs, ...collections]
})

const idxSlideLoading = ref<number | null>(null)
</script>

<template>
  <div
    v-if="mergedItems.length"
    class="mx-auto"
    :class="screenClass"
    :id="fieldValue?.id ? fieldValue?.id  : 'sub-department-2'"  component="sub-department-2"
    :style="getStyles(fieldValue?.container?.properties, screenType)"
  >
    <div class="grid gap-4 auto-rows-fr" :class="gridColsClass">
      
      <LinkIris
        v-for="(item, index) in mergedItems"
        :key="item?.code"
        :href="`${item?.url}`"
        class="relative flex w-full h-full"
        :aria-label="`Go to ${item?.name}`"
        type="internal"
        @start="() => idxSlideLoading = index"
        @finish="() => idxSlideLoading = null"
      >
        <button
          :style="getStyles(fieldValue?.card?.container?.properties, screenType)"
          class="flex items-center justify-center
                 border border-gray-600 rounded-xl
                 px-4 py-3 text-sm font-medium
                 text-gray-800 bg-white hover:bg-gray-50 transition-all
                 w-full h-full"
        >
          <span class="text-center line-clamp-3 leading-snug">
            {{ item?.name }}
          </span>
        </button>

        <div
          v-if="idxSlideLoading === index"
          class="absolute inset-0 grid place-items-center bg-black/40 text-white"
        >
          <LoadingIcon />
        </div>
      </LinkIris>

    </div>
  </div>
</template>
