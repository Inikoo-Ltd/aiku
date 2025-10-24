<script setup lang="ts">
import { ref, IframeHTMLAttributes, watch, provide, inject, computed } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import Publish from "@/Components/Publish.vue"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { Head, Link } from "@inertiajs/vue3"
import ScreenView from "@/Components/ScreenView.vue"
import { setIframeView } from "@/Composables/Workshop"
import ProgressSpinner from "primevue/progressspinner"
import { routeType } from "@/types/route"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import SideMenuWorkshop from "./SideMenuWorkshop.vue"
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
import EmptyState from "@/Components/Utils/EmptyState.vue"
import { trans } from "laravel-vue-i18n"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { get, set } from "lodash"
import Toggle from "@/Components/Pure/Toggle.vue"
import InformationIcon from "@/Components/Utils/InformationIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

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
	webBlockTypes: Object
	domain: string
	shop_type: string  // 'fulfilment' | 'dropshipping' | 'b2b'
}>()

const Navigation = ref(props.data.menu)
const isLoading = ref(false)
const status = ref(props.status)
const comment = ref("")
const isIframeLoading = ref(true)
const iframeClass = ref("w-full h-full")
const _iframe = ref<IframeHTMLAttributes | null>(null)
const iframeSrc = ref(route("grp.websites.header.preview", [route().params["website"]]))
const layout = inject('layout', layoutStructure)

const onPublish = async (action: routeType, popover: Funcition) => {
	try {
		// Ensure action is defined and has necessary properties
		if (!action || !action.method || !action.name || !action.parameters) {
			throw new Error("Invalid action parameters")
		}

		isLoading.value = true

		// Make sure route and axios are defined and used correctly
		const response = await axios[action.method](route(action.name, action.parameters), {
			comment: comment.value,
			layout: { ...Navigation.value, status: status.value },
		})
		popover.close()
	} catch (error) {
		// Ensure the error is logged properly
		console.error("Error:", error)

		// Ensure the error notification is user-friendly
		const errorMessage =
			error.response?.data?.message || error.message || "Unknown error occurred"
		notify({
			title: "Something went wrong.",
			text: errorMessage,
			type: "error",
		})
	} finally {
		// Ensure loading state is updated
		isLoading.value = false
	}
}

const sendToIframe = (data: any) => {
	console.log(data)
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


// Section: Side workshop
const urlToSidebar = computed(() => {
	return route('grp.org.shops.show.web.websites.workshop.sidebar', {
		organisation: layout.currentParams?.organisation || 'x',
		shop: layout.currentParams?.shop || 'x',
		website: layout.currentParams?.website || 'x',
	})
})

let controller: AbortController | null = null
const loadingAutoSave = ref(false)
const autoSave = async (value: any) => {
	if (controller) {
		controller.abort()
	}

	controller = new AbortController()
	loadingAutoSave.value = true

	try {
		const response = await axios.patch(
			route(props.autosaveRoute.name, props.autosaveRoute.parameters),
			{ layout: value },
			{ signal: controller.signal }
		)
		sendToIframe({ key: "reload", value: {} })
	} catch (error: any) {
		if (
			axios.isCancel(error) ||
			error.name === "CanceledError" ||
			error.message === "canceled"
		) {
			console.log("Autosave request cancelled")
			return
		}

		notify({
			title: "Something went wrong.",
			text: error.message,
			type: "error",
		})
	} finally {
		if (controller && !controller.signal.aborted) {
			loadingAutoSave.value = false
			controller = null
		}
	}
}

</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #button-publish="{ action }">
			<Publish :isLoading="isLoading" :is_dirty="true" v-model="comment"
				@onPublish="(popover) => onPublish(action.route, popover)" />
		</template>
		<template #afterTitle>
			<LoadingIcon v-if="loadingAutoSave" v-tooltip="trans('Saving..')" />
		</template>
	</PageHeading>

	<div class="h-[85vh] grid grid-cols-12 gap-4 p-3">
		<!-- SIDEBAR -->
		<div class="col-span-3 bg-white rounded-xl shadow-md p-4 overflow-y-auto border">
			<!-- Section: is follow Sidebar -->
			<div v-if="shop_type !== 'fulfilment'" class="border-dashed border-b border-gray-300 pb-6 mb-6">
				<div class="flex justify-between mt-4 ">
					<div>
						{{ trans("Is follow sidebar navigation?") }}
						<InformationIcon :information="trans('The data will be same like Sidebar')" />
					</div>

					<Toggle
						:modelValue="get(data, ['menu', 'data', 'fieldValue', 'setting_on_sidebar', 'is_follow'], false)"
						@update:modelValue="(value) => { set(data, ['menu', 'data', 'fieldValue', 'setting_on_sidebar', 'is_follow'], value), autoSave(data?.menu) }" />
				</div>

				<Link :href="urlToSidebar" class="text-xs underline hover:text-blue-500 cursor-pointer mt-2">
				{{ trans("Open Sidebar workhop") }}
				<FontAwesomeIcon icon="fal fa-external-link-alt" class="" fixed-width aria-hidden="true" />
				</Link>
			</div>

			<!-- Side workshop -->
			<SideMenuWorkshop :data="data?.menu" :webBlockTypes="webBlockTypes" @auto-save="autoSave"
				@sendToIframe="sendToIframe" />
		</div>

		<!-- PREVIEW SECTION -->
		<div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-hidden border">
			<!-- Controls -->
			<div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b">
				<ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" />
			</div>

			<!-- Iframe Preview -->
			<div v-if="data.menu?.code" class="relative flex-1 overflow-hidden">
				<div v-if="isIframeLoading" class="loading-overlay">
					<ProgressSpinner />
				</div>
				<iframe :src="iframeSrc" :title="props.title" :class="[iframeClass, isIframeLoading ? 'hidden' : '']"
					@error="handleIframeError" @load="isIframeLoading = false" ref="_iframe" />
			</div>
			<div v-else>
				<EmptyState />
			</div>
		</div>
	</div>
</template>


<style scoped lang="scss">
:deep(.loading-overlay) {
	position: absolute;
	inset: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	background-color: rgba(255, 255, 255, 0.85);
	z-index: 40;
	backdrop-filter: blur(2px);
}

:deep(.spinner) {
	border: 4px solid rgba(255, 255, 255, 0.3);
	border-top: 4px solid #6366f1; // Tailwind's indigo-500
	border-radius: 50%;
	width: 40px;
	height: 40px;
	animation: spin 1s linear infinite;
}

@keyframes spin {
	0% {
		transform: rotate(0deg);
	}

	100% {
		transform: rotate(360deg);
	}
}
</style>
