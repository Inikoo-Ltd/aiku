<script setup lang="ts">
import { ref, computed } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import EmptyState from "@/Components/Utils/EmptyState.vue";
import Image from "@/Components/Image.vue";
import { getStyles } from "@/Composables/styles";
import { routeType } from "@/types/route";
import FormEditProductCategory from "@/Components/DepartmentAndFamily/FormEditProductCategory.vue";
import Dialog from "primevue/dialog";
import { trans } from "laravel-vue-i18n";

const props = defineProps<{
  modelValue: {
    collections: Array<Object>
    sub_departments: Array<Object>
    container: {
      properties: object
    }
  }
  webpageData?: any;
  blockData?: object;
  screenType: "mobile" | "tablet" | "desktop";
  routeEditSubDepartment?: routeType;
}>();

const selectedSubDepartment = ref<null | {
  id: number;
  name: string;
  description: string;
  image?: string;
}>(null);

const showDialog = ref(false);





// Default col count per screenType if not defined by user
const fallbackPerRow = {
  desktop: 4,
  tablet: 3,
  mobile: 2,
};

const perRow = computed(() => {
  return (
    props.modelValue?.settings?.per_row?.[props.screenType] ||
    fallbackPerRow[props.screenType] ||
    1
  );
});

const gridColsClass = computed(() => `grid-cols-${perRow.value}`);

const screenClass = computed(() => {
  switch (props.screenType) {
    case "mobile":
      return "px-4 py-6 text-sm";
    case "tablet":
      return "px-6 py-8 text-base";
    case "desktop":
    default:
      return "px-12 py-12 text-base";
  }
});


const mergedItems = computed(() => {
  const subs = props.modelValue?.sub_departments ?? []
  const collections = props.modelValue?.collections ?? []

  return [...subs, ...collections]
})


</script>

<template>
  <div class="mx-auto" :class="screenClass" :style="getStyles(modelValue?.container?.properties, screenType)">
    <div v-if="modelValue?.sub_departments?.length">
      <div class="grid gap-4" :class="gridColsClass">
        <button v-for="item in mergedItems" :key="item?.code" :style="getStyles(modelValue?.card?.container?.properties, screenType)"
          class="flex items-center gap-3 border border-gray-600 rounded-xl px-4 py-3 text-sm font-medium text-gray-800 bg-white hover:bg-gray-50 transition-all w-full">
          <span class="flex-1 text-center">{{ item?.name }}</span>
        </button>
      </div>
    </div>

    <div v-else class="text-center text-gray-500 py-6">
      <EmptyState :data="{
        title: trans('There is no published sub-department webpages'),
        description: 'Please make sure the sub-departments, have published webpage.',
      }" />
    </div>
  </div>
</template>
