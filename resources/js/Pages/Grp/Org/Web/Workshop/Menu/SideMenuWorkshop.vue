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
import { get, set } from "lodash"
import Toggle from "@/Components/Pure/Toggle.vue"
import { trans } from "laravel-vue-i18n"
import InformationIcon from "@/Components/Utils/InformationIcon.vue"
import { Link } from "@inertiajs/vue3"
import { layoutStructure } from "@/Composables/useLayoutStructure"

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
	shopType: string  // 'fulfilment' | 'dropshipping' | 'b2b'
}>()

const emits = defineEmits<{
	(e: 'sendToIframe', value: Object): void
}>()

const layout = inject('layout', layoutStructure)

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

const urlToSidebar = computed(() => {
	return route('grp.org.shops.show.web.websites.workshop.sidebar', {
		organisation: layout.currentParams?.organisation || 'x',
		shop: layout.currentParams?.shop || 'x',
		website: layout.currentParams?.website || 'x',
	})
})

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
			<!-- Tab: Template -->
			<TabPanel>
				<WebBlockListDnd :webBlockTypes="webBlockTypes" @pick-block="onPickBlock"
					:selectedWeblock="data.code" />
			</TabPanel>

			<!-- Tab: Menu -->
			<TabPanel v-if="data">
				<div v-if="shopType !== 'fulfilment'" class="border-dashed border-b border-gray-300 pb-6 mb-6">
					<div class="flex justify-between mt-4 ">
						<div>
							{{ trans("Is follow sidebar navigation?") }}
							<InformationIcon :information="trans('The data will be same like Sidebar')" />
						</div>

						<Toggle
							:modelValue="get(data, ['data', 'fieldValue', 'setting_on_sidebar', 'is_follow'], false)"
							@update:modelValue="(value) => { set(data, ['data', 'fieldValue', 'setting_on_sidebar', 'is_follow'], value), autoSave(data) }"
						/>
					</div>

					<Link :href="urlToSidebar" class="text-xs underline hover:text-blue-500 cursor-pointer mt-2">
						{{ trans("Open Sidebar workhop") }}
						<FontAwesomeIcon icon="fal fa-external-link-alt" class="" fixed-width aria-hidden="true" />
					</Link>
				</div>
				
				<!-- need fix this components edit drawer -->
				<div class="relative">
					<SetMenuListWorkshop
						:data="data"
						:autosaveRoute="autosaveRoute"
						@auto-save="() => autoSave(data)"
					/>
					<Transition name="slide-to-right">
						<div v-if="get(data, ['data', 'fieldValue', 'sidebar', 'is_follow'], false)" class="rounded bg-gray-700/30 absolute inset-0 w-[110%] h-[110%] top-1/2 -translate-y-1/2 left-1/2 -translate-x-1/2 flex items-center justify-center text-white text-center">
							{{ trans("Will not showing due the data follow Sidebar") }}
						</div>
					</Transition>
				</div>
			</TabPanel>

			<!-- Tab: Setting -->
			<TabPanel v-if="data">
				<SideEditor 
					:modelValue="get(data, ['data', 'fieldValue'], null)" 
					:blueprint="Blueprint.blueprint"
					@update:modelValue="(e) => { set(data, ['data', 'fieldValue'], e) , autoSave(data)}"
				/>
			</TabPanel>
		</TabPanels>
	</TabGroup>
</template>

<style scoped lang="scss"></style>
