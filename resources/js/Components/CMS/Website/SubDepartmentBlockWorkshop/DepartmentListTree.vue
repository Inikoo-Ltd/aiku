<script setup lang="ts">
import { ref } from "vue";
import axios from "axios";
import { faTh, faCircle, faChevronRight, faChevronDown } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import EmptyState from "@/Components/Utils/EmptyState.vue";
import { faEmptySet } from "@far";
import { routeType } from "@/types/route";

library.add(faTh, faCircle, faChevronRight, faChevronDown);

const props = defineProps<{
  dataList: {
    data: Array<{
      key: string | number;
      name: string;
      sub_departments_route: routeType
    }>[];
  };
  active: String
}>();

const emits = defineEmits<{
  (e: "changeDepartment", value: object): void;
}>();

const openIndex = ref<number | null>(null);
const loading = ref(false);
const subDepartments = ref<any[]>([]);

async function toggle(index: number) {
  const dept = props.dataList.data[index];

  if (openIndex.value === index) {
    openIndex.value = null;
    subDepartments.value = [];
    return;
  }

  openIndex.value = index;
  loading.value = true;

  try {
    const response = await axios.get(route(
      dept.sub_departments_route.name,
      dept.sub_departments_route.parameters
    ));
    subDepartments.value = response.data.data || [];
    emits("changeDepartment", { ...dept, sub_departments: subDepartments.value });
  } catch (err) {
    console.error("Error fetching sub-departments", err);
    subDepartments.value = [];
  } finally {
    loading.value = false;
  }
}
</script>


<template>
  <div class="mx-auto">
    <ul class="space-y-3">
      <li v-for="(dept, index) in props.dataList.data" :key="dept.slug"
        class="border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow" :class="[
          'rounded-lg shadow-sm transition-shadow',
          dept.slug === props.active
            ? 'border border-blue-500 ring-2 ring-blue-300 shadow-md'
            : 'border border-gray-200 hover:shadow-md hover:border-gray-300'
        ]">
        <div class="flex items-center justify-between px-4 py-3 cursor-pointer group hover:bg-gray-50 rounded-t-lg"
          @click="toggle(index)">
          <div class="flex items-center gap-3 text-gray-800 font-medium">
            <FontAwesomeIcon :icon="faTh" class="text-blue-500 w-4 h-4" />
            <span class="group-hover:underline">{{ dept.name }}</span>
          </div>
          <FontAwesomeIcon :icon="openIndex === index ? faChevronDown : faChevronRight"
            class="text-gray-500 w-3 h-3 transition-transform duration-200" />
        </div>

        <transition name="fade">
          <div v-show="openIndex === index">
            <ul v-if="subDepartments.length"
              class="px-6 py-2 bg-gray-50 border-t border-gray-100 rounded-b-lg space-y-2">
              <li v-for="sub in subDepartments" :key="sub.key"
                class="flex items-center gap-2 text-sm text-gray-700 hover:text-blue-600 transition-colors">
                <FontAwesomeIcon :icon="faCircle" class="text-gray-400 w-2" />
                <span>{{ sub.name }}</span>
              </li>
            </ul>

            <div v-else-if="loading" class="px-6 py-3 text-sm text-gray-400">Loading...</div>
            <div v-else class="flex items-center gap-2 px-6 py-1 text-gray-400 text-xs italic select-none">
              <FontAwesomeIcon :icon="faEmptySet" :class="'w-4 h-4 stroke-current'" />
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
