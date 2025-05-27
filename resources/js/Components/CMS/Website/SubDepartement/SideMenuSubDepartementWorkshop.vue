<script setup lang="ts">
import { ref, computed, inject } from "vue"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { TabGroup, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import WebBlockListDnd from "@/Components/CMS/Fields/WebBlockListDnd.vue"
import SetMenuListWorkshop from "@/Components/CMS/Fields/SetMenuListWorkshop.vue"
import axios from "axios"
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
	faList,
	faPaintBrushAlt,
} from "@fas"
import { faHeart, faLowVision } from "@far"
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue"
import { getBlueprint } from "@/Composables/getBlueprintWorkshop"
import DepartementListTree from "../Departement/DepartementListTree.vue"
import SubDepartementListTree from "./SubDepartementListTree.vue"
/* import DepartementListTree from "./DepartementListTree.vue" */

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
			component: string,
			fieldValue: Object
		}
	}
  dataList: Array<any>
	autosaveRoute: routeType
	webBlockTypes: {
		data: Array<any>
	}
}>()

const emits = defineEmits<{
    (e: 'setUpTemplate', value: string | number): void
    (e: 'onChangeDepartment', value: object): void
    (e: 'autoSave'): void
}>()

const selectedTab = ref(props.data ? 1 : 0)

const tabs = [
	{ label: 'Templates', icon: faThLarge, tooltip: 'template' },
	{ label: 'Menu', icon: faList, tooltip: 'menu' },
	{ label: 'Settings', icon: faPaintBrushAlt, tooltip: 'setting' }
]

function changeTab(index: Number) {
	selectedTab.value = index
}

const computedTabs = computed(() => {
	return props.data
		? tabs
		: [tabs[0]]
})

const onPickBlock = (value: object) => {
	emits('setUpTemplate', value)
}




</script>

<template>
  <div class="h-full flex flex-col">
    <TabGroup :selectedIndex="selectedTab" @change="changeTab" as="div" class="flex flex-col h-full">
      <!-- Sticky Tabs -->
      <TabList
        class="flex border-b border-gray-300 bg-white sticky top-0 z-10 shadow-sm"
      >
        <Tab
          v-for="(tab, index) in computedTabs"
          :key="index"
          class="flex items-center gap-2 px-4 py-2 font-medium text-gray-600 hover:bg-gray-100 focus:outline-none"
          :class="{
            'bg-white text-indigo-600 border-b-2 border-indigo-600':
              selectedTab === index,
          }"
        >
          <FontAwesomeIcon :icon="tab.icon" fixed-width v-tooltip="tab.tooltip" />
        </Tab>
      </TabList>

      <!-- Scrollable Panels -->
      <TabPanels class="overflow-auto flex-grow bg-gray-50">
        <TabPanel class="p-4">
          <WebBlockListDnd
            :webBlockTypes="webBlockTypes"
            @pick-block="onPickBlock"
            :selectedWeblock="data?.code"
          />
        </TabPanel>
        <TabPanel v-if="data" class="p-4">
          <SubDepartementListTree  :dataList="dataList" @changeDepartment="(value)=>emits('onChangeDepartment', value)" />
        </TabPanel>
        <TabPanel v-if="data" class="p-4">
          <SideEditor 
            v-model="data.data.fieldValue" 
            :blueprint="getBlueprint(data.code)"
            @update:modelValue="(e) => { data.data.fieldValue = e, emits('autoSave') }"
            :uploadImageRoute="null" 
          />
        </TabPanel>
      </TabPanels>
    </TabGroup>
  </div>
</template>

<style scoped>
html, body, .h-full {
  height: 100%;
}
</style>

