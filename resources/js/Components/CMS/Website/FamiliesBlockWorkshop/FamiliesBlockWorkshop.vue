<script setup lang="ts">
import { ref, provide, inject, computed, toRaw, onMounted } from "vue"
import { router } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
import Drawer from "primevue/drawer"

import {
  faCube,
  faInfoCircle,
  faLink
} from "@fal"
import {
  faStar,
  faCircle,
  faChevronLeft,
  faChevronRight,
  faDesktop
} from "@fas"

import { getComponent } from "@/Composables/getWorkshopComponents"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { setColorStyleRootByEl } from "@/Composables/useApp"
import { trans } from "laravel-vue-i18n"

import SideMenuSubDepartmentWorkshop from "./SideMenuFamiliesBlockWorkshop.vue"
import SubDepartmentListTree from "./SubDepartmentListTree.vue"
import ScreenView from "@/Components/ScreenView.vue"

import type { routeType } from "@/types/route"

library.add(
  faCube,
  faLink,
  faStar,
  faCircle,
  faChevronLeft,
  faChevronRight,
  faDesktop
)


const props = defineProps<{
  data: {
    web_block_types: any
    autosaveRoute: routeType
    layout: any
    sub_departments: any[]
    update_family_route: routeType
  }
  layout_theme: Array<any>
}>()



const layoutTheme = inject("layout", layoutStructure)

const rootRef = ref<HTMLElement | null>(null)

const visibleDrawer = ref(false)
const isLoadingSave = ref(false)
const currentView = ref<"desktop" | "tablet" | "mobile">("desktop")

provide("visibleDrawer", visibleDrawer)
provide("currentView", currentView)

const dataPicked = ref<{
  sub_department: any | null
  families: any[]
}>({
  sub_department: null,
  families: []
})


const iframeClass = computed(() => {
  switch (currentView.value) {
    case "mobile":
      return "w-[375px] h-[667px] mx-auto"
    case "tablet":
      return "w-[768px] h-[1024px] mx-auto"
    default:
      return "w-full h-full"
  }
})


const onPickTemplate = (template: any) => {
  props.data.layout = {
    ...template,
    data: {
      ...template.data,
      fieldValue: {
        container: {
          properties: null
        }
      }
    }
  }

  autosave()
}

const onChangeDepartment = (payload: any) => {
  dataPicked.value = {
    sub_department: payload.sub_department,
    families: payload.families || []
  }
}


const autosave = () => {
  const payload = structuredClone(toRaw(props.data.layout))

  if (payload?.data?.fieldValue) {
    delete payload.data.fieldValue.families
    delete payload.data.fieldValue.sub_department
  }

  router.patch(
    route(
      props.data.autosaveRoute.name,
      props.data.autosaveRoute.parameters
    ),
    { layout: payload },
    {
      onStart: () => (isLoadingSave.value = true),
      onFinish: () => (isLoadingSave.value = false),
      onError: (errors) => {
        notify({
          title: "Autosave Failed",
          text: errors?.message ?? "Unknown error occurred",
          type: "error"
        })
      }
    }
  )
}


let autosaveTimer: number | null = null
const debouncedAutosave = () => {
  if (autosaveTimer) clearTimeout(autosaveTimer)
  autosaveTimer = window.setTimeout(autosave, 800)
}



onMounted(() => {
  if (rootRef.value && props.layout_theme?.color) {
    setColorStyleRootByEl(rootRef.value, props.layout_theme.color)
  }
})
</script>


<template>
  <div class="pt-4">
    <div class="mx-6 italic text-amber-700 bg-amber-200 py-1 px-2 border-l-4 border-amber-400 w-fit">
      *This block usually showed in Sub Department page
    </div>

    <div class="h-[85vh] grid grid-cols-12 gap-4 p-3">
      <div class="col-span-3 bg-white rounded-xl shadow-md p-4 overflow-auto border">
        <SideMenuSubDepartmentWorkshop
          :data="data.layout"
          :webBlockTypes="data.web_block_types"
          @auto-save="debouncedAutosave"
          @set-up-template="onPickTemplate"
          :dataList="data.sub_departments"
          @onChangeDepartment="onChangeDepartment"
        />
      </div>
      
      <div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-auto border">
        <!-- Header: screen preview -->
        <div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b">
          <div class="py-1 px-2 cursor-pointer lg:block hidden" v-tooltip="'Desktop view'">
            <ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" />
          </div>
          <div @click="visibleDrawer = true" class="text-sm text-gray-600 italic mr-3 cursor-pointer underline">
            <span v-if="dataPicked.sub_department?.name" xv-if="data.layout?.data?.fieldValue?.sub_department?.name">
                Preview: <strong>{{ dataPicked.sub_department?.name }}</strong>
            </span>
            <span v-else class="">{{ trans("Pick the sub-department") }}</span>
          </div>
        </div>

        <div v-if="data.layout?.code" ref="rootRef" :class="['border-2 border-t-0 overflow-auto', iframeClass]">
          <component
            class="flex-1 active-block"
            :is="getComponent(data.layout.code, { shop_type: layoutTheme?.shopState?.type })"
            :screenType="currentView"
            :modelValue="{
              ...data.layout.data.fieldValue,
              sub_department: dataPicked.sub_department,
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
  </div>

  <Drawer v-model:visible="visibleDrawer" position="right" :pt="{ root: { style: 'width: 30vw' } }">
    <template #header>
      <div>
        <h2 class="text-base font-semibold">Sub-Department Overview</h2>
        <p class="text-xs text-gray-500">Choose a Sub-department to preview</p>
      </div>
    </template>

    <SubDepartmentListTree
      :dataList="data.sub_departments"
      @changeDepartment="onChangeDepartment"
      :active="data.layout?.data?.fieldValue?.sub_department?.slug"
    />
  </Drawer>
</template>

<style scoped>
</style>
