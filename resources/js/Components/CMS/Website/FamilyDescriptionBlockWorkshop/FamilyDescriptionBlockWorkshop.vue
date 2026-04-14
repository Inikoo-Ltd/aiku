<script setup lang="ts">
import { ref, provide, inject, computed, toRaw, onMounted } from "vue"
import { router } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
import Drawer from "primevue/drawer"
import axios from "axios"

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

import SideMenuFamilyDescriptionBlockWorkshop from "@/Components/CMS/Website/FamilyDescriptionBlockWorkshop/SideMenuFamilyDescriptionBlockWorkshop.vue"
import ScreenView from "@/Components/ScreenView.vue"
import FamilyList from "@/Components/CMS/Website/FamilyDescriptionBlockWorkshop/FamilyList.vue"

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
    family: any[]
    update_family_route: routeType
  }
  layout_theme: Array<any>
}>()

// STATE
const layoutState = ref(toRaw(props.data.layout))
const layoutTheme = inject("layout", layoutStructure)

const rootRef = ref<HTMLElement | null>(null)
const visibleDrawer = ref(false)
const isLoadingSave = ref(false)
const loadingTemplate = ref(false)
const previewKey = ref<string | number>("")
const currentView = ref<"desktop" | "tablet" | "mobile">("desktop")

provide("visibleDrawer", visibleDrawer)
provide("currentView", currentView)

// ✅ FIXED: keep stable structure
const dataPicked = ref<{
  sub_department: any | null
  families: any[]
  family: any | null
}>({
  sub_department: null,
  families: [],
  family: null
})

// VIEW SIZE
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

// FETCH TEMPLATE
const onPickTemplate = async (template: any) => {
  loadingTemplate.value = true

  try {
    const response = await axios.get(
      route("grp.json.workshop.fetch_descriptions_layout", {
        website: route().params["website"],
        webBlockType: template.code
      })
    )

    if (response.data) {
      layoutState.value = response.data
    }
  } catch (error) {
    console.error("Failed to fetch template", error)
  } finally {
    loadingTemplate.value = false
  }
}

// ✅ FIXED: do NOT replace object
const onChangeFamily = (payload: any) => {
  dataPicked.value.family = payload

  // 🔥 force re-render using family code (or id as fallback)
  previewKey.value = payload?.code || payload?.id || Date.now()

  visibleDrawer.value = false
}
// AUTOSAVE
const autosave = () => {
  const payload = toRaw(layoutState.value)

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

// MOUNT
onMounted(() => {
  if (rootRef.value && props.layout_theme?.color) {
    setColorStyleRootByEl(rootRef.value, props.layout_theme.color)
  }
})
</script>

<template>
  <div class="pt-4">
    <div class="mx-6 italic text-amber-700 bg-amber-200 py-1 px-2 border-l-4 border-amber-400 w-fit">
      {{ trans("*This block usually showed in Family page") }}
    </div>

    <div class="h-[85vh] grid grid-cols-12 gap-4 p-3">

      <!-- LEFT MENU -->
      <div class="col-span-3 bg-white rounded-xl shadow-md p-4 overflow-y-auto border">
        <SideMenuFamilyDescriptionBlockWorkshop :data="layoutState" :webBlockTypes="props.data.web_block_types"
          @set-up-template="onPickTemplate" @auto-save="debouncedAutosave" />
      </div>

      <!-- PREVIEW -->
      <div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-auto border">

        <div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b">
          <div class="py-1 px-2 hidden lg:block">
            <ScreenView v-model="currentView" />
          </div>

          <div class="text-sm text-gray-600 italic cursor-pointer" @click="visibleDrawer = true">
            <span v-if="dataPicked.family?.name">
              Preview: <strong>{{ dataPicked.family.name }}</strong>
            </span>
            <span v-else>Pick The Family</span>
          </div>
        </div>

        <!-- ✅ KEY HERE FOR FULL RE-RENDER -->
        <div v-if="layoutState && dataPicked.family" :key="previewKey" ref="rootRef"
          :class="['border-2 border-t-0', iframeClass]">
          <div v-for="(block, key) in layoutState" :key="key + '-' + previewKey">
            <component class="flex-1 overflow-auto active-block my-3" :is="getComponent(key)"
              :routeEditFamiliesOverview="props.data.update_family_route" :screenType="currentView" :modelValue="{
                ...block?.fieldValue,
                ...dataPicked.family
              }" />
          </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center text-gray-500 flex-1">
          <FontAwesomeIcon :icon="faInfoCircle" class="text-4xl mb-2" />
          <h3 class="text-lg font-semibold">
            {{ trans("No Family selected") }}
          </h3>
        </div>
      </div>
    </div>
  </div>

  <!-- DRAWER -->
  <Drawer v-model:visible="visibleDrawer" position="right" :pt="{
    root: { style: 'width: 30vw' },
    content: { class: 'flex flex-col h-full' }
  }">
    <template #header>
      <div>
        <h2 class="text-base font-semibold">
          {{ trans("Family") }}
        </h2>
        <p class="text-xs text-gray-500">
          {{ trans("Choose a family to preview") }}
        </p>
      </div>
    </template>

    <div class="flex-1 overflow-y-auto p-4">
      <FamilyList :dataList="props.data.family" @ChangeFamily="onChangeFamily" :active="dataPicked.family?.slug" />
    </div>
  </Drawer>
</template>