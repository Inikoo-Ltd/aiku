<script setup lang="ts">
import { computed } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
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
import { faEyeSlash, faInfoCircle } from "@fal"
import { faHeart, faLowVision } from "@far"
import { debounce, get } from "lodash"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"


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

type SideMenuTabKey = "menu" | "styling" | "custom_styling" | "templates"


const data = defineModel<any>("data")

defineProps<{
	uploadImageRoute : routeType
	webBlockTypes: {
		data: Array<any>
	}
}>()


const emits = defineEmits<{
	(e: "sendToIframe", value: object): void
	(e: "auto-save", value: object): void
}>()


const selectedTabKey = defineModel<SideMenuTabKey>("tab", { default: "menu" })

const tabs = computed(() => {
	const templateTab = { key: "templates", label: ctrans("Template"), icon: faThLarge }

	if (!data.value) {
		return [templateTab]
	}

	return [
		{ key: "menu", label: ctrans("Menu"), icon: faList },
		{ key: "styling", label: ctrans("Style"), icon: faPaintBrushAlt },
		{ key: "custom_styling", label: ctrans("Custom nav"), icon: faPaintBrush, tooltip: ctrans("Styling for custom navigation") },
		templateTab,
	]
})

const activeTabKey = computed(() =>
	tabs.value.some((tab) => tab.key === selectedTabKey.value) ? selectedTabKey.value : tabs.value[0].key
)

const isFollowSidebar = computed(() => get(data.value, ["data", "fieldValue", "setting_on_sidebar", "is_follow"], false))

const autoSave = (value: any) => {
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
	<div class="flex h-full flex-col">
		<div class="flex shrink-0 border-b border-gray-200 bg-white px-1">
			<button v-for="tab in tabs" :key="tab.key" type="button"
				class="flex flex-1 items-center justify-center gap-1.5 border-b-2 px-1.5 py-2 text-xs font-medium whitespace-nowrap transition-colors"
				:class="activeTabKey === tab.key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-800'"
				v-tooltip="tab.tooltip"
				@click="selectedTabKey = tab.key">
				<FontAwesomeIcon :icon="tab.icon" class="text-[11px]" fixed-width aria-hidden="true" />
				{{ tab.label }}
			</button>
		</div>

		<div class="compact-side-editor flex-1 overflow-y-auto">
			<div v-if="activeTabKey === 'templates'" class="px-3 pb-3">
				<WebBlockListDnd
					:webBlockTypes="webBlockTypes"
					@pick-block="onPickBlock"
					:selectedWeblock="data?.code"
				/>
			</div>

			<template v-else-if="activeTabKey === 'menu'">
				<div v-if="isFollowSidebar"
					class="m-2 flex gap-2 rounded border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
					<FontAwesomeIcon :icon="faInfoCircle" class="mt-0.5" fixed-width aria-hidden="true" />
					{{ ctrans("The menu follows the sidebar, so this list is not shown on the website.") }}
				</div>
				<div :class="isFollowSidebar ? 'pointer-events-none opacity-40' : ''">
					<SetMenuListWorkshop
						v-model:data="data"
						@update:data="autoSave"
						:uploadImageRoute
					/>
				</div>
			</template>

			<SideEditor v-else-if="activeTabKey === 'styling'"
				:modelValue="get(data, ['data', 'fieldValue'], null)"
				:blueprint="Blueprint.blueprint"
				@update:modelValue="updateFieldValue"
			/>

			<SideEditor v-else-if="activeTabKey === 'custom_styling'"
				:modelValue="get(data, ['data', 'fieldValue'], null)"
				:blueprint="BlueprintForCustomTopAndBottomNavigation.blueprint"
				@update:modelValue="updateFieldValue"
			/>
		</div>
	</div>
</template>

<style scoped lang="scss">
.compact-side-editor {
	:deep(.p-accordionheader) {
		padding: 0.5rem 0.75rem;
		font-size: 0.8125rem;
	}

	:deep(.p-accordionpanel) {
		border-width: 0 0 1px 0;
	}

	:deep(.p-accordioncontent-content) {
		padding: 0.625rem 0.75rem !important;
		font-size: 0.8125rem;
	}
}
</style>
