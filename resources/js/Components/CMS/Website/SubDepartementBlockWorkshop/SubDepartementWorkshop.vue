<script setup lang="ts">
import { faCube, faLink } from "@fal";
import { faStar, faCircle, faChevronLeft, faChevronRight, faDesktop, faInfoCircle } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { ref, provide, inject, toRaw } from "vue";
import EmptyState from "@/Components/Utils/EmptyState.vue";
import SideMenuDepartementWorkshop from "./SideMenuSubDepartementWorkshop.vue";
import { getComponent } from "@/Composables/getWorkshopComponents";
import { router } from "@inertiajs/vue3";
import { notify } from "@kyvg/vue3-notification";
import { routeType } from "@/types/route";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { layoutStructure } from '@/Composables/useLayoutStructure';
import Drawer from 'primevue/drawer';
import DepartementListTree from "./DepartementListTree.vue"
import Button from "@/Components/Elements/Buttons/Button.vue";

library.add(faCube, faLink, faStar, faCircle, faChevronLeft, faChevronRight, faDesktop);

const props = defineProps<{
  data: {
    web_block_types: any;
    autosaveRoute: routeType;
    layout: any;
    departments: any[];
    update_sub_department_route : routeType;
  };
}>();

const layoutTheme = inject('layout', layoutStructure);
const isModalOpen = ref(false);
const isLoadingSave = ref(false);
const visibleDrawer = ref(false);

// Keep local state for layout and the UI-only departement/sub_departments
const layout = ref(props.data.layout);

// Update departement and sub_departments locally only, not saved
const onChangeDepartment = (value: any) => {
  if (layout.value?.data?.fieldValue) {
    layout.value.data.fieldValue.departement = value; // full value for UI
    layout.value.data.fieldValue.sub_departments = value.sub_departments || [];
  }
};

const autosave = () => {
  // Deep clone to safely modify payload without touching reactive data
  const payload = JSON.parse(JSON.stringify(toRaw(layout.value)));
   console.log('autosave payload', layout.value);
  // Remove departement & sub_departments before sending to backend
  if (payload.data?.fieldValue) {
    delete payload.data.fieldValue.departement;
    delete payload.data.fieldValue.sub_departments;
  }

  router.patch(
    route(props.data.autosaveRoute.name, props.data.autosaveRoute.parameters),
    { layout: payload },
    {
      onStart: () => {
        isLoadingSave.value = true;
      },
      onFinish: () => {
        isLoadingSave.value = false;
      },
      onSuccess: () => {
        // Update only backend saved layout data — keep UI-only fields in local layout.value
        props.data.layout = payload;
        notify({
          title: 'Autosave Successful',
          text: 'Your changes have been saved.',
          type: 'success',
        });
      },
      onError: (errors) => {
        notify({
          title: 'Autosave Failed',
          text: errors?.message || 'Unknown error occurred.',
          type: 'error',
        });
      },
    }
  );
};

const onPickTemplate = (template: any) => {
  isModalOpen.value = false;
  layout.value = template;
  layout.value.data.fieldValue = {}
  autosave()
};

const currentView = ref("desktop");
provide("currentView", currentView);

console.log('departement-props', props.data);
</script>

<template>
  <div class="h-[85vh] grid grid-cols-12 gap-4 p-3">
    <div class="col-span-3 bg-white rounded-xl shadow-md p-4 overflow-y-auto border">
      <SideMenuDepartementWorkshop
        :data="layout"
        :webBlockTypes="data.web_block_types"
        @auto-save="autosave"
        @set-up-template="onPickTemplate"
        :dataList="data.departments"
      />
    </div>

    <div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-auto border">
      <div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b">
        <!-- Left: Desktop View Icon -->
        <div class="py-1 px-2 cursor-pointer lg:block hidden selected-bg" v-tooltip="'Desktop view'">
          <FontAwesomeIcon icon="fas fa-desktop" fixed-width aria-hidden="true" />
        </div>

        <!-- Right: Preview Label -->
        <div class="text-sm text-gray-600 italic mr-3 cursor-pointer" @click="visibleDrawer = true">
          <span v-if="layout?.data?.fieldValue?.departement?.name">
            Preview: <strong>{{ layout.data.fieldValue.departement?.name }}</strong>
          </span>
          <span v-else>Pick The departement</span>
        </div>
      </div>

      <div v-if="layout?.code" >
        <component
          class="w-full relative flex-1 overflow-auto border-4 border-[#4F46E5] active-block"
          :is="getComponent(layout.code)"
          :modelValue="{
            ...layout.data.fieldValue,
            departement: layout.data.fieldValue?.departement || null,
            sub_departments: layout.data.fieldValue?.sub_departments || []
          }"
          :routeEditSubDepartement="data.update_sub_department_route"
        />
      </div>

      <div v-else class="flex flex-col items-center justify-center gap-3 text-center text-gray-500 flex-1 min-h-[300px]" style="height: 100%;">
        <div class="flex flex-col items-center gap-2">
          <FontAwesomeIcon :icon="faInfoCircle" class="text-4xl" />
          <h3 class="text-lg font-semibold">No department selected</h3>
          <p class="text-sm max-w-xs">
            Please pick a department to preview its data here.
          </p>
        </div>

        <Button :label="'Pick a department as a data preview'" @click="visibleDrawer = true" />
      </div>
    </div>
  </div>

  <Drawer v-model:visible="visibleDrawer" position="right" :pt="{ root: { style: 'width: 30vw' } }">
    <template #header>
      <div>
        <h2 class="text-base font-semibold">Department Overview</h2>
        <p class="text-xs text-gray-500">Choose a department to preview</p>
      </div>
    </template>

    <DepartementListTree
      :dataList="data.departments"
      @changeDepartment="onChangeDepartment"
      :active="layout?.data?.fieldValue?.departement?.slug"
    />
  </Drawer>
</template>

<style scoped>
.selected-bg {
  background-color: v-bind('layoutTheme?.app?.theme[0]') !important;
  color: v-bind('layoutTheme?.app?.theme[1]') !important;
}
</style>
