<script setup lang="ts">
import { ref, computed } from "vue";
import EmptyState from "@/Components/Utils/EmptyState.vue";
import { getStyles } from "@/Composables/styles";
import { routeType } from "@/types/route";
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
  on progresss
</template>
