<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle, faChevronLeft, faChevronRight, faDesktop } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, provide, inject, toRaw, watch } from "vue"
import { getComponent } from "@/Composables/getWorkshopComponents"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { layoutStructure } from '@/Composables/useLayoutStructure';
import { router } from "@inertiajs/vue3";
import { routeType } from "@/types/route"
import SideMenuFamilyWorkshop from "./SideMenuProductsWorkshop.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import { notify } from "@kyvg/vue3-notification"
import {debounce} from "lodash-es"
import ScreenView from "@/Components/ScreenView.vue";

library.add(faCube, faLink, faStar, faCircle, faChevronLeft, faChevronRight, faDesktop)

const props = defineProps<{
  data: {
    web_block_types: any;
    autosaveRoute: routeType;
    layout: any;
    families: any[];
  }
}>()

const layoutTheme = inject('layout', layoutStructure)
const isModalOpen = ref(false);
const isLoadingSave = ref(false);

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

// Fungsi utama autosave
const doAutosave = () => {
  const payload = toRaw(props.data.layout);
  delete payload.data?.fieldValue?.layout;
  delete payload.data?.fieldValue?.sub_departments;


  router.patch(
    route(props.data.autosaveRoute.name, props.data.autosaveRoute.parameters),
    { layout: payload },
    {
      onStart: () => isLoadingSave.value = true,
      onFinish: () => isLoadingSave.value = false,
      onSuccess: () => {},
      onError: (errors) => {
        notify({
          title: 'Autosave Failed',
          text: errors?.message || 'Unknown error occurred.',
          type: 'error',
        });
      }
    }
  )
};

const autosave = debounce(doAutosave, 800);

// Event: Ganti Template
const onPickTemplate = (template: any) => {
  isModalOpen.value = false;
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
  autosave();
};

// Event: Ganti Department
const onChangeDepartment = (value: any) => {
  const newDepartment = { ...value };
  delete newDepartment.sub_departments;

  if (props.data.layout?.data?.fieldValue) {
    props.data.layout.data.fieldValue.layout = value;
    props.data.layout.data.fieldValue.sub_departments = value.sub_departments || [];
  }

  autosave();
};
</script>



<template>
  <div class="h-[85vh] grid grid-cols-12 gap-4 p-3">
    <div class="col-span-3 bg-white rounded-xl shadow-md p-4 overflow-y-auto border">
      <SideMenuFamilyWorkshop
        :data="data.layout"
        :webBlockTypes="data.web_block_types"
        :dataList="data.families"
        @auto-save="autosave"
        @set-up-template="onPickTemplate"
        @onChangeDepartment="onChangeDepartment"
      />
    </div>

    <div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-hidden border">
      <div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b">
        <div class="py-1 px-2 cursor-pointer lg:block hidden selected-bg" v-tooltip="'Desktop view'">
           <ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" />
        </div>
      </div>

      <div v-if="props.data.layout?.code" :class="['border-2 border-t-0 overflow-auto ', iframeClass]">
        <component :screenType="currentView" class="flex-1 overflow-auto active-block" :is="getComponent(data.layout.code)" :modelValue="data.layout.data.fieldValue" />
      </div>
      <div v-else>
        <EmptyState />
      </div>
    </div>
  </div>
</template>
