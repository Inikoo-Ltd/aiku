<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle, faChevronLeft, faChevronRight, faDesktop } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, watch, computed, provide, inject, toRaw } from "vue"
import Modal from '@/Components/Utils/Modal.vue'
import BlockList from '@/Components/CMS/Webpage/BlockList.vue'
import { getIrisComponent } from "@/Composables/getIrisComponents"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue"
import { getBlueprint } from "@/Composables/getBlueprintWorkshop"
import { layoutStructure } from '@/Composables/useLayoutStructure';
import { router } from "@inertiajs/vue3";
import { routeType } from "@/types/route"
import SideMenuSubDepartementWorkshop from "./SideMenuSubDepartementWorkshop.vue"
import { notify } from "@kyvg/vue3-notification"

library.add(faCube, faLink, faStar, faCircle, faChevronLeft, faChevronRight, faDesktop)

const props = defineProps<{
  data: {
    web_block_types: any;
    autosaveRoute: routeType;
    layout: any;
    sub_departements: any[];
  }
}>()

console.log(props.data)
const layoutTheme = inject('layout', layoutStructure)
const isModalOpen = ref(false);
const isLoadingSave = ref(false);

// Make layout editable
const layout = ref(props.data.layout);

const onPickTemplate = (template: any) => {
  isModalOpen.value = false;
  layout.value = template;
  layout.value.data.fieldValue = {}
  autosave()
};

const onChangeDepartment = (value: any) => {
  const newDepartment = {...value};
  delete newDepartment.families


  if (!layout.value.data.fieldValue || Array.isArray(layout.value.data.fieldValue)) {
    layout.value.data.fieldValue = {}
  }

  if (layout.value?.data?.fieldValue) {
    layout.value.data.fieldValue.sub_departements = value;
    layout.value.data.fieldValue.families = value.families || [];
  }
};



const autosave = () => {
  const payload = toRaw(layout.value);
  // Hapus properti jika ada
  delete payload.data?.fieldValue?.families
  delete payload.data?.fieldValue?.sub_departements


  router.patch(
    route(props.data.autosaveRoute.name, props.data.autosaveRoute.parameters),
    { layout: payload },
    {
      onStart: () => {
        isLoadingSave.value = true
      },
      onFinish: () => {
        isLoadingSave.value = false
      },
      onSuccess: () => {
        props.data.layout = payload;
        notify({
          title: 'Autosave Successful',
          text: 'Your changes have been saved.',
          type: 'success',
        })
      },
      onError: (errors) => {
        notify({
          title: 'Autosave Failed',
          text: errors?.message || 'Unknown error occurred.',
          type: 'error',
        })
      }
    }
  )
}

console.log('props.data.layout', props)


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
        <!--  <ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" /> -->
        <div class="py-1 px-2 cursor-pointer lg:block hidden" :class="['selected-bg']" v-tooltip="'Desktop view'">
          <FontAwesomeIcon icon='fas fa-desktop' class='' fixed-width aria-hidden='true' />
        </div>
      </div>
      <div v-if="layout?.code" class="relative flex-1 overflow-auto">
        <component class="w-full" :is="getIrisComponent(layout.code)" :fieldValue="layout.data.fieldValue" />
      </div>
      <div v-else>
        <EmptyState />
      </div>
    </div>
  </div>
</template>


<style scoped>
.selected-bg {
  background-color: v-bind('layoutTheme?.app?.theme[0]') !important;
  color: v-bind('layoutTheme?.app?.theme[1]') !important;
}
</style>
