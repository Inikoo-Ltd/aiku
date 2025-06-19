<script setup lang="ts">
import { faCube, faLink } from "@fal";
import { faStar, faCircle, faChevronLeft, faChevronRight, faDesktop, faInfoCircle } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { ref, provide, inject, toRaw, watch, computed } from "vue";
import SideMenuDepartementWorkshop from "./SideMenuSubDepartementWorkshop.vue";
import { getComponent } from "@/Composables/getWorkshopComponents";
import { router } from "@inertiajs/vue3";
import { notify } from "@kyvg/vue3-notification";
import { routeType } from "@/types/route";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { layoutStructure } from '@/Composables/useLayoutStructure';
import Drawer from 'primevue/drawer';
import DepartementListTree from "./DepartementListTree.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import ScreenView from "@/Components/ScreenView.vue";

library.add(faCube, faLink, faStar, faCircle, faChevronLeft, faChevronRight, faDesktop);

const props = defineProps<{
  data: {
    web_block_types: any;
    autosaveRoute: routeType;
    layout: any;
    departments: any[];
    update_sub_department_route: routeType;
  };
}>();

const layoutTheme = inject('layout', layoutStructure);
const isModalOpen = ref(false);
const isLoadingSave = ref(false);
const visibleDrawer = ref(false);

const currentView = ref("desktop");
provide("currentView", currentView);

const iframeClass = ref("w-full h-full");
watch(currentView, (newValue) => {
  iframeClass.value = setIframeView(newValue);
});

const setIframeView = (view: string) => {
  switch (view) {
    case "mobile": return "w-[375px] h-[667px] mx-auto";
    case "tablet": return "w-[768px] h-[1024px] mx-auto";
    default: return "w-full h-full";
  }
};

// =============== AUTOSAVE LOGIC ===============
const autosave = () => {
  const payload = JSON.parse(JSON.stringify(toRaw(props.data.layout)));

  if (payload.data?.fieldValue) {
    delete payload.data.fieldValue.departement;
    delete payload.data.fieldValue.sub_departments;
  }

  router.patch(
    route(props.data.autosaveRoute.name, props.data.autosaveRoute.parameters),
    { layout: payload },
    {
      onStart: () => { isLoadingSave.value = true },
      onFinish: () => { isLoadingSave.value = false },
      onSuccess: () => {},
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

// Manual debounce (tanpa eksternal lib)
function debounce(fn: Function, delay = 800) {
  let timer: any;
  return (...args: any[]) => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

const debouncedAutosave = debounce(autosave);

const dataPicked = ref({
  departement : null,
  sub_departments : []
})
// =============== EVENT HANDLERS ===============
const onChangeDepartment = (value: any) => {
  if (props.data.layout?.data?.fieldValue) {
    dataPicked.value.departement = value;
    dataPicked.value.sub_departments = value.sub_departments || [];
    debouncedAutosave();
  }
};

const onPickTemplate = (template: any) => {
  isModalOpen.value = false
  props.data.layout = {
    ...template,
    data: {
      ...template.data,
      fieldValue: {
        container : {
          properties : null
        }
      }
    }
  }
  autosave()
};


</script>

<template>
  <div class="h-[85vh] grid grid-cols-12 gap-4 p-3">
    <div class="col-span-3 bg-white rounded-xl shadow-md p-4 overflow-y-auto border">
      <SideMenuDepartementWorkshop :data="props.data.layout" :webBlockTypes="props.data.web_block_types"
        :dataList="props.data.departments" @auto-save="debouncedAutosave" @set-up-template="onPickTemplate" />
    </div>

    <div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-auto border">
      <div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b">
        <div class="py-1 px-2 cursor-pointer lg:block hidden" v-tooltip="'Desktop view'">
          <ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" />
        </div>

        <div class="text-sm text-gray-600 italic mr-3 cursor-pointer" @click="visibleDrawer = true">
          <span v-if="props.data.layout?.data?.fieldValue?.departement?.name">
            Preview: <strong>{{ props.data.layout.data.fieldValue.departement?.name }}</strong>
          </span>
          <span v-else>Pick The department</span>
        </div>
      </div>

      <div v-if="props.data.layout?.code" :class="['border-2 border-t-0', iframeClass]">
        <component class="flex-1 overflow-auto active-block" :is="getComponent(props.data.layout.code)"
          :screenType="currentView" 
          :modelValue="{
            ...props.data.layout.data.fieldValue,
            departement: dataPicked.departement || null,
            sub_departments: dataPicked.sub_departments || []
          }"
          :routeEditSubDepartement="props.data.update_sub_department_route" />
      </div>

      <div v-else class="flex flex-col items-center justify-center gap-3 text-center text-gray-500 flex-1 min-h-[300px]"
        style="height: 100%;">
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

    <DepartementListTree :dataList="props.data.departments" @changeDepartment="onChangeDepartment"
      :active="props.data.layout?.data?.fieldValue?.departement?.slug" />
  </Drawer>
</template>

<style scoped>
.selected-bg {
  background-color: v-bind('layoutTheme?.app?.theme[0]') !important;
  color: v-bind('layoutTheme?.app?.theme[1]') !important;
}
</style>
