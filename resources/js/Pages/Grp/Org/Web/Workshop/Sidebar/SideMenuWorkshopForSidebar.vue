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
import { notify } from "@kyvg/vue3-notification"
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue"
import Blueprint from "./Blueprint"

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
	autosaveRoute: routeType
	webBlockTypes: {
		data: Array<any>
	}
}>()

const emits = defineEmits<{
	(e: 'sendToIframe', value: Object): void
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
	autoSave(value)
}

let controller: AbortController | null = null
const autoSave = async (value) => {
  // Cancel the previous request if still pending
  if (controller) {
    controller.abort()
  }

  // Create a new controller for this request
  controller = new AbortController()

  try {
    const response = await axios.patch(
      route(props.autosaveRoute.name, props.autosaveRoute.parameters),
      { layout: value },
      { signal: controller.signal }
    )
    emits('sendToIframe', { key: "reload", value: {} })
  } catch (error: any) {
    if (axios.isCancel(error) || error.name === "CanceledError" || error.message === "canceled") {
      console.log("Autosave request cancelled")
      return
    }

    notify({
      title: "Something went wrong.",
      text: error.message,
      type: "error",
    })
  }
}


</script>

<template>
	<TabGroup :selectedIndex="selectedTab" @change="changeTab">
		<TabList class="flex border-b border-gray-300">
			<Tab v-for="(tab, index) in computedTabs" :key="index"
				class="flex items-center gap-2 px-4 py-2 font-medium text-gray-600 rounded-t-lg hover:bg-gray-100 focus:outline-none"
				:class="{ 'bg-white text-indigo-600 border-b-2 border-indigo-600': selectedTab === index }">
				<FontAwesomeIcon :icon="tab.icon" fixed-width v-tooltip="tab.tooltip" />
			</Tab>
		</TabList>
		<TabPanels>
			<TabPanel>
				<WebBlockListDnd :webBlockTypes="webBlockTypes" @pick-block="onPickBlock"
					:selectedWeblock="data.code" />
			</TabPanel>
			<TabPanel v-if="data">
				<!-- need fix this components edit drawer -->
				<SetMenuListWorkshop :data="data" :autosaveRoute="autosaveRoute" @auto-save="() => autoSave(data)" />
			</TabPanel>
			<TabPanel v-if="data">
				<SideEditor 
					v-model="data.data.fieldValue" 
					:blueprint="Blueprint.blueprint"
					@update:modelValue="(e) => { data.data.fieldValue = e , autoSave(data)}"
					:uploadImageRoute="null" />
			</TabPanel>
		</TabPanels>
	</TabGroup>
</template>

<style scoped lang="scss"></style>
