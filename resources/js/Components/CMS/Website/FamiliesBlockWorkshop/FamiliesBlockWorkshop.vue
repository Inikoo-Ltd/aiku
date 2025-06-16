<script setup lang="ts">
import { faCube, faInfoCircle, faLink } from "@fal"
import { faStar, faCircle, faChevronLeft, faChevronRight, faDesktop } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, watch, computed, provide, inject, toRaw } from "vue"
import Modal from '@/Components/Utils/Modal.vue'
import BlockList from '@/Components/CMS/Webpage/BlockList.vue'
import { getComponent } from "@/Composables/getWorkshopComponents"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue"
import { getBlueprint } from "@/Composables/getBlueprintWorkshop"
import { layoutStructure } from '@/Composables/useLayoutStructure';
import { router } from "@inertiajs/vue3";
import { routeType } from "@/types/route"
import SideMenuSubDepartementWorkshop from "./SideMenuFamiliesBlockWorkshop.vue"
import { notify } from "@kyvg/vue3-notification"
import Drawer from 'primevue/drawer';
import SubDepartementListTree from "./SubDepartementListTree.vue"

library.add(faCube, faLink, faStar, faCircle, faChevronLeft, faChevronRight, faDesktop)

const props = defineProps<{
  data: {
    web_block_types: any;
    autosaveRoute: routeType;
    layout: any;
    sub_departements: any[];
    update_family_route : routeType;
  }
}>()

const layoutTheme = inject('layout', layoutStructure)
const isModalOpen = ref(false);
const isLoadingSave = ref(false);
const visibleDrawer = ref(false);
// Make layout editable
const layout = ref(props.data.layout);

const onPickTemplate = (template: any) => {
  isModalOpen.value = false;
  layout.value = template;
  layout.value.data.fieldValue = {}
  autosave()
};

const onChangeDepartment = (value: any) => {
  if (layout.value?.data?.fieldValue) {
    layout.value.data.fieldValue.sub_departement = value.sub_departement;
    layout.value.data.fieldValue.families = value.families || [];
  }
};



const autosave = () => {
  // Deep clone to safely modify payload without touching reactive data
  const payload = JSON.parse(JSON.stringify(toRaw(layout.value)));

  // Remove departement & sub_departments before sending to backend
  if (payload.data?.fieldValue) {
    delete payload.data.fieldValue.families;
    delete payload.data.fieldValue.sub_departement;
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




const currentView = ref("desktop");
provide("currentView", currentView);
</script>

<template>
  <div class="h-[85vh] grid grid-cols-12 gap-4 p-3">
    <div class="col-span-3 bg-white rounded-xl shadow-md p-4 overflow-auto border">
      <SideMenuSubDepartementWorkshop :data="layout" :webBlockTypes="data.web_block_types" @auto-save="autosave"
        @set-up-template="onPickTemplate" :dataList="data.sub_departements" @onChangeDepartment="onChangeDepartment"/>
    </div>

    <div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-auto border">
     <div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b">
        <!-- Left: Desktop View Icon -->
        <div class="py-1 px-2 cursor-pointer lg:block hidden selected-bg" v-tooltip="'Desktop view'">
          <FontAwesomeIcon icon="fas fa-desktop" fixed-width aria-hidden="true" />
        </div>

        <!-- Right: Preview Label -->
        <div class="text-sm text-gray-600 italic mr-3 cursor-pointer" @click="visibleDrawer = true">
          <span v-if="layout?.data?.fieldValue?.sub_departement?.name">
            Preview: <strong>{{ layout.data.fieldValue.sub_departement?.name }}</strong>
          </span>
          <span v-else>Pick The sub-departement</span>
        </div>
      </div>
      <div v-if="layout?.code" class="relative flex-1 overflow-auto">
        <component
          class="w-full relative flex-1 overflow-auto border-4 border-[#4F46E5] active-block"
          :is="getComponent(layout.code)"
          :modelValue="{
            ...layout.data.fieldValue,
            sub_departement: layout.data.fieldValue?.departement || null,
            families: layout.data.fieldValue?.families || []
          }"
          :routeEditfamily="data.update_family_route"
        />
      </div>
      <div v-else class="flex flex-col items-center justify-center gap-3 text-center text-gray-500 flex-1 min-h-[300px]" style="height: 100%;">
        <div class="flex flex-col items-center gap-2">
          <FontAwesomeIcon :icon="faInfoCircle" class="text-4xl" />
          <h3 class="text-lg font-semibold">No sub-department selected</h3>
          <p class="text-sm max-w-xs">
            Please pick a sub-department to preview its data here.
          </p>
        </div>

        <Button :label="'Pick a sub-department as a data preview'" @click="visibleDrawer = true" />
      </div>
    </div>
  </div>

  <Drawer v-model:visible="visibleDrawer" position="right" :pt="{ root: { style: 'width: 30vw' } }">
    <template #header>
      <div>
        <h2 class="text-base font-semibold">Sub-Department Overview</h2>
        <p class="text-xs text-gray-500">Choose a Sub-department to preview</p>
      </div>
    </template>

    <SubDepartementListTree 
      :dataList="data.sub_departements"
      @changeDepartment="onChangeDepartment"
      :active="layout?.data?.fieldValue?.sub_departement?.slug"
     />
  </Drawer>
</template>


<style scoped>
.selected-bg {
  background-color: v-bind('layoutTheme?.app?.theme[0]') !important;
  color: v-bind('layoutTheme?.app?.theme[1]') !important;
}
</style>
