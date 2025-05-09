<script setup lang="ts">
import SetVisibleList from '@/Components/Departement&Family/SetVisibleList.vue';
import EditPreviewBluprintData from "@/Components/Departement&Family/EditPreviewBluprintData.vue";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import ConfirmDialog from 'primevue/confirmdialog';
import { notify } from '@kyvg/vue3-notification';
import { router } from '@inertiajs/vue3';

import { useConfirm } from "primevue/useconfirm";
import { Head } from "@inertiajs/vue3";
import Message from 'primevue/message';
import SelectButton from 'primevue/selectbutton';

import { faExclamationTriangle } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

import { capitalize } from "@/Composables/capitalize";
import type { routeType } from '@/types/route';

const props = defineProps<{
  title: string,
  pageHead: object,
  productCategory: {
    data: Record<string, any>
  },
  families: {
    data: Record<string, any>
  },
  updateRoute: routeType,
  upload_image_route: routeType,
  web_block_types: {
    data: any[]
  }
}>();

const confirm = useConfirm("follow-master");

const options = [
  { name: "Follow Master", value: true },
  { name: "Stand-alone", value: false }
];

const onSaveAll = () => {
  router.patch(
    route(props.updateRoute.name, {
      ...props.updateRoute.parameters,
    }),
    {
      follow_master: props.productCategory.data.follow_master,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        notify({
          title: "Saved Successfully",
          text: "Your follow master setting has been updated.",
          type: "success"
        });
      },
      onError: (errors) => {
        console.error("Save failed:", errors);
        notify({
          title: "Failed to Save",
          text: "Please check your input or try again.",
          type: "error"
        });
      }
    }
  );
};



const confirmFollowMaster = (item) => {
    confirm.require({
        message: item
            ? 'By following the master, this data will be synced from the master source and cannot be edited manually.'
            : 'By setting this as Stand-alone, you will need to manage and edit the blueprint yourself.',
        header: item
            ? 'Follow Master Data?'
            : 'Set as Stand-alone?',
        icon: 'pi pi-exclamation-triangle',
        group: "follow-master",
        rejectProps: {
            label: 'Cancel',
            severity: 'secondary',
            outlined: true,
        },
        acceptProps: {
            label: 'Save',
            severity: 'primary',
        },
        accept: () => {
            onSaveAll();
        },
    });
};

</script>

<template>

  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead" />

  <div class="px-4 pb-8 m-5">
    <Message severity="warn" closable>
      <template #icon>
        <FontAwesomeIcon :icon="faExclamation" />
      </template>
      <span class="ml-2">Right now you follow the master data</span>
    </Message>

    <div class="grid grid-cols-1 lg:grid-cols-[30%_1fr] gap-6 mt-4">
      <div>
        <SelectButton 
          v-model="productCategory.data.follow_master" 
          @update:model-value="(item)=>confirmFollowMaster(item)"
          :options="options" 
          optionLabel="name"
          optionValue="value" 
          class="mb-4"
        />

        <EditPreviewBluprintData :title="productCategory.data.type !== 'family' ? 'Department' : 'Family'"
          :data="productCategory.data" :update_route="updateRoute" :web_block_types="web_block_types"
          :upload_image_route="upload_image_route" />
      </div>

      <SetVisibleList title="Family List" :list_data="families.data" :update-route="updateRoute" />
    </div>
  </div>

  <ConfirmDialog group="follow-master">
        <template #icon>
            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
        </template>
    </ConfirmDialog>
</template>
