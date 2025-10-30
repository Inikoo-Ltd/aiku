<script setup lang="ts">
import { ref, computed, inject } from "vue"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { TabGroup, Tab, TabPanels, TabPanel, TabList } from '@headlessui/vue'
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
	faPaintBrush,
} from "@fas"
import { faEyeSlash } from "@fal"
import { faHeart, faLowVision } from "@far"
import { notify } from "@kyvg/vue3-notification"
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue"
import Blueprint from "./Blueprint"
import BlueprintForCustomTopAndBottomNavigation from "./BlueprintForCustomTopAndBottomNavigation"
import { debounce, get, set } from "lodash"
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
	faLowVision,
	faEyeSlash
)

const props = defineProps<{
	data: {
		data: {
			component: string,
			fieldValue: Object
		}
	}
	webBlockTypes: {
		data: Array<any>
	}
}>()

const emits = defineEmits<{
	(e: 'sendToIframe', value: Object): void
	(e: 'auto-save', value: Object): void
}>()

const layout = inject('layout', layoutStructure)

const selectedTab = ref(props.data ? 1 : 0)


function changeTab(index: number) {
	selectedTab.value = index
}

const tabs = computed(() => {
	const isFollowSidebar = get(props.data, ['data', 'fieldValue', 'setting_on_sidebar', 'is_follow'], false)
	const tabsList = [
		{ label: 'Templates', icon: faThLarge, tooltip: trans('Template') },
		{ label: 'Menu', icon: faList, tooltip: trans('Menu') },
		{ label: 'Styling', icon: faPaintBrushAlt, tooltip: trans('Styling') },
		{ label: 'Styling (for custom navigation)', icon: faPaintBrush, tooltip: trans('Styling (custom navigation)') },
	]

	if (isFollowSidebar) {
		return tabsList
	} else {
		// Remove last tab if not following sidebar
		return tabsList.slice(0, -1)
	}
})
const computedTabs = computed(() => {
	return props.data
		? tabs.value
		: [tabs.value[0]]
})

const onPickBlock = (value: object) => {
	autoSave(value)
}

const autoSave = async (value) => {
 emits('auto-save',value)
}

const debAutoSave = debounce((data) => {
	autoSave(data)
}, 1000)

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
				
				<!-- need fix this components edit drawer -->
				<div class="relative">
					<SetMenuListWorkshop
						:data="data"
						@auto-save="() => autoSave(data)"
					/>
					<Transition name="slide-to-right">
						<div v-if="get(data, ['data', 'fieldValue', 'setting_on_sidebar', 'is_follow'], false)" class="rounded text-yellow-500 bg-gray-500/80 absolute inset-0 w-[110%] h-[102%] top-1/2 -translate-y-1/2 left-1/2 -translate-x-1/2 flex flex-col items-center justify-center text-center">
							<FontAwesomeIcon icon="fal fa-eye-slash" class="text-5xl" fixed-width aria-hidden="true" />
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
					@update:modelValue="(e) => { set(data, ['data', 'fieldValue'], e) , debAutoSave(data)}"
				/>
			</TabPanel>

			<!-- Tab: Styling (custom navigation) -->
			<TabPanel v-if="data">
				<div class="relative">
					<SideEditor
						:modelValue="get(data, ['data', 'fieldValue'], null)"
						:blueprint="BlueprintForCustomTopAndBottomNavigation.blueprint"
						@update:modelValue="(e) => { set(data, ['data', 'fieldValue'], e) , debAutoSave(data)}"
					/>
					
					<Transition name="slide-to-right">
						<div v-if="!get(data, ['data', 'fieldValue', 'setting_on_sidebar', 'is_follow'], false)" class="rounded text-yellow-500 bg-gray-500/80 absolute inset-0 w-full h-full top-1/2 -translate-y-1/2 left-1/2 -translate-x-1/2 flex flex-col items-center justify-center text-center">
							<FontAwesomeIcon icon="fal fa-eye-slash" class="text-5xl" fixed-width aria-hidden="true" />
							{{ trans("Will not showing due the data not follow Sidebar") }}
						</div>
					</Transition>
				</div>
			</TabPanel>
		</TabPanels>
	</TabGroup>
</template>

<style scoped lang="scss"></style>
