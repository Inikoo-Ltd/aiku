<script setup lang="ts">
import { faCube, faLink } from "@fal";
import { faStar, faCircle, faChevronLeft, faChevronRight, faDesktop, faInfoCircle } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { ref, provide, inject, toRaw, watch } from "vue";
import SideMenuSubDepartmentWorkshop from "./SideMenuSubDepartmentWorkshop.vue";
import { getComponent } from "@/Composables/getWorkshopComponents";
import { router } from "@inertiajs/vue3";
import { notify } from "@kyvg/vue3-notification";
import { routeType } from "@/types/route";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { layoutStructure } from '@/Composables/useLayoutStructure';
import Drawer from 'primevue/drawer';
import DepartmentListTree from "./DepartmentListTree.vue";
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


const layoutState = ref(JSON.parse(JSON.stringify(props.data.layout)));


const layoutTheme = inject('layout', layoutStructure);
const isLoadingSave = ref(false);
const visibleDrawer = ref(false);

const currentView = ref("desktop");
provide("currentView", currentView);

const iframeClass = ref("w-full h-full");
watch(currentView, (view) => {
  switch (view) {
    case "mobile":
      iframeClass.value = "w-[375px] h-[667px] mx-auto";
      break;
    case "tablet":
      iframeClass.value = "w-[768px] h-[1024px] mx-auto";
      break;
    default:
      iframeClass.value = "w-full h-full";
  }
});


const createSnapshot = () => {
  const raw = toRaw(layoutState.value);
  const snapshot = JSON.parse(JSON.stringify(raw));

  if (snapshot.data?.fieldValue) {
    delete snapshot.data.fieldValue.department;
    delete snapshot.data.fieldValue.sub_departments;
  }

  return snapshot;
};

const autosave = () => {
  const payload = createSnapshot();
  console.log("AUTOSAVE SNAPSHOT:", payload);

  router.patch(
    route(props.data.autosaveRoute.name, props.data.autosaveRoute.parameters),
    { layout: payload },
    {
      onStart: () => { isLoadingSave.value = true },
      onFinish: () => { isLoadingSave.value = false },
      onError: (errors) => {
        notify({
          title: "Autosave Failed",
          text: errors?.message || "Unknown error occurred.",
          type: "error",
        });
      },
    }
  );
};


function debounce(fn: Function, delay = 800) {
  let timer: any;
  return (...args: any[]) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}
const debouncedAutosave = debounce(autosave);


const dataPicked = ref({
  department: null,
  sub_departments: []
});

const onChangeDepartment = (value: any) => {
  dataPicked.value.department = value;
  dataPicked.value.sub_departments = value?.sub_departments || [];

  if (layoutState.value.data?.fieldValue) {
    debouncedAutosave();
  }
};


const onPickTemplate = (template: any) => {
  layoutState.value = JSON.parse(JSON.stringify({
    ...template,
    data: {
      ...template.data,
      fieldValue: {
        container: { properties: null },
        ...(template.data?.fieldValue || {})
      }
    }
  }));

  autosave();
};

console.log("LAYOUT STATE:", layoutState);
</script>


<template>

  <div class="pt-4">
    <div class="mx-6 italic text-amber-700 bg-amber-200 py-1 px-2 border-l-4 border-amber-400 w-fit">
      *This block usually showed in Department page
    </div>

    <div class="h-[85vh] grid grid-cols-12 gap-4 p-3">
      <div class="col-span-3 bg-white rounded-xl shadow-md p-4 overflow-y-auto border">
        <SideMenuSubDepartmentWorkshop :data="layoutState" :webBlockTypes="props.data.web_block_types"
          :dataList="props.data.departments" @auto-save="debouncedAutosave" @set-up-template="onPickTemplate" />
      </div>
      <div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-auto border">
        <div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b">
          <div class="py-1 px-2 cursor-pointer lg:block hidden" v-tooltip="'Desktop view'">
            <ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" />
          </div>
          <div class="text-sm text-gray-600 italic mr-3 cursor-pointer" @click="visibleDrawer = true">
            <span v-if="layoutState.data.fieldValue.department?.name">
              Preview: <strong>{{ layoutState.data.fieldValue.department.name }}</strong>
            </span>
            <span v-else>Pick The department</span>
          </div>
        </div>
        <div v-if="props.data.layout?.code" :class="['border-2 border-t-0', iframeClass]">
          <component class="flex-1 overflow-auto active-block"
            :is="getComponent(props.data.layout.code, { shop_type: layout?.shopState?.type })" :screenType="currentView"
            :modelValue="{
              ...layoutState.data.fieldValue,
              department: dataPicked.department,
              sub_departments: dataPicked.sub_departments
            }" :routeEditSubDepartment="props.data.update_sub_department_route" />
        </div>
        <div v-else
          class="flex flex-col items-center justify-center gap-3 text-center text-gray-500 flex-1 min-h-[300px]"
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
  </div>

  <Drawer v-model:visible="visibleDrawer" position="right" :pt="{ root: { style: 'width: 30vw' } }">
    <template #header>
      <div>
        <h2 class="text-base font-semibold">Department Overview</h2>
        <p class="text-xs text-gray-500">Choose a department to preview</p>
      </div>
    </template>

    <DepartmentListTree :dataList="props.data.departments" @changeDepartment="onChangeDepartment"
      :active="props.data.layout?.data?.fieldValue?.department?.slug" />
  </Drawer>
</template>

<style scoped>
.selected-bg {
  background-color: v-bind('layoutTheme?.app?.theme[0]') !important;
  color: v-bind('layoutTheme?.app?.theme[1]') !important;
}
</style>
