<script setup lang="ts">
import { ref } from "vue";
import Dialog from 'primevue/dialog';
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import EmptyState from "@/Components/Utils/EmptyState.vue";
import Image from "@/Components/Image.vue";
import { getStyles } from "@/Composables/styles";
import { routeType } from "@/types/route";
import FormEditSubDepertment from "./FormEditSubDepertment.vue";

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
  selectedSubDepartment.value = {
    id: subDept.id,
    name: subDept.name,
    description: subDept.description,
    image: subDept.image,
  };
  showDialog.value = true;
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
  console.log(index,props.modelValue.sub_departments[index])
  closeModal();
}


function closeModal() {
  showDialog.value = false;
  selectedSubDepartment.value = null;
}
</script>

<template>
  <div class="mx-auto px-4 py-12" :style="getStyles(modelValue?.container?.properties, screenType)">
    <h2 class="text-2xl font-bold mb-6">Browse By Sub-department:</h2>

    <div v-if="modelValue?.sub_departments?.length">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <button v-for="item in modelValue.sub_departments" :key="item.code"
          class="flex items-center gap-2 border rounded-xl px-4 py-3 text-sm font-medium text-gray-800 bg-white hover:bg-gray-50 transition-all"
          @click="openModal(item)">
          <FontAwesomeIcon v-if="item.icon" :icon="item.icon" class="text-lg w-5 h-5" />
          <div v-else class="w-5 h-5">
            <Image :src="item.image_thumbnail" class="w-full h-full object-contain" />
          </div>
          {{ item.name }}
        </button>
      </div>
    </div>

    <div v-else class="text-center text-gray-500 py-6">
      <EmptyState :data="{
        title: 'No Sub-departments Available',
        description: 'Please check back later or contact support.',
      }" />
    </div>

    <!-- PrimeVue Dialog -->
    <Dialog :header="`Edit ${selectedSubDepartment?.name}`" v-model:visible="showDialog" :modal="true"
      :style="{ width: '500px' }" :closable="true" @hide="closeModal">
      <FormEditSubDepertment v-if="selectedSubDepartment" :key="selectedSubDepartment.id" :data="selectedSubDepartment"
        :saveRoute="routeEditSubDepartement" @saved="handleSaved" />
    </Dialog>
  </div>
</template>

<style scoped>
/* Optional styling for dialog content */
</style>
