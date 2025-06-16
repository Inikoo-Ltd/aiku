<script setup lang="ts">
import { faCube, faInfoCircle, faLink } from "@fal"
import { faStar, faCircle, faChevronLeft, faChevronRight, faDesktop } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, watch, computed, provide, inject, toRaw } from "vue"
import { getComponent } from "@/Composables/getWorkshopComponents"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { layoutStructure } from '@/Composables/useLayoutStructure';
import { router } from "@inertiajs/vue3";
import { routeType } from "@/types/route"
import SideMenuFamiliesCollectionsWorkshop from "@/Components/CMS/Website/CollectionsWorkshop/SideMenuFamiliesCollectionsWorkshop.vue"
import { notify } from "@kyvg/vue3-notification"
import Drawer from 'primevue/drawer';
import WebpageList from "./WebpageList.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"

library.add(faCube, faLink, faStar, faCircle, faChevronLeft, faChevronRight, faDesktop)

const props = defineProps<{
  data: {
    web_block_types: any;
    autosaveRoute: routeType;
    layout: any;
    webpages: any[]; // renamed from sub_departements
    update_family_route: routeType;
  }
}>()

const layoutTheme = inject('layout', layoutStructure)
const isModalOpen = ref(false);
const isLoadingSave = ref(false);
const visibleDrawer = ref(false);
console.log('layoutTheme', props.data);
// Make layout editable
const layout = ref(props.data.layout);

const onPickTemplate = (template: any) => {
  isModalOpen.value = false;
  layout.value = template;
  layout.value.data.fieldValue = {}
  autosave()
};

const onChangeWebpage = (value: any) => {
  if (layout.value?.data?.fieldValue) {
    layout.value.data.fieldValue.webpage = value.webpage;
    layout.value.data.fieldValue.collections = value.collections || [];
  }
};

const autosave = () => {
  const payload = JSON.parse(JSON.stringify(toRaw(layout.value)));

  if (payload.data?.fieldValue) {
    delete payload.data.fieldValue.collections;
    delete payload.data.fieldValue.webpage;
  }

  router.patch(
    route(props.data.autosaveRoute.name, props.data.autosaveRoute.parameters),
    { layout: payload },
    {
      onStart: () => isLoadingSave.value = true,
      onFinish: () => isLoadingSave.value = false,
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
      <SideMenuFamiliesCollectionsWorkshop
        :data="layout"
        :webBlockTypes="data.web_block_types"
        @auto-save="autosave"
        @set-up-template="onPickTemplate"
        :dataList="data.webpages"
        @onChangeDepartment="onChangeWebpage"
      />
    </div>

    <div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-auto border">
      <div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b">
        <div class="py-1 px-2 cursor-pointer lg:block hidden selected-bg" v-tooltip="'Desktop view'">
          <FontAwesomeIcon icon="fas fa-desktop" fixed-width aria-hidden="true" />
        </div>

        <div class="text-sm text-gray-600 italic mr-3 cursor-pointer" @click="visibleDrawer = true">
          <span v-if="layout?.data?.fieldValue?.webpage?.name">
            Preview: <strong>{{ layout.data.fieldValue.webpage?.name }}</strong>
          </span>
          <span v-else>Pick a Departement or sub-departement</span>
        </div>
      </div>

      <div v-if="layout?.code" class="relative flex-1 overflow-auto">
        <component
          class="w-full relative flex-1 overflow-auto border-4 border-[#4F46E5] active-block"
          :is="getComponent(layout.code)"
          :modelValue="{
            ...layout.data.fieldValue,
            webpage: layout.data.fieldValue?.webpage || null,
            families: layout.data.fieldValue?.families || []
          }"
          :routeEditfamily="data.update_family_route"
        />
      </div>

      <div v-else class="flex flex-col items-center justify-center gap-3 text-center text-gray-500 flex-1 min-h-[300px]">
        <div class="flex flex-col items-center gap-2">
          <FontAwesomeIcon :icon="faInfoCircle" class="text-4xl" />
          <h3 class="text-lg font-semibold">No webpage selected</h3>
          <p class="text-sm max-w-xs">
            Please pick a Departement or sub-departement to preview its data here.
          </p>
        </div>
        <Button :label="'Pick a webpage to preview'" @click="visibleDrawer = true" />
      </div>
    </div>
  </div>

  <Drawer v-model:visible="visibleDrawer" position="right" :pt="{ root: { style: 'width: 30vw' } }">
    <template #header>
      <div>
        <h2 class="text-base font-semibold">Departement or sub-departement  Overview</h2>
        <p class="text-xs text-gray-500">Choose a Departement or sub-departement  to preview</p>
      </div>
    </template>

    <!-- You can uncomment this if using WebpageList -->
    <EmptyState />
    <!-- <WebpageList 
      :dataList="data.webpages"
      @changeDepartment="onChangeWebpage"
      :active="layout?.data?.fieldValue?.webpage?.slug"
    />  -->
   
  </Drawer>
</template>
