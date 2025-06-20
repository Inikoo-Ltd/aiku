<script setup lang="ts">
import { faCube, faInfoCircle, faLink } from "@fal"
import { faStar, faCircle, faChevronLeft, faChevronRight, faDesktop } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, watch, provide, inject, toRaw } from "vue"
import { getComponent } from "@/Composables/getWorkshopComponents"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { router } from "@inertiajs/vue3"
import { routeType } from "@/types/route"
import SideMenuSubDepartementWorkshop from "./SideMenuFamiliesBlockWorkshop.vue"
import { notify } from "@kyvg/vue3-notification"
import Drawer from 'primevue/drawer'
import SubDepartementListTree from "./SubDepartementListTree.vue"
import ScreenView from "@/Components/ScreenView.vue"

library.add(faCube, faLink, faStar, faCircle, faChevronLeft, faChevronRight, faDesktop)

const props = defineProps<{
  data: {
    web_block_types: any;
    autosaveRoute: routeType;
    layout: any;
    sub_departements: any[];
    update_family_route: routeType;
  }
}>()

const layoutTheme = inject('layout', layoutStructure)
const isModalOpen = ref(false)
const isLoadingSave = ref(false)
const visibleDrawer = ref(false)

const currentView = ref("desktop")
provide("currentView", currentView)

const iframeClass = ref("w-full h-full")

watch(currentView, (newVal) => {
  iframeClass.value = setIframeView(newVal)
})

const setIframeView = (view: string) => {
  switch (view) {
    case "mobile":
      return "w-[375px] h-[667px] mx-auto"
    case "tablet":
      return "w-[768px] h-[1024px] mx-auto"
    default:
      return "w-full h-full"
  }
}

// === Handle pick template ===
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
}

const dataPicked = ref({
  sub_departement : null,
  families : []
})
// === Handle change sub-departement ===
const onChangeDepartment = (value: any) => {
    dataPicked.value.sub_departement = value.sub_departement
    dataPicked.value.families = value.families || []
}

// === Autosave logic ===
const autosave = () => {
  const payload = JSON.parse(JSON.stringify(toRaw(props.data.layout)))

  if (payload.data?.fieldValue) {
    delete payload.data.fieldValue.families
    delete payload.data.fieldValue.sub_departement
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
        })
      }
    }
  )
}

// === Debounce helper ===
function debounce(fn: Function, delay = 800) {
  let timer: any
  return (...args: any[]) => {
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => fn(...args), delay)
  }
}
const debouncedAutosave = debounce(autosave)
</script>

<template>
  <div class="h-[85vh] grid grid-cols-12 gap-4 p-3">
    <div class="col-span-3 bg-white rounded-xl shadow-md p-4 overflow-auto border">
      <SideMenuSubDepartementWorkshop
        :data="data.layout"
        :webBlockTypes="data.web_block_types"
        @auto-save="debouncedAutosave"
        @set-up-template="onPickTemplate"
        :dataList="data.sub_departements"
        @onChangeDepartment="onChangeDepartment"
      />
    </div>

    <div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-auto border">
      <div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b">
        <div class="py-1 px-2 cursor-pointer lg:block hidden" v-tooltip="'Desktop view'">
          <ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" />
        </div>

        <div class="text-sm text-gray-600 italic mr-3 cursor-pointer" @click="visibleDrawer = true">
          <span v-if="data.layout?.data?.fieldValue?.sub_departement?.name">
            Preview: <strong>{{ data.layout.data.fieldValue.sub_departement?.name }}</strong>
          </span>
          <span v-else>Pick the sub-departement</span>
        </div>
      </div>

      <div v-if="data.layout?.code" :class="['border-2 border-t-0 overflow-auto', iframeClass]">
        <component
          class="flex-1 active-block"
          :is="getComponent(data.layout.code)"
          :screenType="currentView"
          :modelValue="{
            ...data.layout.data.fieldValue,
            sub_departement: dataPicked.sub_departement,
            families: dataPicked.families
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
      :active="data.layout?.data?.fieldValue?.sub_departement?.slug"
    />
  </Drawer>
</template>

<style scoped>
.selected-bg {
  background-color: v-bind('layoutTheme?.app?.theme[0]') !important;
  color: v-bind('layoutTheme?.app?.theme[1]') !important;
}
</style>
