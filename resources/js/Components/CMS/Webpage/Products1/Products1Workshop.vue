<script setup lang="ts">
import { faFilter, faSearch, faLayerGroup } from "@fas";
import { ref, computed } from "vue";
import ProductRender from "./ProductRender.vue";
import FilterProducts from "./FilterProduct.vue";
import Drawer from "primevue/drawer";
import Button from "@/Components/Elements/Buttons/Button.vue";
import PureInput from "@/Components/Pure/PureInput.vue";

const props = defineProps<{
  modelValue: any
  screenType: "mobile" | "tablet" | "desktop";
}>();
const dummyProductImage = '/product/product_dummy.jpeg'
const filter = ref({ data: null })
const search = ref('')
const showFilters = ref(false);
const showAside = ref(false);
const dummyProducts = ref(props.modelValue?.products?.data ? props.modelValue?.products?.data :
  Array.from({ length: 8 }).map((_, i) => ({
    id: i + 1,
    name: `Product ${i + 1}`,
    web_images: {
      main: {
        original: dummyProductImage
      }
    },
    code: `PRD-${1000 + i}`,
    price: 10000 * (i + 1),
  }))
);

const isMobile = computed(() => props.screenType === "mobile");
const layout = {
  iris: {
    is_logged_in: true
  }
};

const responsiveGridClass = computed(() => {
  const count = props.screenType === "desktop" ? 4 : props.screenType === "tablet" ? 3 : 2;
  return `grid-cols-${count}`;
});
</script>

<template>
  <div class="flex flex-col lg:flex-row">
    <transition name="slide-fade">
      <aside v-show="!isMobile && showAside" class="w-68 p-4">
        <FilterProducts v-model="filter" />
      </aside>
    </transition>

    <main class="flex-1">
      <div class="px-4 pt-4 pb-2 flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="flex items-center w-full md:w-1/3 gap-2">
          <Button v-if="isMobile" :icon="faFilter" @click="showFilters = true" class="!p-3 !w-auto"
            aria-label="Open Filters" />
          <div v-else class="py-4">
            <Button :icon="faFilter" @click="showAside = !showAside" class="!p-3 !w-auto" aria-label="Open Filters" />
          </div>

          <PureInput v-model="search" type="text" placeholder="Search products..." :clear="true" :isLoading="false"
            :prefix="{ icon: faSearch, label: '' }" />
        </div>

        <div class="flex space-x-6 overflow-x-auto mt-2 md:mt-0 border-b border-gray-300">
          <button v-for="opt in ['Latest', 'Code', 'Name', 'Price']" :key="opt"
            class="pb-2 text-sm font-medium whitespace-nowrap text-gray-600">
            {{ opt }}
          </button>
        </div>
      </div>

      <div class="px-4 pb-2 flex justify-between items-center text-sm text-gray-600">
        <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-md border border-gray-200 shadow-sm text-sm">
          <span class="text-gray-700 font-medium">
            Showing <span class="font-semibold text-gray-900">{{ dummyProducts.length }}</span>
            of <span class="font-semibold text-gray-900">{{ dummyProducts.length }}</span>
            products
          </span>
        </div>
        <div>
          <Button v-if="layout?.iris?.is_logged_in" :icon="faLayerGroup" label="Set All Products to Portfolio"
            class="!p-3 !w-auto" type="secondary" />
        </div>
      </div>

      <div :class="responsiveGridClass" class="grid gap-6 p-4">
        <div v-for="product in dummyProducts" :key="product.id" class="border p-3 relative rounded shadow-sm bg-white">
          <ProductRender :product="product" />
        </div>
      </div>
    </main>

    <!-- Mobile Filters Drawer -->
    <Drawer v-model:visible="showFilters" position="left" :modal="true" :dismissable="true" :closeOnEscape="true"
      :showCloseIcon="false" class="w-80">
      <div class="p-4">
        <FilterProducts v-model="filter" />
      </div>
    </Drawer>
  </div>
</template>

<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.3s ease;
}

.slide-fade-enter-from,
.slide-fade-leave-to {
  opacity: 0;
  transform: translateX(-10px);
}

aside {
  transition: all 0.3s ease;
}
</style>
