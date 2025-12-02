<script setup lang="ts">
import { faFilter, faSearch, faLayerGroup } from "@fas";
import { ref, computed, inject, watch } from "vue";
import FilterProducts from "@/Components/CMS/Webpage/Products1/FilterProduct.vue";
import Drawer from "primevue/drawer";
import Button from "@/Components/Elements/Buttons/Button.vue";
import PureInput from "@/Components/Pure/PureInput.vue";
import { getStyles } from "@/Composables/styles";

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFileDownload } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
import ProductRenderEcom from "@/Components/CMS/Webpage/Products1/Ecommerce/ProductRenderEcom.vue"
import { getProductsRenderB2bComponent } from "@/Composables/getIrisComponents";

library.add(faFileDownload)

const props = defineProps<{
  modelValue: any
  screenType: "mobile" | "tablet" | "desktop";
  code : string
}>();

const layout: any = inject("layout", {});

const dummyProductImage = '/product/product_dummy.jpeg';

const filter = ref({ data: null });
const search = ref('');
const showFilters = ref(false);
const showAside = ref(false);

const dummyProducts = computed(() => {
  return props.modelValue?.products?.data?.length
    ? props.modelValue.products.data
    : Array.from({ length: 8 }).map((_, i) => ({
      id: i + 1,
      name: `Product ${i + 1}`,
      web_images: {
        main: {
          original: dummyProductImage,
        },
      },
      code: `PRD-${1000 + i}`,
      price: 10000 * (i + 1),
    }));
});

const isMobile = computed(() => props.screenType === "mobile");

const responsiveGridClass = computed(() => {
  const perRow = props.modelValue?.settings?.per_row ?? {};
  const columnCount = {
    desktop: perRow.desktop ?? 4,
    tablet: perRow.tablet ?? 4,
    mobile: perRow.mobile ?? 2,
  };
  const count = columnCount[props.screenType] ?? 1;
  return `grid-cols-${count}`;
});

const search_sort_class = ref(getStyles(props.modelValue?.search_sort?.sort?.properties, props.screenType, false))
const placeholder_class = ref(getStyles(props.modelValue?.search_sort?.search?.placeholder?.properties, props.screenType, false))
const search_class = ref(getStyles(props.modelValue?.search_sort?.search?.input?.properties, props.screenType, false))

watch(
  () => props.modelValue?.search_sort,
  () => {
    search_sort_class.value = getStyles(props.modelValue?.search_sort?.sort?.properties, props.screenType, false)
    placeholder_class.value = getStyles(props.modelValue?.search_sort?.search?.placeholder?.properties, props.screenType, false)
    search_class.value = getStyles(props.modelValue?.search_sort?.search?.input?.properties, props.screenType, false)
  },
  { deep: true }
)

</script>


<template>
  <div id="products-1">
    <div class="flex flex-col lg:flex-row" :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(modelValue.container?.properties, screenType)
    }">
      <transition v-if="!props.modelValue?.settings?.is_hide_filter" name="slide-fade">
        <aside v-show="!isMobile && showAside" class="w-68 p-4">
          <FilterProducts v-model="filter" :productCategory="props.modelValue.model_id"/>
        </aside>
      </transition>

      <main class="flex-1 mt-4">
        <!-- <div class="px-4 xpt-4 mb-2 text-base font-normal">
            <div
                v-tooltip="trans('This is not work in workshop, try in website.')"
                xhref="route().has('iris.catalogue.feeds.product_category.download') ? route('iris.catalogue.feeds.product_category.download', { productCategory: props.modelValue.model_slug }) : '#'"
                xtarget="_blank"
                class="group hover:underline w-fit">
                <FontAwesomeIcon icon="fas fa-file-download" class="text-sm opacity-50 group-hover:opacity-100" fixed-width aria-hidden="true" />
                <span class="text-sm font-normal opacity-70 group-hover:opacity-100">Download products (csv)</span>
            </div>
        </div> -->
        <div class="px-4 xpt-4 mb-2 flex flex-col md:flex-row justify-between items-center gap-4">
          <div class="flex items-center w-full md:w-1/3 gap-2">
            
            <template v-if="!props.modelValue?.settings?.is_hide_filter">
              <Button v-if="isMobile" :icon="faFilter" @click="showFilters = true" class="!p-3 !w-auto"
                aria-label="Open Filters"  :injectStyle="getStyles(modelValue?.filter?.button?.properties,screenType)"/>
              <div v-else class="">
                <Button :icon="faFilter" @click="showAside = !showAside" :injectStyle="getStyles(modelValue?.filter?.button?.properties,screenType)" class="!p-3 !w-auto" aria-label="Open Filters" />
              </div>
            </template>

            <div class=" w-full" >
               <PureInput 
                  v-model="search" 
                  type="text" 
                  :placeholder="trans('Search products...')" 
                  :clear="true" :isLoading="false"
                  :prefix="{ icon: faSearch, label: '' }" class="search-input ring-0">
                  <template #prefix>
                    <div class="pl-3 whitespace-nowrap text-gray-400">
                      <FontAwesomeIcon  :icon='faSearch' class="icon-search" fixed-width aria-hidden='true' />
                    </div>
                  </template>
                </PureInput>
            </div>
          </div>

          <div class="flex space-x-6 overflow-x-auto mt-2 md:mt-0 border-b border-gray-300 ">
            <button v-for="opt in ['Latest', 'Code', 'Name', 'Price']" :key="opt"
              class="pb-2 text-sm font-medium whitespace-nowrap sort-button ">
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
              class="!p-3 !w-auto"  type="secondary" />
          </div>
        </div>

        <div :class="responsiveGridClass" class="grid gap-6 p-4">
          <div v-for="product in dummyProducts" :key="product.id"
            :style="getStyles(modelValue?.card_product?.properties, screenType)"
            class="border p-3 relative rounded  bg-white">
            <component
              :is="getProductsRenderB2bComponent(code)" 
              :product="product" 
              :buttonStyle="getStyles(modelValue?.button?.properties, screenType, false)"
              :hasInBasket="[]" 
              :bestSeller="modelValue.bestseller" 
              :buttonStyleHover="getStyles(modelValue?.buttonHover?.properties, screenType)"
              :buttonStyleLogin="getStyles(modelValue?.buttonLogin?.properties, screenType)"
             />
          </div>
        </div>
      </main>

      <!-- Mobile Filters Drawer -->
      <Drawer v-model:visible="showFilters" position="left" :modal="true" :dismissable="true" :closeOnEscape="true"
        :showCloseIcon="false" class="w-80">
        <div class="p-4">
          <FilterProducts v-model="filter" :productCategory="props.modelValue.model_id"/>
        </div>
      </Drawer>
    </div>
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

.sort-button{
   color: v-bind('search_sort_class?.color || null') !important;
   font-family: v-bind('search_sort_class?.fontFamily || null') !important;
   font-size: v-bind('search_sort_class?.fontSize || null') !important;
   font-style: v-bind('search_sort_class?.fontStyle || null') !important;
}

.icon-search{
    color: v-bind('search_class?.color || null') !important;
    font-family: v-bind('search_class?.fontFamily || null') !important;
    font-size: v-bind('search_class?.fontSize || null') !important;
    font-style: v-bind('search_class?.fontStyle || null') !important;
}

.search-input {
    color: v-bind('search_class?.color || null') !important;
    font-family: v-bind('search_class?.fontFamily || null') !important;
    font-size: v-bind('search_class?.fontSize || null') !important;
    font-style: v-bind('search_class?.fontStyle || null') !important;

    border-top: v-bind('search_class?.borderTop || null') !important;
    border-bottom: v-bind('search_class?.borderBottom || null') !important;
    border-left: v-bind('search_class?.borderLeft || null') !important;
    border-right: v-bind('search_class?.borderRight || null') !important;

    border-top-left-radius: v-bind('search_class?.borderTopLeftRadius || null') !important;
    border-top-right-radius: v-bind('search_class?.borderTopRightRadius || null') !important;
    border-bottom-left-radius: v-bind('search_class?.borderBottomLeftRadius || null') !important;
    border-bottom-right-radius: v-bind('search_class?.borderBottomRightRadius || null') !important;

  :deep(input) {
    color: v-bind('search_class?.color || null') !important;
    font-family: v-bind('search_class?.fontFamily || null') !important;
    font-size: v-bind('search_class?.fontSize || null') !important;
    font-style: v-bind('search_class?.fontStyle || null') !important;
  }

  :deep(input::placeholder) {
    color: v-bind('placeholder_class?.color || "#999"') !important;
    font-family: v-bind('placeholder_class?.fontFamily || "inherit"') !important;
    font-size: v-bind('placeholder_class?.fontSize || null') !important;
    font-style: v-bind('placeholder_class?.fontStyle || null') !important;
  }
}


</style>
