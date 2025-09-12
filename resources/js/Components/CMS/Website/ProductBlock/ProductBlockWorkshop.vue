<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle, faChevronLeft, faChevronRight, faDesktop } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, inject, toRaw, provide, watch } from "vue"
import { getComponent } from "@/Composables/getWorkshopComponents"
import { router } from "@inertiajs/vue3";
import { routeType } from "@/types/route"
import SideMenuProductWorkshop from "./SideMenuProductBlockWorkshop.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import { notify } from "@kyvg/vue3-notification"
import debounce from "lodash/debounce"
import ScreenView from "@/Components/ScreenView.vue";

library.add(faCube, faLink, faStar, faCircle, faChevronLeft, faChevronRight, faDesktop)

const props = defineProps<{
  data: {
    web_block_types: any;
    autosaveRoute: routeType;
    layout: any;
    products: routeType;
    families: any;
  }
  currency: {
    code: string
    name: string
  }
}>()

const reload = inject('reload') as () => void
const isModalOpen = ref(false)
const isLoadingSave = ref(false)

const currentView = ref("desktop");
provide("currentView", currentView);


const autosave = () => {
  const payload = toRaw(props.data.layout)
  delete payload.data?.fieldValue?.product

  router.patch(
    route(props.data.autosaveRoute.name, props.data.autosaveRoute.parameters),
    { layout: payload },
    {
      onStart: () => isLoadingSave.value = true,
      onFinish: () => {
        isLoadingSave.value = false
        reload?.()
      },
      onSuccess: () => {
        // notify success here if needed
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

// Debounced autosave (500ms)
const debouncedAutosave = debounce(autosave, 500)

const onPickTemplate = (template: any) => {
  isModalOpen.value = false
  props.data.layout = template
  debouncedAutosave()
}

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
    <div class="col-span-3 bg-white rounded-xl shadow-md py-4 overflow-y-auto border">
      <SideMenuProductWorkshop :data="props.data.layout" :webBlockTypes="props.data.web_block_types"
        @auto-save="autosave" @set-up-template="onPickTemplate" />
    </div>

    <div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-hidden border">
      <div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b">
        <div class="flex items-center gap-2 py-1 px-2 cursor-pointer lg:flex hidden selected-bg"
          v-tooltip="'Desktop view'">
          <div class="py-1 px-2 cursor-pointer lg:block hidden selected-bg" v-tooltip="'Desktop view'">
            <ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" />
          </div>
        </div>
      </div>

      <div v-if="props.data.layout?.data?.fieldValue?.product" class="relative flex-1 overflow-auto" :class="['border-2 border-t-0 overflow-auto ', iframeClass]">
        <component class="w-full" :is="getComponent(props.data.layout.code)" :screenType="currentView"
          :modelValue="props.data.layout.data.fieldValue" :templateEdit="'template'" :currency />
      </div>

      <div v-else>
        <EmptyState />
      </div>
    </div>
  </div>
</template>