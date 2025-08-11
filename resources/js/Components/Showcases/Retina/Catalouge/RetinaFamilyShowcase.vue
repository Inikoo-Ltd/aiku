<script setup lang="ts">
import { routeType } from '@/types/route';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faImage } from "@far";
import Image from "@/Components/Image.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import {faAlbumCollection } from "@fal";
import ButtonAddCategoryToPortfolio from "@/Components/Iris/Products/ButtonAddCategoryToPortfolio.vue"

library.add(faAlbumCollection);

const props = defineProps<{
  data: {
    family: {
      data: {},
    },
    routeList: {
      collectionRoute: routeType
    },
    routes: {
      detach_family: routeType
    }
  }
}>()


</script>

<template>
  <div class="px-4 pb-8 m-5">
    <div class="grid grid-cols-1 lg:grid-cols-[30%_1fr] gap-6 mt-4 ">
      <div>
        <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
          <div class="flex items-center justify-between mb-6">
            <div class="flex-1 mx-4">
              <div class="bg-white rounded-lg shadow hover:shadow-md transition duration-300">
                <Image v-if="data.family.data?.image" :src="data.family.data?.image" :imageCover="true"
                  class="w-full h-40 object-cover rounded-t-lg" />
                <div v-else class="flex justify-center items-center bg-gray-100 w-full h-48">
                  <FontAwesomeIcon :icon="faImage" class="w-8 h-8 text-gray-400" />
                </div>
              </div>
            </div>
          </div>


          <div class="border-t pt-4 space-y-4 text-gray-700">
            <div class="font-bold">
              {{ data.family.data?.name || "No label" }}
            </div>
            <div class="text-sm h-64 overflow-y-auto pr-1 text-justify" v-html="data.family.data?.description">
            </div>
          </div>
        </div>
      </div>      
    </div>
  </div>
</template>
