<script setup lang="ts">
import { faHeart } from '@far';
import { faCircle, faStar } from '@fas';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { getStyles } from "@/Composables/styles"
import { computed } from "vue";
import ProductRender from '@/Components/CMS/Webpage/Products1/ProductRender.vue';

const dummyProductImage = '/product/product_dummy.jpeg'

const props = defineProps<{
  fieldValue: any
  webpageData?: any
  blockData?: Object
  screenType: "mobile" | "tablet" | "desktop"
}>()



const responsiveGridClass = computed(() => {
  const perRow = props.fieldValue?.settings?.per_row ?? {}

  const columnCount = {
    desktop: perRow.desktop ?? 4,
    tablet: perRow.tablet ?? 4,
    mobile: perRow.mobile ?? 2,
  }

  const count = columnCount[props.screenType] ?? 1
  return `grid-cols-${count}`
})
</script>

<template>
  <div class="grid gap-6 p-4" :class="responsiveGridClass"
    :style="getStyles(fieldValue.container?.properties, screenType)">
    <div v-for="(product, index) in fieldValue?.settings?.products" :key="index" class="border p-3 relative rounded shadow-sm bg-white">
      <ProductRender :product="product" :key="index" :productHasPortfolio="[]" />
    </div>
  </div>
</template>

<style scoped></style>
