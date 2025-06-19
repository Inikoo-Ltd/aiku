<script setup lang="ts">
import { faCube, faInfoCircle, faLink } from "@fal"
import { faStar, faCircle, faChevronLeft, faChevronRight, faDesktop } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, computed, provide, inject, toRaw, watch } from "vue"
import { getComponent } from "@/Composables/getWorkshopComponents"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { router } from "@inertiajs/vue3"
import { routeType } from "@/types/route"
import SideMenuFamiliesCollectionsWorkshop from "@/Components/CMS/Website/CollectionsWorkshop/SideMenuFamiliesCollectionsWorkshop.vue"
import { notify } from "@kyvg/vue3-notification"
import Drawer from 'primevue/drawer'
import WebpageList from "./WebpageList.vue"
import {debounce} from "lodash-es"
import ScreenView from "@/Components/ScreenView.vue";

library.add(faCube, faLink, faStar, faCircle, faChevronLeft, faChevronRight, faDesktop)

const props = defineProps<{
  data: {
    web_block_types: any;
    autosaveRoute: routeType;
    layout: any;
    departments: {
      data: Array<any>
    };
    subDepartments: {
      data: Array<any>
    };
    webpages?: any[];
    update_family_route?: any;
  }
}>()

const isModalOpen = ref(false)
const isLoadingSave = ref(false)
const visibleDrawer = ref(false)

const layout = computed(() => props.data.layout)

const autosave = () => {
  const payload = JSON.parse(JSON.stringify(toRaw(layout.value)))

  if (payload.data?.fieldValue) {
    delete payload.data.fieldValue.collections
  }

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
        })
      },
    }
  )
}

// Create debounced version of autosave
const autosaveDebounced = debounce(autosave, 1000) // delay 1000ms

const onPickTemplate = (template: any) => {
  isModalOpen.value = false
  layout.value.code = template.code
  layout.value.data = { fieldValue: {
    container : {
      properties : null
    }
  } }
  autosaveDebounced()
}

const pickedwebpage = ref({
  collections : []
})
const onChangeWebpage = (value: any) => {
  if (layout.value?.data?.fieldValue) {
    pickedwebpage.value.collections = value.collections || []
  }
}

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

</script>


<template>
  <div class="h-[85vh] grid grid-cols-12 gap-4 p-3">
    <div class="col-span-3 bg-white rounded-xl shadow-md p-4 overflow-auto border">
      <SideMenuFamiliesCollectionsWorkshop
        :data="props.data.layout"
        :webBlockTypes="props.data.web_block_types"
        @auto-save="autosave"
        @set-up-template="onPickTemplate"
        :dataList="props.data.webpages"
      />
    </div>

    <div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-auto border">
      <div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b">
        <div class="py-1 px-2 cursor-pointer lg:block hidden" v-tooltip="'Desktop view'">
            <ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" />
        </div>

        <div class="text-sm text-gray-600 italic mr-3 cursor-pointer" @click="visibleDrawer = true">
          <span v-if="layout?.data?.fieldValue?.webpage?.name">
            Preview: <strong>{{ layout.data.fieldValue.webpage?.name }}</strong>
          </span>
          <span v-else>Pick a Departement or sub-departement</span>
        </div>
      </div>

      <div v-if="props.data.layout?.code" :class="['border-2 border-t-0 overflow-auto ', iframeClass]">
        <component
          class="flex-1 overflow-auto active-block"
          :is="getComponent(layout.code)"
          :modelValue="{
            ...layout.data.fieldValue,
            collections : pickedwebpage.collections
            }"
          :screenType="currentView"
          :routeEditfamily="props.data.update_family_route"
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
        <h2 class="text-base font-semibold">Departement or sub-departement Overview</h2>
        <p class="text-xs text-gray-500">Choose a Departement or sub-departement to preview</p>
      </div>
    </template>

    <WebpageList
      :dataList="[...props.data?.departments?.data, ...props.data?.subDepartments?.data]"
      @onChangeWebpage="onChangeWebpage"
      :active="layout?.data?.fieldValue?.webpage?.slug"
    />
  </Drawer>
</template>

