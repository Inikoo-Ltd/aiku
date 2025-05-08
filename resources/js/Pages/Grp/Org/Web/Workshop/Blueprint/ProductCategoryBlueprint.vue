<script setup lang="ts">
import SetVisibleList from '@/Components/Departement&Family/SetVisibleList.vue';
import { faChevronCircleLeft, faChevronCircleRight, faSave } from '@far'
import { faExclamation, faWindWarning } from '@fas';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Message from 'primevue/message';
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { routeType } from '@/types/route';
import EditPreviewBluprintData from "@/Components/Departement&Family/EditPreviewBluprintData.vue";

const props = defineProps<{
  title: string,
  pageHead: object,
  productCategory: {
    data : Object
  }
  families: {
    data : Object
  }
  updateRoute: routeType
  upload_image_route : routeType
  web_block_types : {
    data : Array<any>
  }
}>()

console.log(props)


</script>

<template>

  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead" />
  <div class="px-4 pb-8 m-5">
    <Message severity="warn" closable>
      <template #icon>
        <FontAwesomeIcon :icon="faExclamation" />
      </template>
      <span class="ml-2">Right Now you follow the master data </span>
    </Message>
    <div class="grid grid-cols-1 lg:grid-cols-[30%_1fr] gap-6 mt-4 ">
      <EditPreviewBluprintData 
            title="Department" 
            :data="productCategory.data" 
            :update_route="updateRoute"
            :web_block_types="web_block_types" 
            :upload_image_route="upload_image_route">
      </EditPreviewBluprintData>

      <SetVisibleList :title="'Family List'" :list_data="families.data" :update-route="updateRoute" />
    </div>
  </div>



</template>
