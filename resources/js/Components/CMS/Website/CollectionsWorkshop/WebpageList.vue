<script setup lang="ts">
import { ref } from "vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faTh, faCircle, faChevronRight, faChevronDown } from "@fas";
import { faEmptySet } from "@fal";
import type { routeType } from "@/types/route";

library.add(faTh, faCircle, faChevronRight, faChevronDown, faEmptySet);

type WebpageItem = {
  key: string | number;
  slug: string;
  code: string;
  name: string;
  collections?: Array<{
    key: string | number;
    name: string;
  }>;
  families_route: routeType;
};

const props = defineProps<{
  dataList: { data: WebpageItem[] };
  active: string;
}>();

const emits = defineEmits<{
  (e: "changeDepartment", value: { collections: WebpageItem["collections"] }): void;
}>();

const openIndex = ref<number | null>(null);
const loading = ref(false);
const collections = ref<WebpageItem["collections"]>([]);

function toggle(index: number) {
  const webpage = props.dataList.data[index];
  if (openIndex.value === index) {
    // Toggle collapse
    openIndex.value = null;
    collections.value = [];
    return;
  }

  openIndex.value = index;
  collections.value = webpage.collections ?? [];
  emits("changeDepartment", { webpage : webpage, collections: collections.value });
}
</script>

<template>
  <div class="mx-auto">
    <ul class="space-y-3">
      <li
        v-for="(webpage, index) in props.dataList.data"
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
