<script setup lang="ts">
import { ref } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import EmptyState from "@/Components/Utils/EmptyState.vue";
import Image from "@/Components/Image.vue";
import { routeType } from "@/types/route";

const props = defineProps<{
  modelValue: Record<string, any>;
  webpageData?: any;
  blockData?: object;
  screenType: "mobile" | "tablet" | "desktop";
  routeEditSubDepartement?: routeType;
}>();


</script>

<template>
  <div class="mx-auto px-4 py-12">
    <h2 class="text-2xl font-bold mb-6">Browse By Collections :</h2>

    <div v-if="modelValue?.collections?.length">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <button v-for="item in modelValue.collections" :key="item.code"
          class="flex items-center gap-3 border rounded px-4 py-3 text-sm font-medium text-gray-800 bg-white hover:bg-gray-50 transition-all w-full">
          <div class="flex items-center justify-center w-5 h-5 shrink-0 text-xl ">
            <FontAwesomeIcon v-if="item.icon" :icon="item.icon" class="text-xl w-5 h-5" />
            <Image v-else :src="item.image" class="w-full h-full object-contain" />
          </div>
          <span class="flex-1 text-center">{{ item.name }}</span>
        </button>
      </div>
    </div>


    <div v-else class="text-center text-gray-500 py-6">
      <EmptyState :data="{
        title: 'No Collection Available',
        description: 'Please check back later or contact support.',
      }" />
    </div>

  </div>
</template>

<style scoped>
</style>
