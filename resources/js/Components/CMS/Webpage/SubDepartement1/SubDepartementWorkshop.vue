<script setup lang="ts">
import { ref, computed } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import EmptyState from "@/Components/Utils/EmptyState.vue";
import Image from "@/Components/Image.vue";
import { getStyles } from "@/Composables/styles";
import { routeType } from "@/types/route";
import FormEditProductCategory from "@/Components/Departement&Family/FormEditProductCategory.vue";
import Dialog from "primevue/dialog";
import { trans } from "laravel-vue-i18n";

const props = defineProps<{
  modelValue: Record<string, any>;
  webpageData?: any;
  blockData?: object;
  screenType: "mobile" | "tablet" | "desktop";
  routeEditSubDepartement?: routeType;
}>();

const selectedSubDepartment = ref<null | {
  id: number;
  name: string;
  description: string;
  image?: string;
}>(null);

const showDialog = ref(false);

function openModal(subDept: any) {
  if (props.routeEditSubDepartement) {
    selectedSubDepartment.value = {
      id: subDept.id,
      name: subDept.name,
      description: subDept.description,
      image: subDept.image,
    };
    showDialog.value = true;
  }
}

function handleSaved(updatedSubDept: any) {
  const index = props.modelValue.sub_departments.findIndex(
    (item: any) => item.id === updatedSubDept.id
  );

  if (index !== -1) {
    props.modelValue.sub_departments[index] = {
      ...props.modelValue.sub_departments[index],
      ...updatedSubDept,
    };
  }
  closeModal();
}

function closeModal() {
  showDialog.value = false;
  selectedSubDepartment.value = null;
}

// Default col count per screenType if not defined by user
const fallbackPerRow = {
  desktop: 4,
  tablet: 3,
  mobile: 2,
};

const perRow = computed(() => {
  return (
    props.modelValue?.setting?.per_row?.[props.screenType] ||
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
</script>

<template>
  <div
    class="mx-auto"
    :class="screenClass"
    :style="getStyles(modelValue?.container?.properties, screenType)"
  >
    <h2 class="text-2xl font-bold mb-6">Browse by sub-department:</h2>
    <div v-if="modelValue?.sub_departments?.length">
      <div class="grid gap-4" :class="gridColsClass">
        <button
          v-for="item in modelValue.sub_departments"
          :key="item.code"
          class="flex items-center gap-3 border rounded px-4 py-3 text-sm font-medium text-gray-800 bg-white hover:bg-gray-50 transition-all w-full"
          @click="openModal(item)"
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
            />
          </div>
          <span class="flex-1 text-center">{{ item.name }}</span>
        </button>
      </div>
    </div>

    <div v-else class="text-center text-gray-500 py-6">
      <EmptyState :data="{
        title: trans('There is no published sub-department webpages'),
        description: 'Please make sure the sub-departments, have published webpage.',
      }" />
    </div>

    <!-- Dialog Tetap -->
    <Dialog
      :header="`Edit ${selectedSubDepartment?.name}`"
      v-model:visible="showDialog"
      :modal="true"
      :style="{ width: '500px', zIndex: 20 }"
      :closable="true"
      @hide="closeModal"
    >
      <FormEditProductCategory
        v-if="selectedSubDepartment"
        :key="selectedSubDepartment.id"
        :data="selectedSubDepartment"
        :saveRoute="routeEditSubDepartement"
        @saved="handleSaved"
      />
    </Dialog>
  </div>
</template>
