<script setup lang="ts">
import { ref, inject, provide, reactive, computed, watch, onMounted } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCube, faLink } from "@fal"
import { faStar, faCircle, faChevronLeft, faChevronRight, faDesktop } from "@fas"
import { router } from "@inertiajs/vue3"
import debounce from "lodash/debounce"
import { cloneDeep } from "lodash-es"

import { getComponent } from "@/Composables/getWorkshopComponents"
import { setColorStyleRootByEl } from "@/Composables/useApp"
import { notify } from "@kyvg/vue3-notification"

import SideMenuProductWorkshop from "./SideMenuProductBlockWorkshop.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import ScreenView from "@/Components/ScreenView.vue"
import ToggleSwitch from "primevue/toggleswitch"

import type { routeType } from "@/types/route"
import { trans } from "laravel-vue-i18n"

import "@/../css/Iris/editor.css"

library.add(
  faCube,
  faLink,
  faStar,
  faCircle,
  faChevronLeft,
  faChevronRight,
  faDesktop
)

/* -------------------------------- props -------------------------------- */
const props = defineProps<{
  data: {
    web_block_types: any
    autosaveRoute: routeType
    layout: any
    products: routeType
    families: any
  }
  currency: {
    code: string
    name: string
  }
  layout_theme: Array<any>
}>()


const reload = inject<() => void>("reload")
const parentLayout = inject<any>("layout")


const isModalOpen = ref(false)
const isLoadingSave = ref(false)
const currentView = ref<"desktop" | "tablet" | "mobile">("desktop")
const rootRef = ref<HTMLElement | null>(null)


const localLayout = reactive(cloneDeep(parentLayout))

localLayout.app.theme = props.layout_theme.color
localLayout.iris = {
  ...localLayout.iris,
  is_logged_in: true,
}

provide("layout", localLayout)
provide("currentView", currentView)


const autosave = () => {
  const payload = cloneDeep(props.data.layout)

  delete payload?.data?.fieldValue?.product

  router.patch(
    route(
      props.data.autosaveRoute.name,
      props.data.autosaveRoute.parameters
    ),
    { layout: payload },
    {
      onStart: () => (isLoadingSave.value = true),
      onFinish: () => {
        isLoadingSave.value = false
        reload?.()
      },
      onError: (errors: any) => {
        notify({
          title: "Autosave Failed",
          text: errors?.message || "Unknown error occurred",
          type: "error",
        })
      },
    }
  )
}

const debouncedAutosave = debounce(autosave, 500)


const onPickTemplate = (template: any) => {
  isModalOpen.value = false
  props.data.layout = template
  debouncedAutosave()
}


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


onMounted(() => {
  if (rootRef.value && props.layout_theme?.color) {
    setColorStyleRootByEl(rootRef.value, props.layout_theme.color)
  }
})
</script>


<template>
  <div class="h-[85vh] grid grid-cols-12 gap-4 p-3">
    <!-- Sidebar -->
    <div class="col-span-3 bg-white rounded-xl shadow-md py-4 overflow-y-auto border">
      <SideMenuProductWorkshop
        :data="props.data.layout"
        :webBlockTypes="props.data.web_block_types"
        @auto-save="autosave"
        @set-up-template="onPickTemplate"
      />
    </div>

    <!-- Preview -->
    <div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-auto border">
      <!-- Toolbar -->
      <div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b shrink-0">
        <div class="hidden lg:block py-1 px-2 cursor-pointer" v-tooltip="'Desktop view'">
          <ScreenView v-model="currentView" />
        </div>

        <div class="flex items-center gap-3">
          <span class="text-sm font-medium">{{ trans("Login")}}</span>
          <ToggleSwitch v-model="localLayout.iris.is_logged_in" />
        </div>
      </div>

      <!-- Content -->
      <div class="flex-1 overflow-auto">
        <div
          v-if="props.data.layout?.data?.fieldValue?.product"
          class="editor-class"
        >
          <div
            ref="rootRef"
            class="relative flex-1 overflow-auto border-2 border-t-0"
            :class="iframeClass"
          >
            <component
              class="w-full pointer-events-none"
              :is="getComponent(props.data.layout.code, {
                shop_type: parentLayout?.shopState?.type,
              })"
              :code="props.data.layout.code"
              :screenType="currentView"
              :modelValue="props.data.layout.data.fieldValue"
              templateEdit="template"
              :currency="currency"
            />
          </div>
        </div>

        <EmptyState v-else />
      </div>
    </div>
  </div>
</template>
