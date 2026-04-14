<script setup lang="ts">
import { ref, computed } from "vue"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from "@headlessui/vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

import WebBlockListDnd from "@/Components/CMS/Fields/WebBlockListDnd.vue"
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue"
import { getBlueprint } from "@/Composables/getBlueprintWorkshop"

import {
  faChevronRight,
  faSignOutAlt,
  faShoppingCart,
  faSearch,
  faChevronDown,
  faTimes,
  faPlusCircle,
  faBars,
  faThLarge,
  faPaintBrushAlt,
} from "@fas"
import { faHeart, faLowVision } from "@far"

library.add(
  faChevronRight,
  faSignOutAlt,
  faShoppingCart,
  faHeart,
  faSearch,
  faChevronDown,
  faTimes,
  faPlusCircle,
  faBars,
  faLowVision,
  faThLarge,
  faPaintBrushAlt
)

const props = defineProps<{
  data: {
    code?: string
    data?: {
      component: string
      fieldValue: Record<string, any>
    }
  }
  dataList: Array<any>
  autosaveRoute: routeType
  webBlockTypes: {
    data: Array<any>
  }
}>()

const emits = defineEmits<{
  (e: "setUpTemplate", value: string | number): void
  (e: "onChangeDepartment", value: object): void
  (e: "autoSave"): void
}>()

const selectedTab = ref(props.data?.data ? 1 : 0)

const tabs = [
  { label: "Templates", icon: faThLarge },
  { label: "Settings", icon: faPaintBrushAlt },
]

const computedTabs = computed(() => {
  return props.data?.data ? tabs : [tabs[0]]
})

function changeTab(index: number) {
  selectedTab.value = index
}

function onPickBlock(value: any) {
  emits("setUpTemplate", value)
}
</script>

<template>
  <div class="h-full flex flex-col bg-gray-50">
    <TabGroup
      :selectedIndex="selectedTab"
      @change="changeTab"
      as="div"
      class="flex flex-col h-full"
    >
      <!-- Tabs -->
      <TabList
        class="flex items-center gap-1 px-2 py-1 bg-white border-b border-gray-200 sticky top-0 z-10"
      >
        <Tab
          v-for="(tab, index) in computedTabs"
          :key="index"
          v-slot="{ selected }"
          class="relative flex items-center gap-2 px-3 py-1.5 text-sm rounded-md transition-all duration-200 focus:outline-none"
          :class="
            selected
              ? 'text-indigo-600 bg-indigo-50'
              : 'text-gray-500 hover:bg-gray-100'
          "
        >
          <FontAwesomeIcon :icon="tab.icon" class="text-xs" />
          <span class="hidden sm:inline">{{ tab.label }}</span>

          <!-- active indicator -->
          <span
            v-if="selected"
            class="absolute bottom-0 left-1/2 -translate-x-1/2 w-4 h-[2px] bg-indigo-500 rounded-full"
          />
        </Tab>
      </TabList>

      <!-- Panels -->
      <TabPanels class="flex-1 overflow-auto p-2 space-y-2">
        <!-- Templates -->
        <TabPanel>
          <div
            class="bg-white rounded-lg border border-gray-200 shadow-sm p-2 hover:shadow-md transition-shadow"
          >
            <WebBlockListDnd
              :webBlockTypes="webBlockTypes"
              @pick-block="onPickBlock"
              :selectedWeblock="data?.code"
            />
          </div>
        </TabPanel>

        <!-- Settings -->
        <TabPanel v-if="data?.data?.fieldValue">
          <div
            class="bg-white rounded-lg border border-gray-200 shadow-sm p-3 hover:shadow-md transition-shadow"
          >
            <SideEditor
              v-model="data.data.fieldValue"
              :blueprint="getBlueprint(data.code)"
              @update:modelValue="(e) => {
                data.fieldValue = e
                emits('autoSave')
              }"
              :uploadImageRoute="null"
            />
          </div>
        </TabPanel>
      </TabPanels>
    </TabGroup>
  </div>
</template>

<style scoped>
html,
body,
.h-full {
  height: 100%;
}

/* smooth scroll */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-thumb {
  background: #e5e7eb;
  border-radius: 999px;
}
</style>