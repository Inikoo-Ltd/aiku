<script setup lang="ts">
import { computed } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import EmptyState from "@/Components/Utils/EmptyState.vue";
import Image from "@/Components/Image.vue";
import { routeType } from "@/types/route";
import { getStyles } from "@/Composables/styles";

const props = defineProps<{
  modelValue: Record<string, any>;
  webpageData?: any;
  blockData?: object;
  screenType: "mobile" | "tablet" | "desktop";
  routeEditSubDepartement?: routeType;
}>();

const defaultCols = {
  mobile: 2,
  tablet: 3,
  desktop: 4,
};

const gridColsClass = computed(() => {
  const perRow = props.modelValue?.settings?.per_row || {};
  const col = perRow[props.screenType] ?? defaultCols[props.screenType];
  return `grid-cols-${col}`;
});
</script>

<template>
  <div
    class="mx-auto px-4 py-12"
    :style="getStyles(modelValue.container?.properties, screenType)"
  >
    <h2 class="text-2xl font-bold mb-6">Browse By Collections</h2>

    <div v-if="modelValue?.collections?.length">
      <div :class="['grid gap-4', gridColsClass]">
        <button
          v-for="item in modelValue.collections"
          :key="item.code"
          class="flex items-center gap-3 border rounded px-4 py-3 text-sm font-medium text-gray-800 bg-white hover:bg-gray-50 transition-all w-full"
        >
          <div class="flex items-center justify-center w-5 h-5 shrink-0 text-xl">
            <FontAwesomeIcon
              v-if="item.icon"
              :icon="item.icon"
              class="text-xl w-5 h-5"
            />
            <Image
              v-else
              :src="item.image"
              class="w-full h-full object-contain"
              :alt="`Collection - ${item.name}`"
            />
          </div>
          <span class="flex-1 text-center">{{ item.name }}</span>
        </button>
      </div>
    </div>

    <div v-else class="text-center text-gray-500 py-6">
      <EmptyState
        :data="{
          title: 'No Collection Available',
          description: 'Please check back later or contact support.',
        }"
      />
    </div>
  </div>
</template>

<style scoped>
</style>
