<script setup lang="ts">
import { faTh } from "@fas";
import { ref } from "vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { routeType } from "@/types/route";

library.add(faTh);

const props = defineProps<{
  dataList: {
    data: Array<{
      key: string | number;
      name: string;
      slug: string;
      families_route: routeType;
    }>;
  };
  active: string;
}>();

const emits = defineEmits<{
  (e: "ChangeFamily", value: object): void;
}>();

function selectFamily(dept: any) {
  emits("ChangeFamily", { family: dept });
}
console.log(props);
</script>

<template>
  <div class="">
    <ul class="space-y-3">
      <li
        v-for="dept in props.dataList.data"
        :key="dept.slug"
        @click="selectFamily(dept)"
        :class="[
          'flex items-center gap-3 px-4 py-3 rounded-lg cursor-pointer transition-all',
          dept.slug === props.active
            ? 'border border-blue-500 ring-2 ring-blue-300 shadow-md bg-blue-50'
            : 'border border-gray-200 hover:border-gray-300 hover:bg-gray-50'
        ]"
      >
        <FontAwesomeIcon :icon="faTh" class="text-blue-500 w-4 h-4" />
        <span class="text-gray-800 font-medium">
          {{ dept.name }}
        </span>
      </li>
    </ul>
  </div>
</template>