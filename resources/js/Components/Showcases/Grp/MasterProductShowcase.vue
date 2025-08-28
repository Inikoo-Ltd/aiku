<script setup lang="ts">
import ProductCategoryCard from "@/Components/ProductCategoryCard.vue";
import { faImage } from "@fal";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import Image from "@/Components/Image.vue";
import { trans } from "laravel-vue-i18n";

// Interfaces
interface TradeUnit {
  id: number;
  name: string;
  code?: string;
  image?: {
    thumbnail: string;
  };
}

interface ProductData {
  id: number;
  name: string;
  image?: {
    source: string;
  };
  trade_units: TradeUnit[];
}

const props = defineProps<{
  data: {
    data: ProductData;
  };
}>();
</script>

<template>
  <div class="px-4 pb-10 m-5">
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-8 gap-6 mt-4">
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

      <!-- Trade Units -->
      <div
        class="col-span-1 md:col-span-2 lg:col-span-3 bg-white border rounded-lg shadow-sm p-4 h-fit"
      >
        <h3 class="text-lg font-semibold mb-3 text-gray-800">
          Trade Units ({{ data.data.trade_units.length }})
        </h3>

        <div v-if="data.data.trade_units.length" class="divide-y border rounded-md bg-gray-50">
          <div
            v-for="item in data.data.trade_units"
            :key="item.id"
            class="flex items-center justify-between gap-4 p-3 bg-white hover:bg-gray-50 transition-colors"
          >
            <!-- Info -->
            <div class="flex items-center gap-3">
              <Image
                v-if="item.image"
                :src="item.image.thumbnail"
                class="w-12 h-12 rounded object-cover shadow-sm border"
              />
              <div>
                <div class="font-medium text-gray-700">{{ item.name }}</div>
                <div class="flex mt-1 gap-3 text-xs text-gray-500">
                  <span>Code: {{ item.code || "-" }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="text-sm text-gray-500 italic p-3 bg-gray-50 rounded-md">
            {{trans('No trade units available')}}
        </div>
      </div>
    </div>

    <!-- Debug -->
<!--     <pre>
{{ data }}
    </pre> -->
  </div>
</template>
