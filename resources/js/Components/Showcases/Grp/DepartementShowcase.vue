<script setup lang="ts">
import SetVisibleList from '@/Components/Departement&Family/SetVisibleList.vue';
import { faChevronCircleLeft, faChevronCircleRight, faSave } from '@far'
import { faExclamation, faWindWarning } from '@fas';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Message from 'primevue/message';


const props = defineProps<{
  data: {
    families: Array<any>
    departement: {
      data: {
        name: string,
        description: string
      }
    }
  }
}>()

const goToPrev = () => {
  console.log('Previous clicked')
}

const goToNext = () => {
  console.log('Next clicked')
}

/* const componentsDepartment: Record<string, Component> = {
    'department-1': DepartmentRender,
}

const getComponentDepartment = (componentName: string) => {
    return componentsDepartment[componentName]
} */

</script>

<template>
  <div class="px-4 pb-8 m-5">
    <Message severity="warn" closable>
      <template #icon>
        <FontAwesomeIcon :icon="faExclamation" />
      </template>
      <span class="ml-2">Right Now you follow the master data </span>
    </Message>
    <div class="grid grid-cols-1 lg:grid-cols-[30%_1fr] gap-6 mt-4 ">
      <div >
        <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
          <div class="flex justify-between items-center border-b pb-4 mb-4">
          <h3 class="text-xl font-semibold">Departement</h3>
        </div>
        <div class="flex items-center justify-between mb-6">
          <button @click="goToPrev" aria-label="Previous">
            <FontAwesomeIcon :icon="faChevronCircleLeft" class="text-xl text-gray-600 hover:text-primary" />
          </button>
          <div class="flex-1 mx-4">
          </div>
          <button @click="goToNext" aria-label="Next">
            <FontAwesomeIcon :icon="faChevronCircleRight" class="text-xl text-gray-600 hover:text-primary" />
          </button>
        </div>


        <div class="border-t pt-4 space-y-4 text-sm text-gray-700">
          <div class="text-sm font-medium">
            <span>{{ data.departement.data.name || 'No label' }}</span>
          </div>
          <div class="text-md">
            <span class="text-gray-400">{{ data.departement.data.description || 'No description' }}</span>
          </div>
        </div>
        </div>
        
      </div>

      <SetVisibleList :title="'Family List'" :list_data="data.families.data" />
    </div>
  </div>



</template>
