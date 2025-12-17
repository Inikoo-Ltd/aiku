<script setup lang="ts">
import { ref, computed, inject } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { TabGroup, Tab, TabPanels, TabPanel, TabList } from "@headlessui/vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import WebBlockListDnd from "@/Components/CMS/Fields/WebBlockListDnd.vue"
import SetMenuListWorkshop from "@/Components/CMS/Fields/SetMenuListWorkshop.vue"
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue"
import Blueprint from "./Blueprint"
import BlueprintForCustomTopAndBottomNavigation from "./BlueprintForCustomTopAndBottomNavigation"
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
	faPaintBrush,
} from "@fas"
import { faEyeSlash } from "@fal"
import { faHeart, faLowVision } from "@far"
import { debounce, get } from "lodash"
import { trans } from "laravel-vue-i18n"
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
	faLowVision,
	faEyeSlash
)


const data = defineModel<any>("data")

defineProps<{
	webBlockTypes: {
		data: Array<any>
	}
}>()


const emits = defineEmits<{
	(e: "sendToIframe", value: object): void
	(e: "auto-save", value: object): void
}>()


const layout = inject("layout", layoutStructure)
const selectedTab = ref(data.value ? 1 : 0)


const tabs = computed(() => [
	{ label: "Templates", icon: faThLarge, tooltip: trans("Template") },
	{ label: "Menu", icon: faList, tooltip: trans("Menu") },
	{ label: "Styling", icon: faPaintBrushAlt, tooltip: trans("Styling") },
	{
		label: "Styling (custom navigation)",
		icon: faPaintBrush,
		tooltip: trans("Styling (custom navigation)"),
	},
])

const computedTabs = computed(() =>
	data.value ? tabs.value : [tabs.value[0]]
)

const changeTab = (index: number) => {
	selectedTab.value = index
}


const autoSave = (value: any) => {
	console.log("Auto saving...", value)
	emits("auto-save", value)
}

const debAutoSave = debounce((value: any) => {
	autoSave(value)
}, 1000)


const updateData = (updater: (draft: any) => void) => {
	if (!data.value) return
	const cloned = structuredClone(data.value)
	updater(cloned)
	data.value = cloned
	debAutoSave(cloned)
}


const onPickBlock = (value: object) => {
	autoSave(value)
}

const updateFieldValue = (value: any) => {
	updateData(draft => {
		draft.data.fieldValue = value
	})
}
</script>

<template>
	<TabGroup :selectedIndex="selectedTab" @change="changeTab">
		<TabList class="flex border-b border-gray-300">
			<Tab
				v-for="(tab, index) in computedTabs"
				:key="index"
				class="flex items-center gap-2 px-4 py-2 font-medium text-gray-600 rounded-t-lg hover:bg-gray-100 focus:outline-none"
				:class="{
					'bg-white text-indigo-600 border-b-2 border-indigo-600':
						selectedTab === index,
				}"
			>
				<FontAwesomeIcon :icon="tab.icon" fixed-width v-tooltip="tab.tooltip" />
			</Tab>
		</TabList>

		<TabPanels>
			<!-- Template -->
			<TabPanel>
				<WebBlockListDnd
					:webBlockTypes="webBlockTypes"
					@pick-block="onPickBlock"
					:selectedWeblock="data?.code"
				/>
			</TabPanel>

			<!-- Menu -->
			<TabPanel v-if="data">
				<div class="relative">
					<SetMenuListWorkshop
						v-model:data="data"
						@update:data="autoSave"
					/>

					<Transition name="slide-to-right">
						<div
							v-if="
								get(
									data,
									['data', 'fieldValue', 'setting_on_sidebar', 'is_follow'],
									false
								)
							"
							class="rounded text-yellow-500 bg-gray-500/80 absolute inset-0 w-[110%] h-[102%]
							top-1/2 -translate-y-1/2 left-1/2 -translate-x-1/2
							flex flex-col items-center justify-center text-center"
						>
							<FontAwesomeIcon
								icon="fal fa-eye-slash"
								class="text-5xl"
								fixed-width
							/>
							{{ trans("Will not showing due the data follow Sidebar") }}
						</div>
					</Transition>
				</div>
			</TabPanel>

			<!-- Setting -->
			<TabPanel v-if="data">
				<SideEditor
					:modelValue="get(data, ['data', 'fieldValue'], null)"
					:blueprint="Blueprint.blueprint"
					@update:modelValue="updateFieldValue"
				/>
			</TabPanel>

			<!-- Styling Custom Navigation -->
			<TabPanel v-if="data">
				<SideEditor
					:modelValue="get(data, ['data', 'fieldValue'], null)"
					:blueprint="BlueprintForCustomTopAndBottomNavigation.blueprint"
					@update:modelValue="updateFieldValue"
				/>
			</TabPanel>
		</TabPanels>
	</TabGroup>
</template>
