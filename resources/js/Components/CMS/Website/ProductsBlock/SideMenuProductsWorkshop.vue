<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from "@headlessui/vue"
import { routeType } from "@/types/route"

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
  faLowVision
)

const props = defineProps<{
  data: {
    data: {
      component: string
      fieldValue: Record<string, any>
    }
    code?: string
  }
  dataList: any[]
  autosaveRoute: routeType
  webBlockTypes: { data: any[] }
  selectedTab: string | number
  tabs: Array<{ icon: any; tooltip?: string }>
}>()

const emit = defineEmits<{
  (e: "setUpTemplate", value: string | number): void
  (e: "autoSave"): void
  (e: "update:selectedTab", value: string | number): void
}>()

const changeTab = (index: number) => emit("update:selectedTab", index)

const onPickBlock = (value: object) => emit("setUpTemplate", value)

const handleAutoSave = (value: any) => {
  props.data.data.fieldValue = value
  emit("autoSave")
}
</script>

<template>
  <div class="h-full flex flex-col">
    <TabGroup :selectedIndex="selectedTab" @change="changeTab" as="div" class="flex flex-col h-full">
      <TabList class="flex border-b border-gray-300 bg-white sticky top-0 z-10 shadow-sm">
        <Tab
          v-for="(tab, index) in tabs"
          :key="index"
          class="flex items-center gap-2 px-4 py-2 font-medium text-gray-600 hover:bg-gray-100 focus:outline-none"
          :class="{
            'bg-white text-indigo-600 border-b-2 border-indigo-600': selectedTab === index,
          }"
        >
          <FontAwesomeIcon :icon="tab.icon" fixed-width v-tooltip="tab.tooltip" />
        </Tab>
      </TabList>

      <TabPanels class="overflow-auto flex-grow bg-gray-50">
        <TabPanel class="p-4">
          <WebBlockListDnd
            :webBlockTypes="webBlockTypes"
            :selectedWeblock="data?.code"
            @pick-block="onPickBlock"
          />
        </TabPanel>

        <TabPanel v-if="data?.data?.fieldValue" class="p-4">
          <SideEditor
            v-model="data.data.fieldValue"
            :blueprint="getBlueprint(data.code)"
            @update:modelValue="handleAutoSave"
          />
        </TabPanel>


        <TabPanel v-if="data?.data?.fieldValue" class="p-4">
          <SideEditor
            v-model="data.data.fieldValue"
            :blueprint="getBlueprint('bestseller-1')"
            @update:modelValue="handleAutoSave"
          />
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
</style>
