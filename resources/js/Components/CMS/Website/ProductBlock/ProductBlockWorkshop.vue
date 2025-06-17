<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle, faChevronLeft, faChevronRight, faDesktop } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, inject, toRaw, provide } from "vue"
import { getComponent } from "@/Composables/getWorkshopComponents"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { layoutStructure } from '@/Composables/useLayoutStructure';
import { router } from "@inertiajs/vue3";
import { routeType } from "@/types/route"
import SideMenuProductWorkshop from "./SideMenuProductBlockWorkshop.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import { notify } from "@kyvg/vue3-notification"

library.add(faCube, faLink, faStar, faCircle, faChevronLeft, faChevronRight, faDesktop)

const props = defineProps<{
  data: {
    web_block_types: any;
    autosaveRoute: routeType;
    layout: any;
    products: routeType;
    families: any;
  }
}>()
console.log(props)
const isModalOpen = ref(false);
const isLoadingSave = ref(false);
const layout = ref(props.data.layout);

const onPickTemplate = (template: any) => {
  isModalOpen.value = false;
  layout.value = template;
  autosave()
};


const autosave = () => {
  const payload = toRaw(layout.value);
  delete payload.data?.fieldValue?.layout;
  delete payload.data?.fieldValue?.sub_departments;

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
};

const currentView = ref("desktop");
provide("currentView", currentView);

console.log('ss',props)
</script>

<template>
  <div class="h-[85vh] grid grid-cols-12 gap-4 p-3">
    <div class="col-span-3 bg-white rounded-xl shadow-md p-4 overflow-y-auto border">
      <SideMenuProductWorkshop :data="layout" :webBlockTypes="data.web_block_types" @auto-save="autosave"
        @set-up-template="onPickTemplate"  />
    </div>

    <div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-hidden border">
      <div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b">
        <!-- Desktop View Preview -->
        <div class="flex items-center gap-2 py-1 px-2 cursor-pointer lg:flex hidden selected-bg"
          v-tooltip="'Desktop view'">
          <FontAwesomeIcon icon="fas fa-desktop" fixed-width aria-hidden="true" />

        </div>
      </div>

      <!--  {{  layout?.data?.fieldValue }} -->
      <div v-if="layout?.data?.fieldValue?.product" class="relative flex-1 overflow-auto">
        <component class="w-full" :is="getComponent(layout.code)" :modelValue="layout.data.fieldValue" />
      </div>

      <div v-else>
        <EmptyState />
      </div>
    </div>
  </div>

</template>
