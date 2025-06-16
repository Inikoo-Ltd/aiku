<script setup lang="ts">
import { ref } from 'vue'
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import Family1Render from './Families1Render.vue'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import { getStyles } from "@/Composables/styles"
import Dialog from 'primevue/dialog';
import { routeType } from '@/types/route'
import FormEditProductCategory from "@/Components/Departement&Family/FormEditProductCategory.vue";

library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)

const props = defineProps<{
  modelValue: {
    families: {
      name: string
      description: string
      images: { source: string }[]
    }[]
  }
  routeEditfamily? : routeType
  webpageData?: any
  blockData?: Object
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const selectedSubDepartment = ref<null | {
  id: number;
  name: string;
  description: string;
  image?: string;
}>(null);

const showDialog = ref(false);

function openModal(subDept: any) {
  if (props.routeEditfamily) {
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
  const index = props.modelValue.families.findIndex(
    (item: any) => item.id === updatedSubDept.id
  );

  if (index !== -1) {
    props.modelValue.families[index] = {
      ...props.modelValue.families[index],
      ...updatedSubDept,
    };
  }
  console.log(index, props.modelValue.families[index])
  closeModal();
}


function closeModal() {
  showDialog.value = false;
  selectedSubDepartment.value = null;
}
</script>

<template>

   <div
  v-if="props.modelValue?.families && props.modelValue?.families?.length"
  class="px-4 py-10 mx-[30px]"
  :style="getStyles(modelValue.container?.properties, screenType)"
>
  <h2 class="text-2xl font-bold mb-6">Browse By Product Lines:</h2>
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-8">
     <div v-for="(item, index) in props.modelValue.families" :key="index"   @click="openModal(item)">
      <Family1Render :data="item" />
    </div>
  </div>
</div>


  <EmptyState v-else :data="{ title: 'Empty Families' }" />


   <Dialog :header="`Edit ${selectedSubDepartment?.name}`" v-model:visible="showDialog" :modal="true"
      :style="{ width: '500px' }" :closable="true" @hide="closeModal">
      <FormEditProductCategory v-if="selectedSubDepartment" :key="selectedSubDepartment.id" :data="selectedSubDepartment"
        :saveRoute="routeEditfamily" @saved="handleSaved" />
    </Dialog>
</template>
