<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, onMounted, IframeHTMLAttributes, provide, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import axios from "axios"
import Publish from "@/Components/Publish.vue"
import { notify } from "@kyvg/vue3-notification"
import ScreenView from "@/Components/ScreenView.vue"
import WebpageSideEditor from "@/Components/Workshop/WebpageSideEditor.vue"
import { setIframeView } from "@/Composables/Workshop"
import { trans } from "laravel-vue-i18n"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import ButtonPreviewLogin from "@/Components/Workshop/Tools/ButtonPreviewLogin.vue"
import { Root, Daum } from "@/types/webBlockTypes"
import { Root as RootWebpage } from "@/types/webpageTypes"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"

import { faBrowser, faDraftingCompass, faRectangleWide, faStars, faBars, faExternalLink, faBoothCurtain, } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"

import { routeType } from "@/types/route"
import { faLowVision } from "@far"

library.add(faBrowser, faDraftingCompass, faRectangleWide, faStars, faBars, faLowVision)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	webpage: RootWebpage
	webBlockTypes: Root
}>()

provide('isInWorkshop', true)


const data = ref(props.webpage)
const iframeClass = ref("w-full h-full")
const isIframeLoading = ref(true)
const isPreviewLoggedIn = ref(false)
const isModalBlockList = ref(false)
const sendToIframe = (data: any) => { _iframe.value?.contentWindow.postMessage(data, '*') }

/* const socketConnectionWebpage = props.webpage ? socketWeblock(props.webpage.slug) : null */
const _iframe = ref<IframeHTMLAttributes | null>(null)
const addBlockCancelToken = ref<Function | null>(null)
const orderBlockCancelToken = ref<Function | null>(null)
const deleteBlockCancelToken = ref<Function | null>(null)
const addBlockParentIndex = ref(0)

const openedBlockSideEditor = ref<number | null>(null)
provide('openedBlockSideEditor', openedBlockSideEditor)
const openedChildSideEditor = ref<number | null>(null)
provide('openedChildSideEditor', openedChildSideEditor)

// Method: Add block
const isAddBlockLoading = ref<string | null>(null)
	const addNewBlock = async ({block, type}) => {
	if (addBlockCancelToken.value) addBlockCancelToken.value()
    let position = null
	if(type == 'before') {
		position = addBlockParentIndex.value
	}else if(type == 'after'){
		position = addBlockParentIndex.value + 1
	}
	router.post(
		route(props.webpage.add_web_block_route.name, props.webpage.add_web_block_route.parameters),
		{ web_block_type_id: block.id, position :position  },
		{
			onStart: () => (isAddBlockLoading.value = "addBlock" + block.id),
			onFinish: () => {
				addBlockCancelToken.value = null
				isAddBlockLoading.value = null
				addBlockParentIndex.value = 0
			},
			onCancelToken: (cancelToken) => {
				addBlockCancelToken.value = cancelToken.cancel
			},
			onSuccess: (e) => {
				data.value = e.props.webpage
				sendToIframe({ key: 'reload', value: {} })
			},
			onError: (error) => {
				console.log(error)
				notify({
					title: trans("Something went wrong"),
					text: error.message,
					type: "error",
				})
			},
		}
	)
}

// Method: save workshop
const isLoadingblock = ref<string | null>(null)
const isSavingBlock = ref(false)
const _WebpageSideEditor = ref(null)
const cancelTokens = ref<Record<string, Function>>({}) // A map to store cancel tokens by block id
// Object to store individual debounce timers for each block
const debounceTimers = ref({})
const openWebsite = () => {
	window.open('https://' + props.webpage.domain + '/' + props.webpage.url, "_blank")
}
const debounceSaveWorkshop = (block) => {
	console.log('debounceSaveWorkshop', block.web_block.layout);

	// If the debounce timer exists, cancel it
	if (debounceTimers.value[block.id]) {
		clearTimeout(debounceTimers.value[block.id])
	}

	// Set a new debounce timer for this block
	debounceTimers.value[block.id] = setTimeout(() => {
		router.patch(
			route(props.webpage.update_model_has_web_blocks_route.name, {
				modelHasWebBlocks: block.id,
			}),
			{
				layout: block.web_block.layout,
				show_logged_in: block.visibility.in,
				show_logged_out: block.visibility.out,
				show: block.show,
			},
			{
				onStart: () => {
					console.log('========== start save ', block.id, block.type)
					isLoadingblock.value = block.id
					isSavingBlock.value = true
				},
				onCancelToken: (cclToken) => {
					cancelTokens.value[block.id] = cclToken.cancel
				},
				onFinish: () => {
					isLoadingblock.value = null
					isSavingBlock.value = false
					delete cancelTokens.value[block.id]
				},
				onSuccess: (e) => {
					data.value = e.props.webpage
					sendToIframe({ key: 'reload', value: {} })
				},
				onError: (error) => {
					notify({
						title: trans("Something went wrong"),
						text: error.message,
						type: "error",
					})
				},
				preserveScroll: true,
			}
		)
	}, 1500) // Debounce time of 1500ms for each block
}

const onSaveWorkshop = (block) => {
	// Cancel the ongoing save for the specific block if it's in progress
	if (cancelTokens.value[block.id]) {
		cancelTokens.value[block.id]()
	}

	// Call debounceSaveWorkshop to handle save for this block
	debounceSaveWorkshop(block)
}

const onSaveWorkshopFromId = (blockId: number, from?: string) => {
	// Debug purpose
	if (from) {
		console.log('onSaveWorkshopFromId from:', from)
	}

	if (!blockId) return

	if (cancelTokens.value[blockId]) {
		cancelTokens.value[blockId]()
	}

	const block = data.value.layout.web_blocks.find((block) => block.id === blockId)
	if (block) {
		debounceSaveWorkshop(block)
	}
}

provide('onSaveWorkshopFromId', onSaveWorkshopFromId)
provide('onSaveWorkshop', onSaveWorkshop)

const sendOrderBlock = async (block: Object) => {
	if (orderBlockCancelToken.value) orderBlockCancelToken.value()
	router.post(
		route(props.webpage.reorder_web_blocks_route.name, props.webpage.reorder_web_blocks_route.parameters),
		{ positions: block },
		{
			onStart: () => { },
			onFinish: () => {
				isLoadingblock.value = null
				orderBlockCancelToken.value = null
			},
			onCancelToken: (cancelToken) => {
				orderBlockCancelToken.value = cancelToken.cancel
			},
			onSuccess: (e) => {
				data.value = e.props.webpage
				sendToIframe({ key: 'reload', value: {} })
			},
			onError: (error) => {
				notify({
					title: trans('Something went wrong'),
					text: error.message,
					type: 'error',
				})
			}
		}
	)
}

// Method: Delete Block
const isLoadingDeleteBlock = ref<number | null>(null)
const sendDeleteBlock = async (block: Daum) => {
	if (deleteBlockCancelToken.value) deleteBlockCancelToken.value()
	router.delete(
		route(props.webpage.delete_model_has_web_blocks_route.name, { modelHasWebBlocks: block.id }),
		{
			onStart: () => isLoadingDeleteBlock.value = block.id,
			onFinish: () => {
				isLoadingDeleteBlock.value = null
				orderBlockCancelToken.value = null
			},
			onCancelToken: (cancelToken) => {
				deleteBlockCancelToken.value = cancelToken.cancel
			},
			onSuccess: (e) => {
				data.value = e.props.webpage
				sendToIframe({ key: 'reload', value: {} })
			},
			onError: (error) => {
				notify({
					title: trans('Something went wrong'),
					text: error.message,
					type: 'error',
				})
			}
		}
	)
}

// Method: Publish
const comment = ref("")
const isLoadingPublish = ref(false)
const onPublish = async (action: routeType, popover: { close: Function, open: Function }) => {
	try {
		// Ensure action is defined and has necessary properties
		if (!action || !action.method || !action.name || !action.parameters) {
			throw new Error("Invalid action parameters")
		}

		isLoadingPublish.value = true

		// Make sure route and axios are defined and used correctly
		const response = await axios[action.method](route(action.name, action.parameters), {
			comment: comment.value,
			publishLayout: { blocks: data.value.layout },
		})

		if (response.status === 200) {
			comment.value = ""
			notify({
				title: trans('Published!'),
				text: trans('Webpage data has been published successfully'),
				type: 'success',
			})
		}
		popover.close()
	} catch (error) {
		// Ensure the error is logged properly
		console.error("Error:", error)
		const errorMessage =
			error.response?.data?.message || error.message || "Unknown error occurred"
		notify({
			title: trans("Something went wrong"),
			text: errorMessage,
			type: "error",
		})
	} finally {
		isLoadingPublish.value = false
	}
}

// const handleIframeError = () => {
// 	console.error("Failed to load iframe content.")
// }

const iframeSrc =
	route("grp.websites.webpage.preview", [
		route().params["website"],
		route().params["webpage"],
		{
			organisation: route().params["organisation"],
			shop: route().params["shop"],
			fulfilment: route().params["fulfilment"]
		},
	]
	)

const previewSrc =
	route("grp.websites.preview", [
		route().params["website"],
		route().params["webpage"],
		{
			organisation: route().params["organisation"],
			shop: route().params["shop"],
			fulfilment: route().params["fulfilment"]
		},
	]
	)

const openFullScreenPreview = () => {
	/* window.open(previewSrc + '&isInWorkshop=true', "_blank") */
	const url = new URL(previewSrc, window.location.origin);
	url.searchParams.set('isInWorkshop', 'true');
	url.searchParams.set('mode', 'iris');
	window.open(url.toString(), '_blank');
}

const setHideBlock = (block: Daum) => {
	block.show = !block.show
	onSaveWorkshop(block)
}


onMounted(() => {
	// Listen emit from Iframe
	window.addEventListener("message", (event) => {
		if (event.origin !== window.location.origin) return;
		const { data } = event;

		if (data.key === 'autosave') {
			onSaveWorkshop(data.value)
		} else if (data.key === 'activeBlock') {
			openedBlockSideEditor.value = data.value
		} else if (data.key === 'activeChildBlock') {
			openedChildSideEditor.value = data.value
		}else if (data.key === 'addBlock') {
			if (_WebpageSideEditor.value) {
				isModalBlockList.value = true;
				addBlockParentIndex.value =  data.value.parentIndex
				_WebpageSideEditor.value.addType = data.value.type;
			}
		}else if (data.key === 'deleteBlock') {
			sendDeleteBlock(data.value)
		}
	})
})


watch(openedBlockSideEditor, (newValue) => {
	sendToIframe({ key: 'activeBlock', value: newValue })
})


</script>

<template>

	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #button-publish="{ action }">
			<Publish :isLoading="isLoadingPublish" :is_dirty="data.is_dirty" v-model="comment"
				@onPublish="(popover) => onPublish(action.route, popover)" />
		</template>

		<template #afterTitle v-if="isSavingBlock">
			<LoadingIcon v-tooltip="trans('Saving..')" />
		</template>
		<template #other>
			<div class=" px-2 cursor-pointer" v-tooltip="'go to website'" @click="openWebsite">
				<FontAwesomeIcon :icon="faExternalLink" aria-hidden="true" size="xl" />
			</div>
		</template>
	</PageHeading>

	<div class="flex gap-x-2">
		<!-- Section: Side editor -->
		<div class="hidden lg:flex lg:flex-col border-2 bg-gray-200 pl-3 py-1">
			<WebpageSideEditor v-model="isModalBlockList" :isLoadingblock :isLoadingDeleteBlock :isAddBlockLoading
				:webpage="data" :webBlockTypes="webBlockTypes" @update="onSaveWorkshop" @delete="sendDeleteBlock"
				@add="addNewBlock" @order="sendOrderBlock" @setVisible="setHideBlock" ref="_WebpageSideEditor"/>
		</div>

		<!-- Section: Preview -->
		<div class="h-[calc(100vh-180px)] w-full max-w-full flex flex-col bg-gray-200 overflow-x-auto">
			<div class="flex justify-between">
				<!-- Section: Screenview -->
				<div class="flex">
					<ScreenView @screenView="(e) => iframeClass = setIframeView(e)" />
					<div class="py-1 px-2 cursor-pointer" v-tooltip="'Preview'" @click="openFullScreenPreview">
						<FontAwesomeIcon :icon="faLowVision" fixed-width aria-hidden="true" />
					</div>
				</div>

				<!-- Tools: login-logout, edit-preview -->
				<div class="flex gap-3 items-center px-4">
					<ButtonPreviewLogin v-model="isPreviewLoggedIn"
						@update:model-value="(e) => sendToIframe({ key: 'isPreviewLoggedIn', value: e })" />

				</div>
			</div>

			<div class="border-2 h-full w-full relative">
				<div class="h-full w-full bg-white overflow-auto">
					<!-- Loading Icon di Tengah -->
					<div v-if="isIframeLoading"
						class="absolute inset-0 flex items-center justify-center bg-white">
						<LoadingIcon class="w-24 h-24 text-6xl" />
					</div>

					<!-- Iframe -->
					<iframe ref="_iframe" :src="iframeSrc" :title="props.title"
						:class="[iframeClass, isIframeLoading ? 'hidden' : '']"
						@load="isIframeLoading = false" />
				</div>
			</div>


		</div>
	</div>
</template>

<style lang="scss" scoped>
:deep(.component-iseditable) {
	@apply border border-transparent border-dashed cursor-pointer;
}

iframe {
	height: 100%;
	transition: width 0.3s ease;
}

:deep(.loading-overlay) {
	position: block;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
	background: rgba(255, 255, 255, 0.8);
	z-index: 1000;
}

:deep(.spinner) {
	border: 4px solid rgba(255, 255, 255, 0.3);
	border-radius: 50%;
	border-top: 4px solid #3498db;
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
