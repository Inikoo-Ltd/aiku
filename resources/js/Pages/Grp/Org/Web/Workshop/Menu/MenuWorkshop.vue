<script setup lang="ts">
import { ref, IframeHTMLAttributes, watch, provide, inject, computed, toRaw } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import Publish from "@/Components/Publish.vue"
import axios from "axios"
import { Head, Link } from "@inertiajs/vue3"
import ScreenView from "@/Components/ScreenView.vue"
import { setIframeView } from "@/Composables/Workshop"
import { routeType } from "@/types/route"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import SideMenuWorkshop from "./SideMenuWorkshop.vue"
import MenuTemplatePicker from "./MenuTemplatePicker.vue"
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
import { faChevronDoubleLeft, faChevronDoubleRight, faExternalLinkAlt } from "@fal"
import { faHeart, faLowVision } from "@far"
import { ctrans } from "@/Composables/useTrans"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { get, set } from "lodash"
import Toggle from "@/Components/Pure/Toggle.vue"
import InformationIcon from "@/Components/Utils/InformationIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import ConditionIcon from "@/Components/Utils/ConditionIcon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

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
	pageHead: PageHeadingTypes
	title: string
	uploadImageRoute: routeType
	data: {}
	status: boolean
	autosaveRoute: routeType
	webBlockTypes: { data: Array<{ id: number, code: string }> }
	domain: string
	shop_type: string  // 'fulfilment' | 'dropshipping' | 'b2b'
}>()

const Navigation = ref(props.data.menu)
const isLoading = ref(false)
const status = ref(props.status)
const comment = ref("")
const isIframeLoading = ref(true)
const iframeClass = ref(setIframeView("desktop"))
const _iframe = ref<IframeHTMLAttributes | null>(null)
const iframeSrc = ref(route("grp.websites.header.preview", [route().params["website"]]))
const layout = inject('layout', layoutStructure)
const errorMessage = ref<string | null>(null)
const pickingTemplateCode = ref<string | null>(null)
const hasTemplate = computed(() => !!Navigation.value?.code)

const readErrorMessage = (error: any): string =>
	error?.response?.data?.message || error?.message || ctrans("Unknown error occurred")

const onPublish = async (action: routeType, popover: Function) => {
	try {
		if (!action || !action.method || !action.name || !action.parameters) {
			throw new Error("Invalid action parameters")
		}

		isLoading.value = true
		errorMessage.value = null

		await axios[action.method](route(action.name, action.parameters), {
			comment: comment.value,
			layout: { ...Navigation.value, status: status.value },
		})
		popover.close()
	} catch (error) {
		console.error("Error:", error)
		errorMessage.value = ctrans("Publish failed: :message", { message: readErrorMessage(error) })
	} finally {
		// Ensure loading state is updated
		isLoading.value = false
	}
}

const sendToIframe = (data: any) => {
	_iframe.value?.contentWindow.postMessage(data, "*")
}


const handleIframeError = () => {
	console.error("Failed to load iframe content.")
}

const currentView = ref('desktop')
provide('currentView', currentView)
watch(currentView, (newValue) => {
	iframeClass.value = setIframeView(newValue)
})


const sideEditorStorageKey = "menu-workshop-side-editor-open"
const readSideEditorOpen = (): boolean => {
	try {
		return localStorage.getItem(sideEditorStorageKey) !== "false"
	} catch {
		return true
	}
}
const isSideEditorOpen = ref(readSideEditorOpen())
watch(isSideEditorOpen, (isOpen) => {
	try {
		localStorage.setItem(sideEditorStorageKey, String(isOpen))
	} catch {
	}
})

const selectedSideTab = ref<"menu" | "styling" | "custom_styling" | "templates">(props.data.menu ? "menu" : "templates")

const railTabs = computed(() => [
	{ key: "menu", label: ctrans("Menu"), icon: faList },
	{ key: "styling", label: ctrans("Style"), icon: faPaintBrushAlt },
	{ key: "custom_styling", label: ctrans("Styling for custom navigation"), icon: faPaintBrush },
	{ key: "templates", label: ctrans("Template"), icon: faThLarge },
])

const openSideTab = (tabKey: "menu" | "styling" | "custom_styling" | "templates") => {
	selectedSideTab.value = tabKey
	isSideEditorOpen.value = true
}

const isFollowSidebar = computed(() =>
	get(Navigation.value, ["data", "fieldValue", "setting_on_sidebar", "is_follow"], false)
)

const setFollowSidebar = (isFollow: boolean) => {
	const updatedNavigation = structuredClone(toRaw(Navigation.value))
	set(updatedNavigation, ["data", "fieldValue", "setting_on_sidebar", "is_follow"], isFollow)
	Navigation.value = updatedNavigation
	autoSave(updatedNavigation)
}

const urlToSidebar = computed(() => {
	return route('grp.org.shops.show.web.websites.workshop.sidebar', {
		organisation: layout.currentParams?.organisation || 'x',
		shop: layout.currentParams?.shop || 'x',
		website: layout.currentParams?.website || 'x',
	})
})

const statusSave = ref<null | 'loading' | 'success' | 'error'>(null)
let statusTimeout: ReturnType<typeof setTimeout> | null = null
const setStatus = (newStatus: null | 'loading' | 'success' | 'error') => {
    statusSave.value = newStatus
    if (statusTimeout) clearTimeout(statusTimeout)
    if (newStatus === 'success' || newStatus === 'error') {
        statusTimeout = setTimeout(() => {
            statusSave.value = null
        }, 3000)
    }
}
let controller: AbortController | null = null
const autoSave = async (value: any): Promise<boolean> => {
	if (controller) {
		controller.abort()
	}

	controller = new AbortController()

	setStatus('loading')
	errorMessage.value = null
	try {
		await axios.patch(
			route(props.autosaveRoute.name, props.autosaveRoute.parameters),
			{ layout: value },
			{ signal: controller.signal }
		)
		setStatus('success')
		Navigation.value = {...value}
		sendToIframe({ key: "reload", value: {} })
		return true
	} catch (error: any) {
		if (
			axios.isCancel(error) ||
			error.name === "CanceledError" ||
			error.message === "canceled"
		) {
			return false
		}

		errorMessage.value = ctrans("Save failed: :message", { message: readErrorMessage(error) })
		setStatus('error')
		return false
	} finally {
		if (controller && !controller.signal.aborted) {
			controller = null
		}
	}
}

const pickTemplate = async (template: { code: string }) => {
	pickingTemplateCode.value = template.code
	isIframeLoading.value = true

	const isSaved = await autoSave(template)

	pickingTemplateCode.value = null

	if (isSaved) {
		selectedSideTab.value = "menu"
		isSideEditorOpen.value = true
	}
}

</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #mainIcon v-if="statusSave === 'loading'">
			<LoadingIcon size="sm" />
		</template>
		<template #button-publish="{ action }">
			<Publish :isLoading="isLoading || statusSave === 'loading'" :is_dirty="true" v-model="comment"
				@onPublish="(popover) => onPublish(action.route, popover)" />
		</template>
	</PageHeading>

	<div class="h-[84vh] flex border-t border-gray-200">
		<aside v-if="hasTemplate && isSideEditorOpen" class="w-80 shrink-0 flex flex-col bg-[#F9F9F9] border-r border-gray-300">
			<div class="h-9 shrink-0 pl-3 pr-1.5 flex items-center justify-between border-b border-gray-300 bg-gray-50 text-sm">
				<div class="flex items-center gap-2 font-semibold">
					<FontAwesomeIcon :icon="faBars" fixed-width aria-hidden="true" />
					{{ ctrans("Menu") }}
				</div>
				<button type="button" class="h-7 w-7 rounded text-gray-500 hover:bg-gray-200 hover:text-gray-700"
					v-tooltip="ctrans('Collapse editor')" @click="isSideEditorOpen = false">
					<FontAwesomeIcon :icon="faChevronDoubleLeft" fixed-width aria-hidden="true" />
				</button>
			</div>

			<div v-if="shop_type !== 'fulfilment' && Navigation"
				class="shrink-0 px-3 py-2 flex items-center justify-between gap-2 border-b border-gray-200 bg-white text-xs">
				<div class="min-w-0">
					<div class="flex items-center gap-1 font-medium text-gray-700">
						{{ ctrans("Follow sidebar navigation") }}
						<InformationIcon :information="ctrans('The data will be same like Sidebar')" />
					</div>
					<Link :href="urlToSidebar" class="text-gray-500 hover:text-[var(--app-accent)] hover:underline">
						{{ ctrans("Open Sidebar workshop") }}
						<FontAwesomeIcon :icon="faExternalLinkAlt" class="text-[10px]" fixed-width aria-hidden="true" />
					</Link>
				</div>
				<Toggle size="sm" :modelValue="isFollowSidebar" @update:modelValue="setFollowSidebar" />
			</div>

			<div class="flex-1 min-h-0">
				<SideMenuWorkshop
					v-model:tab="selectedSideTab"
					:data="Navigation"
					:webBlockTypes="webBlockTypes"
					:uploadImageRoute
					@auto-save="autoSave"
					@sendToIframe="sendToIframe"
				/>
			</div>
		</aside>

		<aside v-else-if="hasTemplate" class="w-10 shrink-0 flex flex-col items-center bg-[#F9F9F9] border-r border-gray-300">
			<button type="button" class="h-9 w-full border-b border-gray-300 bg-gray-50 text-gray-500 hover:text-gray-700"
				v-tooltip="ctrans('Expand editor')" @click="isSideEditorOpen = true">
				<FontAwesomeIcon :icon="faChevronDoubleRight" fixed-width aria-hidden="true" />
			</button>
			<div class="flex flex-col items-center gap-0.5 py-1.5">
				<button v-for="tab in railTabs" :key="tab.key" type="button"
					class="h-8 w-8 rounded text-gray-600 hover:bg-gray-200 hover:text-gray-900"
					v-tooltip="tab.label" @click="openSideTab(tab.key)">
					<FontAwesomeIcon :icon="tab.icon" class="text-sm" fixed-width aria-hidden="true" />
				</button>
			</div>
		</aside>

		<section class="flex-1 min-w-0 flex flex-col bg-gray-100">
			<div v-if="hasTemplate" class="h-9 shrink-0 flex items-center justify-between gap-3 pr-3 bg-slate-200 border-b border-gray-300">
				<ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" />

				<div class="flex items-center gap-2 text-xs">
					<span class="tabular-nums" :class="errorMessage ? 'text-red-600' : 'text-gray-500'">
						<template v-if="statusSave === 'loading'">{{ ctrans("Saving…") }}</template>
						<template v-else-if="errorMessage">{{ errorMessage }}</template>
						<template v-else-if="statusSave === 'success'">{{ ctrans("All changes saved") }}</template>
					</span>
					<Button type="tertiary" size="xxs" icon="fas fa-save" :label="ctrans('Save')"
						:loading="statusSave === 'loading'" @click="() => autoSave(Navigation)" />
				</div>
			</div>

			<div v-if="hasTemplate" class="relative flex-1 min-h-0 overflow-hidden"
				:class="currentView === 'desktop' ? '' : 'py-4'">
				<div v-if="isIframeLoading"
					class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-4 bg-white/80">
					<LoadingIcon class="text-4xl" />
					<span class="text-sm text-gray-500">{{ ctrans("Loading preview") }}</span>
				</div>
				<iframe :src="iframeSrc" :title="props.title" ref="_iframe"
					class="bg-white transition-all" :class="[iframeClass, currentView === 'desktop' ? '' : 'shadow-lg']"
					@error="handleIframeError" @load="isIframeLoading = false" />
			</div>
			<MenuTemplatePicker v-else class="flex-1 min-h-0"
				:templates="webBlockTypes.data ?? []"
				:pickingCode="pickingTemplateCode"
				:errorMessage="errorMessage"
				@pick="pickTemplate" />
		</section>
	</div>
</template>
