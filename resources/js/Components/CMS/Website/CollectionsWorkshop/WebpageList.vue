<script setup lang="ts">
import { ref } from "vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faTh, faCircle, faChevronRight, faChevronDown } from "@fas";
import { faEmptySet } from "@fal";
import type { routeType } from "@/types/route";
import axios from "axios";

library.add(faTh, faCircle, faChevronRight, faChevronDown, faEmptySet);


const props = defineProps<{
  dataList: Array<any>
  active: string;
}>();

const emits = defineEmits<{
  (e: "onChangeWebpage", value: { collections: any}): void;
}>();

const openIndex = ref<number | null>(null);
const loading = ref(false);
const collections = ref([]);

async function toggle(index: number) {
  const dept = props.dataList[index];

  if (openIndex.value === index) {
    openIndex.value = null;
    collections.value = [];
    return;
  }

  openIndex.value = index;
  loading.value = true;

  try {
    const response = await axios.get(route(
      dept.collections_route.name,
      dept.collections_route.parameters
    ));
    collections.value = response.data.data || [];
    emits("onChangeWebpage", { ...dept, collections: collections.value });
  } catch (err) {
    console.error("Error fetching sub-departments", err);
    collections.value = [];
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="mx-auto">
    <ul class="space-y-3">
      <li
        v-for="(webpage, index) in props.dataList"
        :key="webpage.key"
        :class="[
          'border rounded-lg shadow-sm transition-shadow',
          webpage.slug === props.active
            ? 'border-blue-500 ring-2 ring-blue-300 shadow-md'
            : 'border-gray-200 hover:shadow-md hover:border-gray-300'
        ]"
      >
        <div
          class="flex items-center justify-between px-4 py-3 cursor-pointer group hover:bg-gray-50 rounded-t-lg"
          @click="toggle(index)"
        >
          <div class="flex items-center gap-3 text-gray-800 font-medium">
            <FontAwesomeIcon :icon="faTh" class="text-blue-500 w-4 h-4" />
            <span class="group-hover:underline">{{ webpage.code }}</span>
          </div>
          <FontAwesomeIcon
            :icon="openIndex === index ? faChevronDown : faChevronRight"
            class="text-gray-500 w-3 h-3 transition-transform duration-200"
          />
        </div>

        <transition name="fade">
          <div v-show="openIndex === index">
            <ul
              v-if="collections?.length"
              class="px-6 py-2 bg-gray-50 border-t border-gray-100 rounded-b-lg space-y-2"
            >
              <li
                v-for="sub in collections"
                :key="sub.key"
                class="flex items-center gap-2 text-sm text-gray-700 hover:text-blue-600 transition-colors"
              >
                <FontAwesomeIcon :icon="faCircle" class="text-gray-400 w-2" />
                <span>{{ sub.name }}</span>
              </li>
            </ul>

            <div
              v-else-if="loading"
              class="px-6 py-3 text-sm text-gray-400"
            >
              Loading...
            </div>

            <div
              v-else
              class="flex items-center gap-2 px-6 py-1 text-gray-400 text-xs italic select-none"
            >
              <FontAwesomeIcon :icon="faEmptySet" class="w-4 h-4" />
              <span>No sub-departments found</span>
            </div>
          </div>
        </transition>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
