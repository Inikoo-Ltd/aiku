<script setup lang="ts">
import ProductCategoryCard from "@/Components/ProductCategoryCard.vue";
import { faImage } from "@fal";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import Image from "@/Components/Image.vue";


// Interfaces
interface TradeUnit {
  id: number;
  name: string;
  code?: string;
  image?: {
    thumbnail: string;
  };
}

interface ProductItem {
  product_id: number;
  name: string;
  code?: string;
  shop_id: number;
  shop_name: string;
  shop_currency: string;
  price: number | string;
  update_route: {
    name: string;
    parameters: Record<string, any>;
  };
}

interface ProductData {
  id: number;
  name: string;
  image?: {
    source: string;
  };
  
  trade_units: TradeUnit[];
  products: ProductItem[];
}

const props = defineProps<{
  currency : string
  data: {
    data: ProductData;
  };
}>();

</script>


<template>
  <div class="px-4 pb-10 m-5 space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-8 gap-6">
      <!-- Left Column -->
      <div class="col-span-1 md:col-span-1 lg:col-span-2">
        <ProductCategoryCard :data="data.data">
          <template #image>
            <Image
              v-if="data?.data.image"
              :src="data?.data.image.source"
              class="w-full h-52 object-cover object-center rounded-t-lg"
            />
            <div
              v-else
              class="flex justify-center items-center bg-gray-100 w-full h-52 rounded-t-lg"
            >
              <FontAwesomeIcon :icon="faImage" class="w-10 h-10 text-gray-400" />
            </div>
          </template>
        </ProductCategoryCard>
      </div>
    </div>
  </div>
</template>
