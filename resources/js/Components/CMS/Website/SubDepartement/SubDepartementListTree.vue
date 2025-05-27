<script setup lang="ts">
import { faTh, faCircle, faChevronRight, faChevronDown } from "@fas";
import { ref } from "vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import EmptyState from "@/Components/Utils/EmptyState.vue";

library.add(faTh, faCircle, faChevronRight, faChevronDown);

const props = defineProps<{
  dataList: {
    data: Array<{
      key: string | number;
      name: string;
      sub_departments?: Array<{
        key: string | number;
        name: string;
      }>;
    }>;
  };
}>();

const emits = defineEmits<{
    (e: 'changeDepartment', value: object): void
}>()


const openIndex = ref<number | null>(null);

function toggle(index: number) {
  openIndex.value = openIndex.value === index ? null : index;
  emits('changeDepartment', props.dataList.data[index]);
}

</script>

<template>
  <div class="max-w-4xl mx-auto px-4 py-6">
    <h4 class="text-xl font-extrabold text-gray-900 mb-6 border-b-4 border-blue-500 pb-2">
      SubDepartement Overview
    </h4>

    <ul class="space-y-3">
      <li
        v-for="(dept, index) in props.dataList.data"
        :key="dept.key"
        class="border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow"
      >
        <div
          class="flex items-center justify-between px-4 py-3 cursor-pointer group hover:bg-gray-50 rounded-t-lg"
          @click="toggle(index)"
        >
          <div class="flex items-center gap-3 text-gray-800 font-medium">
            <FontAwesomeIcon :icon="faTh" class="text-blue-500 w-4 h-4" />
            <span class="group-hover:underline">{{ dept.name }}</span>
          </div>
          <FontAwesomeIcon
            :icon="openIndex === index ? faChevronDown : faChevronRight"
            class="text-gray-500 w-3 h-3 transition-transform duration-200"
          />
        </div>

        <transition name="fade">
          <div v-show="openIndex === index">
            <ul
              v-if="dept.families?.length"
              class="px-6 py-2 bg-gray-50 border-t border-gray-100 rounded-b-lg space-y-2"
            >
              <li
                v-for="sub in dept.families"
                :key="sub.key"
                class="flex items-center gap-2 text-sm text-gray-700 hover:text-blue-600 transition-colors"
              >
                <FontAwesomeIcon :icon="faCircle" class="text-gray-400 w-2" />
                <span>{{ sub.name }}</span>
              </li>
            </ul>
            <EmptyState v-else class="px-6 py-2 text-gray-500 text-sm" />
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
