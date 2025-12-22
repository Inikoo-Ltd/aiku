<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { computed, reactive, ref, watch, onMounted, onBeforeUnmount, inject } from "vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"
import RetinaTablePortfoliosManual from "@/Components/Tables/Retina/RetinaTablePortfoliosManual.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Modal from "@/Components/Utils/Modal.vue"
import AddPortfoliosWithUpload from "@/Components/Dropshipping/AddPortfoliosWithUpload.vue"
import AddPortfolios from "@/Components/Dropshipping/AddPortfolios.vue"
import { InputNumber, InputText, Message, Popover } from "primevue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSyncAlt, faHandPointer, faBan } from "@fas"
import { useFormatTime, useTimeCountdown } from "@/Composables/useFormatTime"
import Icon from "@/Components/Icon.vue"
import LoadingText from "@/Components/Utils/LoadingText.vue"
import { differenceInHours, differenceInMinutes, differenceInSeconds, addDays } from "date-fns"
import { layoutStructure } from "@/Composables/useLayoutStructure"

import {
	faBracketsCurly,
	faPawClaws,
	faFileExcel,
	faImage,
	faArrowLeft,
	faArrowRight,
	faUpload,
	faBox,
	faEllipsisV,
	faDownload,
} from "@fal"
import { faCheck } from "@fas"
import axios from "axios"
import { Table as TableTS } from "@/types/Table"
import { CustomerSalesChannel } from "@/types/customer-sales-channel"
import RetinaTablePortfoliosPlatform from "@/Components/Tables/Retina/RetinaTablePortfoliosPlatform.vue"
import RetinaTablePortfoliosShopify from "@/Components/Tables/Retina/RetinaTablePortfoliosShopify.vue"
import { ulid } from "ulid"
import PlatformWarningNotConnected from "@/Components/Retina/Platform/PlatformWarningNotConnected.vue"
import PlatformWarningNotConnectedShopify from "@/Components/Retina/Platform/PlatformWarningNotConnectedShopify.vue"
import { useTruncate } from "@/Composables/useTruncate"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { useTabChange } from "@/Composables/tab-change"
import TableRetinaPlatformPortfolioLogs from "@/Components/Tables/Retina/TableRetinaPlatformPortfolioLogs.vue"
import { useEchoRetinaPersonal } from "@/Stores/echo-retina-personal"
import PureProgressBar from "@/Components/PureProgressBar.vue"
import { set } from "lodash-es"
import Editor2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { EditorContent } from "@tiptap/vue-3"

library.add(
	faFileExcel,
	faCheck,
	faBracketsCurly,
	faSyncAlt,
	faHandPointer,
	faPawClaws,
	faImage,
	faSyncAlt,
	faBox,
	faArrowLeft,
	faArrowRight,
	faUpload
)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	tabs: TSTabs
	download_route: any
	grouped_portfolios: any
	is_closed: boolean
	content?: {
		portfolio_empty?: {
			title?: string
			description?: string
			separation?: string
			sync_button?: string
			add_button?: string
		}
	}
	products: TableTS
	logs: {}
	routes: {
		batch_upload: routeType
		batch_all: routeType
		match_match: routeType
		syncAllRoute: routeType
		batch_sync: routeType
		duplicate: routeType
		addPortfolioRoute: routeType
		bulk_upload: routeType
		bulk_unlink: routeType
		itemRoute: routeType
		updatePortfolioRoute: routeType
		batchDeletePortfolioRoute: routeType
		clonePortfolioRoute: routeType
	}
	platform_user_id: number
	step: {
		current: number
	}
	platform_data: {
		id: number
		code: string // 'manual' | 'shopify' | 'ebay' | 'amazon'
		name: string
		type: string
	}
	is_platform_connected: boolean
	customer_sales_channel: CustomerSalesChannel
	channels: object
	count_product_not_synced: number

	// inactive: {}
	product_count: number
	download_portfolio_customer_sales_channel_url: string | null
	last_created_at_download_portfolio_customer_sales_channel: string | null
	ebay_warehouse_policy_msg: {
		show_msg: boolean
		cust_country: string
	}
}>()

const step = ref(props.step)
const isPlatformManual = computed(() => props.platform_data.type === "manual")
const isOpenModalPortfolios = ref(false)
const isOpenModalDownloadImages = ref(false)
const isOpenModalSuspended = ref(false)
const isTestConnectionSuccess = ref(false)
const isOpenModalCloneProgress = ref(false)
const cloneProgressData = ref({
	data: {
		number_success: 0,
		number_fails: 0,
	},
	done: 0,
	total: 0,
})
const cloneSourceChannelName = ref("")
const isLoadingUpload = ref(false)
const isLoadingClone = ref(false)
const selectedData = reactive({
	products: [] as number[],
})

const onUploadToShopify = () => {
	if (!props.routes.bulk_upload?.name) {
		notify({
			title: trans("No route defined"),
			type: "error",
		})
		return
	}

	router.post(
		route(props.routes.bulk_upload.name, props.routes.bulk_upload.parameters),
		{
			portfolios: selectedData.products,
		},
		{
			preserveScroll: true,
			onBefore: () => (isLoadingUpload.value = true),
			onError: (error) => {
				notify({
					title: trans("Something went wrong"),
					text: "",
					type: "error",
				})
			},
			onSuccess: () => {
				selectedData.products = []
				router.reload({ only: ["pageHead", "products"] })
				notify({
					title: trans("Success!"),
					text: `Portfolios successfully uploaded to ${props.platform_data.name}`,
					type: "success",
				})
				props.step.current = 1
			},
			onFinish: () => {
				isLoadingUpload.value = false
			},
		}
	)
}

const downloadUrl = (type: string, addParams: string = "") => {
	if (props.download_route?.[type]?.name) {
		return route(props.download_route[type].name, {
			...props.download_route[type].parameters,
			...{ ids: addParams },
		})
	} else {
		return ""
	}
}

const _popover = ref()
const _clone_popover = ref()

// Method: Platform reconnect
const onClickReconnect = async (customerSalesChannel: CustomerSalesChannel) => {
	console.log("customerSalesChannel", customerSalesChannel)
	try {
		const response = await axios[customerSalesChannel.reconnect_route.method || "get"](
			route(
				customerSalesChannel.reconnect_route.name,
				customerSalesChannel.reconnect_route.parameters
			)
		)
		console.log("1111 response", response)
		if (response.status !== 200) {
			throw new Error("Something went wrong. Try again later.")
		} else {
			window.open(response.data, "_blank")
		}
	} catch (error: any) {
		notify({
			title: "Something went wrong",
			text: error.message || "Please try again later.",
			type: "error",
		})
	}
}

// Method: Clone manual portfolios
const cloneSimulationInterval = ref<number | null>(null)

const onCloneManualPortfolio = async (
	sourceCustomerSalesChannelId: string | number,
	sourceChannelName?: string,
	totalPortfolios?: number
) => {
	cloneSourceChannelName.value = sourceChannelName || ""

	const total = totalPortfolios || 0
	cloneProgressData.value = {
		data: {
			number_success: 0,
			number_fails: 0,
		},
		done: 0,
		total: total,
	}

	router.post(
		route(props.routes.clonePortfolioRoute.name, {
			...props.routes.clonePortfolioRoute.parameters,
			customerSalesChannel: sourceCustomerSalesChannelId,
		}),
		{},
		{
			onBefore: () => {
				isLoadingClone.value = true
				isOpenModalCloneProgress.value = true
				_clone_popover.value?.hide()
			},
			onError: (error) => {
				notify({
					title: trans("Something went wrong"),
					text: "",
					type: "error",
				})
				isOpenModalCloneProgress.value = false
				if (cloneSimulationInterval.value) {
					clearInterval(cloneSimulationInterval.value)
					cloneSimulationInterval.value = null
				}
			},
			onSuccess: () => {
				selectedData.products = []
				notify({
					title: trans("Success!"),
					text: trans(`Portfolios cloning started in background.`),
					type: "success",
				})
				props.step.current = 1

				setTimeout(() => {
					if (cloneProgressData.value.done === 0 && cloneProgressData.value.total > 0) {
						startProgressSimulation()
					}
				}, 2000)
			},
			onFinish: () => {
				isLoadingClone.value = false
			},
		}
	)
}

const startProgressSimulation = () => {
	if (cloneSimulationInterval.value) return

	const total = cloneProgressData.value.total
	if (total === 0) return

	const intervalMs = Math.max(100, Math.min(500, 10000 / total))

	cloneSimulationInterval.value = window.setInterval(() => {
		if (cloneProgressData.value.done >= cloneProgressData.value.total) {
			if (cloneSimulationInterval.value) {
				clearInterval(cloneSimulationInterval.value)
				cloneSimulationInterval.value = null
			}

			setTimeout(() => {
				isOpenModalCloneProgress.value = false
				router.reload({ only: ["pageHead", "products"] })
				notify({
					title: trans("Cloning Complete!"),
					text: trans(":total products cloned.", {
						total: cloneProgressData.value.total,
					}),
					type: "success",
				})
			}, 1000)
			return
		}

		cloneProgressData.value = {
			...cloneProgressData.value,
			done: cloneProgressData.value.done + 1,
			data: {
				...cloneProgressData.value.data,
				number_success: cloneProgressData.value.data.number_success + 1,
			},
		}
	}, intervalMs)
}

onBeforeUnmount(() => {
	if (cloneSimulationInterval.value) {
		clearInterval(cloneSimulationInterval.value)
	}
})

const echoRetinaPersonal = useEchoRetinaPersonal()

watch(
	() => echoRetinaPersonal.progressBars?.ClonePortfolio,
	(newProgress) => {
		if (!newProgress) return

		const progressForChannel = newProgress[props.customer_sales_channel.id]
		if (progressForChannel) {
			cloneProgressData.value = {
				data: progressForChannel.data,
				done: progressForChannel.done,
				total: progressForChannel.total,
			}

			if (
				progressForChannel.done >= progressForChannel.total &&
				progressForChannel.total > 0
			) {
				setTimeout(() => {
					isOpenModalCloneProgress.value = false
					router.reload({ only: ["pageHead", "products"] })
					notify({
						title: trans("Cloning Complete!"),
						text: trans(":success products cloned successfully, :fails failed.", {
							success: progressForChannel.data.number_success,
							fails: progressForChannel.data.number_fails,
						}),
						type: progressForChannel.data.number_fails > 0 ? "warn" : "success",
					})
				}, 1500)
			}
		}
	},
	{ deep: true }
)

// Section: Bulk upload to Shopify/Ebay/etc
const isLoadingBulkDeleteUpload = ref(false)
// const selectedPortfoliosToSync = ref([])
const bulkUpload = () => {
	router[props.routes.bulk_upload.method || "post"](
		route(props.routes.bulk_upload.name, props.routes.bulk_upload.parameters),
		{
			portfolios: null,
		},
		{
			preserveScroll: true,
			// onBefore: () => isLoadingUpload.value = true,
			onStart: () => {
				isLoadingBulkDeleteUpload.value = true
			},
			onSuccess: () => {
				// set(progressToUploadToShopify.value, [product.id], 'loading')
			},
			onFinish: () => {
				isLoadingBulkDeleteUpload.value = false
			},
			onError: (error) => {
				console.log("Error during bulk upload:", error)
				notify({
					title: trans("Something went wrong"),
					text: error.message || trans("An error occurred while uploading portfolios"),
					type: "error",
				})
			},
		}
	)
}

const progressToUploadToShopify = ref<{ [key: number]: string }>({})
const selectedProducts = ref<number[]>([])

const loadingAction = ref([])
const modalAddproductBluk = ref(false)
const modalMatchproductBluk = ref(false)
const modalBulkEditPrice = ref(false)
const selectedEditProduct = ref([])

const openBulkEditModal = () => {
	modalBulkEditPrice.value = true
}

const progessbar = ref({
	data: {
		number_success: 0,
		number_fails: 0,
	},
	done: true,
	total: selectedProducts.value.length,
})

const debReloadPage = () => {
	router.reload({
		except: ["auth", "breadcrumbs", "flash", "layout", "localeData", "pageHead", "ziggy"],
	})
}

const onSuccessEditCheckmark = (key) => {
	if (key == "Match With Existing Product") modalMatchproductBluk.value = true
	else modalAddproductBluk.value = true
	selectedProducts.value = []

	progessbar.value = { ...progessbar.value, done: false, total: selectedProducts.value.length }
}

const onFailedEditCheckmark = (error: any) => {
	notify({
		title: "Something went wrong.",
		text: error?.response?.data?.products || "An error occurred.",
		type: "error",
	})
}

const submitPortfolioAction = async (action: any) => {
	loadingAction.value.push(action.label)
	try {
		const method = action?.method?.toLowerCase() || "get"
		const url = route(action.name, action?.parameters)
		const data = { portfolios: selectedProducts.value }

		const response = await axios({
			method,
			url,
			data: method === "get" ? undefined : data,
			params: method === "get" ? data : undefined,
		})

		debReloadPage()
		onSuccessEditCheckmark(action.label)
	} catch (error: any) {
		onFailedEditCheckmark(error)
	} finally {
		loadingAction.value = []
	}
}

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const key = ulid()

// ========= handle upload to R2 and get download link
const codeString = ref<string | null>(null)
const isSocketActive = ref(false)

let channel: any = null
const isModalReadyDownloadImages = ref(false)
const isModalDownloadImages = ref(false)
const stateDownloadImagesReady = ref<string | null>(null)
const linkDownloadImages = ref(null)
const initSocketListener = () => {
	if (!window.Echo || !codeString.value) return

	isSocketActive.value = true
	stateDownloadImagesReady.value = "loading"
	isModalDownloadImages.value = true

	const socketEvent = `upload-portfolio-to-r2.${codeString.value}`
	const socketAction = ".upload-portfolio-to-r2"

	if (channel) {
		channel.stopListening(socketAction)
	}

	channel = window.Echo.private(socketEvent).listen(socketAction, (eventData: any) => {
		stateDownloadImagesReady.value = "success"
		linkDownloadImages.value = "https://" + eventData.download_url
		isModalDownloadImages.value = false
		isModalReadyDownloadImages.value = true

		notify({
			title: "Your download images is ready",
			text: "Click download button to get your files.",
			type: "success",
		})

		sessionStorage.removeItem("download_code")
		codeString.value = null
		// stop listening after this event
		channel.stopListening(socketAction)
		isSocketActive.value = false
	})
}

watch(
	() => props.download_portfolio_customer_sales_channel_url,
	() => {
		if (props.download_portfolio_customer_sales_channel_url) {
			sessionStorage.removeItem("download_code")
			linkDownloadImages.value =
				"https://" + props.download_portfolio_customer_sales_channel_url
		} else {
			linkDownloadImages.value = null
		}
	},
	{
		immediate: true,
	}
)

// === STORAGE & SOCKET SYNC ===
onMounted(() => {
	const storedCode = sessionStorage.getItem("download_code")
	if (storedCode) {
		codeString.value = storedCode
		initSocketListener()
	}

	watch(codeString, (newCode) => {
		if (newCode) {
			sessionStorage.setItem("download_code", newCode)
			initSocketListener()
		}
	})

	onBeforeUnmount(() => {
		if (channel) channel.stopListening(".upload-portfolio-to-r2")
		linkDownloadImages.value = null
	})
})

const handleDownloadClick = async (type: string, event: Event) => {
	event.preventDefault()
	const url = downloadUrl(type)

	if (!url) {
		console.error("No valid URL found for download")
		return
	}
	// Convert URL to string if it's a Router object
	const urlString = typeof url === "string" ? url : url.toString()

	try {
		const response = await axios.get(urlString, {})
		if (response.status !== 200) {
			notify({
				title: "Something went wrong.",
				text: "An error occurred.",
				type: "error",
			})
			return
		}

		if (response.data) {
			codeString.value = response.data
			initSocketListener()
		}
	} catch (error) {
		console.error("Download failed:", error)
		notify({
			title: "Something went wrong.",
			text: "An error occurred.",
			type: "error",
		})
	}
}

// Time countdown for download link expiration
const timeCountdown = ref("")
const countdownInterval = ref<number | null>(null)

// Set countdown for download link expiration
const setCountdown = (expiryDate: Date) => {
	clearInterval(countdownInterval.value!)
	console.log("Setting countdown for:", expiryDate, "Current time:", new Date())

	// Initial update
	timeCountdown.value = useTimeCountdown(expiryDate.toISOString(), { human: true })

	// Update every second
	countdownInterval.value = window.setInterval(() => {
		const countdown = useTimeCountdown(expiryDate.toISOString(), { human: true })
		timeCountdown.value = countdown

		// Clear interval when countdown is done
		if (!countdown) {
			if (countdownInterval.value !== null) {
				clearInterval(countdownInterval.value)
			}
			timeCountdown.value = "Expired"
		}
	}, 1000)
}

// Update the time left
const updateTimeLeft = () => {
	if (!props.last_created_at_download_portfolio_customer_sales_channel) {
		timeCountdown.value = "Download link is ready"
		return
	}

	const expiryDate = addDays(
		new Date(props.last_created_at_download_portfolio_customer_sales_channel),
		1
	)
	const now = new Date()
	console.log("Now:", now, "Expiry:", expiryDate)

	if (now > expiryDate) {
		timeCountdown.value = "Expired"
		return
	}

	setCountdown(expiryDate)
}

const bulkUpdatePriceData = ref({})

const calculateAdjustedPrice = (amount, type) => {
	bulkUpdatePriceData.value = {
		amount: amount,
		type: type
	};
}

const submitBulkEditPrice = async (type) => {
	loadingAction.value.push("bulk-edit")

	try {
		const url = route(`retina.models.portfolios.update_new_product_price.${type}`)
		const data = { items: selectedProducts.value, ...bulkUpdatePriceData.value }

		const response = await axios({
			method: "post",
			url,
			data: data
		})

		debReloadPage()
		onSuccessEditCheckmark("bulk-edit")
		bulkUpdatePriceData.value = {}
		modalBulkEditPrice.value = false

		notify({
			title: trans('Your edits was successfully submitted and still processed in background.'),
			text: trans('Please wait for a few minutes to update the product'),
			type: 'success',
		})

	} catch (error: any) {
		onFailedEditCheckmark(error)
	} finally {
		loadingAction.value = []
	}

	loadingAction.value = [];
}

// Watch for changes to the creation date
watch(() => props.last_created_at_download_portfolio_customer_sales_channel, updateTimeLeft, {
	immediate: true,
})

// Clean up interval when component is unmounted
onBeforeUnmount(() => {
	if (countdownInterval.value) {
		clearInterval(countdownInterval.value)
	}
})

const layout = inject("layout", layoutStructure)
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #button-upload-to-shopify="{ action }">
			<Button
				@click="onUploadToShopify()"
				:style="action.style"
				:label="action.label"
				:loading="isLoadingUpload"
				:disabled="!selectedData.products.length"
				v-tooltip="
					!selectedData.products.length
						? trans('Select at least one product to upload')
						: ''
				" />
		</template>

		<template v-if="props.product_count && !props.is_closed" #other>
			<div class="rounded-md">
				<a :href="downloadUrl('csv') as string" target="_blank" rel="noopener">
					<Button :icon="faDownload" label="CSV" type="tertiary" class="rounded-r-none" />
				</a>

				<a
					v-if="!linkDownloadImages"
					href="#"
					@click.prevent="!isSocketActive && handleDownloadClick('images', $event)">
					<Button
						:icon="faImage"
						type="tertiary"
						class="border-l-0 rounded-l-none"
						:disabled="isSocketActive">
						<template #label>
							<LoadingIcon v-if="stateDownloadImagesReady === 'loading'" />
							<span v-else>
								{{ trans("Images") }}
							</span>
						</template>
					</Button>
				</a>

				<VTooltip v-else class="w-fit inline">
					<a :href="linkDownloadImages" target="_blank" rel="noopener" download>
						<Button
							:icon="faDownload"
							:label="trans('Download images')"
							type="secondary"
							class="border-l-0 rounded-l-none"
							:disabled="isSocketActive">
						</Button>
					</a>
					<template #popper>
						<div class="text-xs tabular-nums">
							{{
								trans(":timeCountdown left before link expires", {
									timeCountdown: timeCountdown,
								})
							}}.
						</div>
					</template>
				</VTooltip>
			</div>

			<Button
				@click="() => (isOpenModalPortfolios = true)"
				:label="trans('Add products')"
				:icon="'fas fa-plus'"
				v-if="!customer_sales_channel.ban_stock_update_until" />

			<div class="rounded-md" v-if="channels?.data?.length">
				<!-- Section: Download button -->
				<Button
					@click="(e) => _clone_popover?.toggle(e)"
					v-tooltip="trans('Open another options')"
					:icon="faEllipsisV"
					xloading="!!isLoadingSpecificChannel.length"
					class="!px-2 h-full"
					type="tertiary"
					key=""
					v-if="!customer_sales_channel.ban_stock_update_until" />

				<Popover ref="_clone_popover">
					<div class="w-64 relative">
						<div class="text-sm mb-2">
							{{ trans("Clone portfolio from channel:") }}
						</div>

						<div
							v-for="(manual_channel, index) in channels?.data"
							:key="index"
							class="flex flex-col gap-y-2 mb-1.5">
							<Button
								:loading="isLoadingClone"
								@click="
									() =>
										onCloneManualPortfolio(
											manual_channel.id,
											manual_channel.name || manual_channel.slug,
											manual_channel.number_portfolios
										)
								"
								:label="
									(manual_channel.name || manual_channel.slug) +
									' (' +
									manual_channel.number_portfolios +
									')'
								"
								full
								:style="'tertiary'">
								<template #default="{ loading }">
									<div class="flex gap-x-2 justify-start items-center w-full">
										<LoadingIcon
											v-if="loading"
											class="h-5"
											v-tooltip="trans('Processing...')" />
										<img
											v-else
											:src="`/assets/channel_logo/${manual_channel.platform_code}.svg`"
											class="h-5"
											:alt="manual_channel.platform_code"
											v-tooltip="manual_channel.platform_name" />
										<div>
											{{
												useTruncate(
													manual_channel.name || manual_channel.slug,
													20
												) +
												" (" +
												manual_channel.number_portfolios +
												")"
											}}
										</div>
									</div>
								</template>
							</Button>
						</div>
					</div>
				</Popover>
			</div>
		</template>
	</PageHeading>
	<Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

	<div v-if="!is_platform_connected && !isPlatformManual" class="mb-10">
		<div v-if="platform_data.type === 'shopify'" class="px-2 md:px-6">
			<PlatformWarningNotConnectedShopify :customer_sales_channel="customer_sales_channel" />
		</div>

		<PlatformWarningNotConnected v-else :customer_sales_channel="customer_sales_channel" />
	</div>
	<div v-if="ebay_warehouse_policy_msg.show_msg" class="flex justify-between mt-5 m-4">
		<div class="w-full border-2 border-red-500 rounded-lg p-4 bg-red-50">
			<div class="flex flex-col sm:flex-row sm:items-start">
				<div class="flex items-center mb-2 sm:mb-0 sm:flex-shrink-0">
					<svg
						class="h-5 w-5 text-red-500 mr-2 sm:mr-0 sm:mt-0.5"
						fill="currentColor"
						viewBox="0 0 20 20">
						<path
							fill-rule="evenodd"
							d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
							clip-rule="evenodd"></path>
					</svg>
					<strong class="text-sm text-red-700 sm:hidden">{{
						trans("Important Notice:")
					}}</strong>
				</div>
				<div class="sm:ml-3">
					<p class="text-sm text-red-700">
						<strong class="hidden sm:inline">{{ trans("Important Notice:") }}</strong>
						{{ trans("We noticed your account is registered in") }}
						<strong> {{ ebay_warehouse_policy_msg.cust_country + "." }} </strong>
						{{
							trans(
								"In accordance to eBay’s Overseas Warehouse Block Policy, listings from this region may be blocked when the item is stored overseas."
							)
						}}
						<a
							href="https://export.ebay.com/en/fees-regulations-policies/ebay-policies/overseas-warehouse-block-policy-authorization-requirements-for-forward-deployed-inventory/"
							target="_blank"
							class="underline text-red-800 hover:text-red-900">
							[eBay Overseas Warehouse Block Policy]
						</a>
						<br />
						<br />
						{{
							trans(
								"If this happens, please contact eBay Support to request approval or further assistance:"
							)
						}}
						<a
							href="https://www.ebay.com/help/contact_us?id=4105&st=10"
							target="_blank"
							class="underline text-red-800 hover:text-red-900">
							eBay Customer Support
						</a>
					</p>
				</div>
			</div>
		</div>
	</div>
	<!-- Section: Alert if there is product not synced -->
	<Message
		v-if="
			is_platform_connected &&
			count_product_not_synced > 0 &&
			!isPlatformManual &&
			currentTab === 'products'
		"
		severity="warn"
		class="m-4">
		<div class="ml-2 font-normal flex flex-col items-center sm:flex-row justify-between w-full">
			<div>
				<FontAwesomeIcon
					icon="fad fa-exclamation-triangle"
					class="text-xl"
					fixed-width
					aria-hidden="true" />
				<div class="inline items-center gap-x-2">
					{{
						trans("You have :products products not synced yet", {
							products: `${count_product_not_synced}`,
						})
					}}
				</div>
			</div>

			<div class="w-full sm:w-fit h-fit space-x-2 flex justify-end">
				<ButtonWithLink
					v-if="routes.duplicate?.name"
					:routeTarget="routes.duplicate"
					v-tooltip="
						trans(
							'This will only create new products to the :platform that not exist in :platform',
							{ platform: props.platform_data.name }
						)
					"
					aclick="() => onClickReconnect(customer_sales_channel)"
					icon="far fa-plus"
					:label="trans('Create new')"
					type="tertiary" />

				<ButtonWithLink
					v-if="routes.batch_sync?.name"
					:routeTarget="routes.batch_sync"
					v-tooltip="
						trans(
							'This will only sync existing products to the :platform (will not create new)',
							{ platform: props.platform_data.name }
						)
					"
					icon="fas fa-sync-alt"
					:label="trans('Use existing')"
					type="tertiary" />

				<div
					v-if="selectedProducts.length > 0"
					class="space-x-2 border-r border-gray-400 pr-2">
					<Button
						v-if="selectedProducts.length > 0"
						v-tooltip="
							trans('Edit Product :platform', { platform: props.platform_data?.name })
						"
						:type="'tertiary'"
						:label="trans('Edit Price (:_count)', { _count: selectedProducts?.length })"
						:loading="loadingAction.includes('bulk-edit')"
						@click="openBulkEditModal()"
						:icon="['fal', 'fa-pencil']"
						size="xs" />

					<Button
						v-if="selectedProducts.length > 0"
						v-tooltip="
							trans('Unlink & Delete Product :platform', {
								platform: props.platform_data?.name,
							})
						"
						:type="'delete'"
						:label="
							trans('Unlink & Delete (:_count)', { _count: selectedProducts?.length })
						"
						:loading="loadingAction.includes('bulk-unlink')"
						@click="
							() =>
								submitPortfolioAction({
									label: 'bulk-unlink',
									name: props.routes.bulk_unlink.name,
									parameters: { customerSalesChannel: customer_sales_channel.id },
									method: 'post',
								})
						"
						size="xs" />

					<Button
						v-if="selectedProducts.length > 0"
						v-tooltip="
							trans('Upload as new product to the :platform', {
								platform: props.platform_data?.name,
							})
						"
						:type="'create'"
						:label="trans('Create New (:_count)', { _count: selectedProducts?.length })"
						:loading="loadingAction.includes('bulk-create')"
						@click="
							() =>
								submitPortfolioAction({
									label: 'bulk-create',
									name: props.routes.bulk_upload.name,
									parameters: { customerSalesChannel: customer_sales_channel.id },
									method: 'post',
								})
						"
						size="xs" />
				</div>

				<div>
					<ButtonWithLink
						v-if="
							customer_sales_channel.type !== 'ebay' &&
							!customer_sales_channel.ban_stock_update_until
						"
						label="Upload all as new product"
						size="xs"
						:routeTarget="{
							name: props.routes.batch_all.name,
							parameters: { customerSalesChannel: customer_sales_channel.id },
							method: 'post',
						}"
						@success="
							() => {
								;((progessbar = {
									...progessbar,
									done: false,
									total: count_product_not_synced,
								}),
									(selectedProducts = []))
							}
						" />
				</div>
			</div>
		</div>
	</Message>

	<!-- retina.models.dropshipping.ebay.batch_upload -->
	<div v-if="(is_platform_connected || isPlatformManual) && currentTab === 'products'">
		<div
			v-if="props.product_count < 1"
			class="relative mx-auto flex max-w-3xl flex-col items-center px-6 text-center pt-20 lg:px-0">
			<h1 class="text-4xl font-bold tracking-tight lg:text-6xl">
				{{ content?.portfolio_empty?.title || trans(`You don't have a single portfolios`) }}
			</h1>
			<p class="mt-4 text-xl">
				{{
					content?.portfolio_empty?.description ||
					trans(
						"To get started, add products to your shop. You can sync from your inventory or create a new one."
					)
				}}
			</p>
			<div class="mt-6 space-y-4">
				<ButtonWithLink
					v-if="routes?.syncAllRoute"
					:routeTarget="routes?.syncAllRoute"
					isWithError
					:label="content?.portfolio_empty?.sync_button"
					icon="fas fa-sync-alt"
					xtype="tertiary"
					size="xl" />
				<div v-if="routes?.syncAllRoute && routes?.addPortfolioRoute" class="text-gray-500">
					{{ content?.portfolio_empty?.separation || trans("or") }}
				</div>
				<Button
					v-if="routes?.addPortfolioRoute"
					@click="isOpenModalPortfolios = true"
					:label="content?.portfolio_empty?.add_button || trans('Add products')"
					icon="fas fa-plus"
					size="xl" />
			</div>
		</div>
		<div v-else class="overflow-x-auto">
			<RetinaTablePortfoliosManual
				v-if="isPlatformManual"
				:data="props.products"
				:tab="'products'"
				:selectedData
				:platform_data
				:platform_user_id
				:is_platform_connected
				:progressToUploadToShopify
				:isPlatformManual
				:useCheckBox="
					is_platform_connected && count_product_not_synced > 0 && !isPlatformManual
				" />
			<RetinaTablePortfoliosShopify
				v-else-if="platform_data.type === 'shopify'"
				:data="props.products"
				:tab="'products'"
				:selectedData
				:platform_data
				:platform_user_id
				:is_platform_connected
				:progressToUploadToShopifyAll="progessbar"
				:progressToUploadToShopify
				:customerSalesChannel="customer_sales_channel"
				v-model:selectedProducts="selectedProducts"
				:key="key"
				:count_product_not_synced="count_product_not_synced" />
			<RetinaTablePortfoliosPlatform
				v-else
				:data="props.products"
				:tab="'products'"
				:selectedData
				:platform_data
				:platform_user_id
				:is_platform_connected
				:progressToUploadToShopify
				:customerSalesChannel="customer_sales_channel"
				:progressToUploadToEcom="progessbar"
				v-model:selectedProducts="selectedProducts"
				:key="key + 'table-products'"
				:routes="props.routes"
				:count_product_not_synced="count_product_not_synced" />
		</div>
	</div>
	<div v-else-if="currentTab === 'logs'">
		<TableRetinaPlatformPortfolioLogs :data="logs" :tab="currentTab" />
	</div>

	<Modal
		:isOpen="isOpenModalPortfolios"
		@onClose="isOpenModalPortfolios = false"
		width="w-full max-w-7xl max-h-[600px] md:max-h-[85vh] overflow-y-auto">
		<AddPortfolios
			v-if="platform_data?.type === 'manual'"
			:step="step"
			:routes="props.routes"
			:platform_data
			@onDone="isOpenModalPortfolios = false"
			:platform_user_id />

		<AddPortfoliosWithUpload
			v-else
			:step="step"
			:routes="props.routes"
			:platform_data
			@onDone="
				() => {
					;((isOpenModalPortfolios = false), (key = ulid()))
				}
			"
			:platform_user_id
			:is_platform_connected
			:customerSalesChannel="customer_sales_channel"
			:onClickReconnect />
	</Modal>

	<Modal
		:isOpen="isModalDownloadImages"
		@onClose="isModalDownloadImages = false"
		width="w-full max-w-lg">
		<div class="flex min-h-full items-end justify-center text-center sm:items-center px-2 py-3">
			<div
				class="relative transform overflow-hidden rounded-lg bg-white text-left transition-all w-full"
				xclass="getTextColorDependsOnStatus(selectedModal?.status)">
				<div>
					<div
						class="mx-auto flex size-12 items-center justify-center rounded-full bg-gray-100"
						xclass="getBgColorDependsOnStatus(selectedModal?.status)">
						<FontAwesomeIcon
							icon="fas fa-spinner"
							class="text-gray-500 text-2xl animate-spin"
							fixed-width
							aria-hidden="true" />
					</div>

					<div class="mt-3 text-center sm:mt-5">
						<div as="h3" class="font-semibold text-2xl">
							Your download images request is being processed.
						</div>

						<div xv-if="selectedModal?.description" class="mt-2 text-sm opacity-75">
							This may take around 10 seconds. You'll receive a notification once it's
							ready.
						</div>
					</div>
				</div>

				<a v-if="linkDownloadImages" :href="linkDownloadImages" class="mt-5 sm:mt-6">
					<Button :label="trans('Download')" full />
				</a>
			</div>
		</div>
	</Modal>

	<Modal
		:isOpen="isModalReadyDownloadImages"
		@onClose="isModalReadyDownloadImages = false"
		width="w-full max-w-lg">
		<div class="flex min-h-full items-end justify-center text-center sm:items-center px-2 py-3">
			<div
				class="relative transform overflow-hidden rounded-lg bg-white text-left transition-all w-full"
				xclass="getTextColorDependsOnStatus(selectedModal?.status)">
				<div>
					<div
						class="mx-auto flex size-12 items-center justify-center rounded-full bg-gray-100"
						xclass="getBgColorDependsOnStatus(selectedModal?.status)">
						<FontAwesomeIcon
							icon="fal fa-check"
							class="text-green-500 text-2xl"
							fixed-width
							aria-hidden="true" />
					</div>

					<div class="mt-3 text-center sm:mt-5">
						<div as="h3" class="font-semibold text-2xl">
							Your images are ready for download.
						</div>

						<div xv-if="selectedModal?.description" class="mt-2 text-sm opacity-75">
							Click the button below to retrieve your files.
						</div>
					</div>
				</div>

				<a
					v-if="linkDownloadImages"
					:href="linkDownloadImages"
					target="_blank"
					download
					class="mt-5 sm:mt-6 block">
					<Button :label="trans('Download')" full />
				</a>
			</div>
		</div>
	</Modal>

<!-- Modal: Bulk Edit Price -->
	<Modal
		:isOpen="modalBulkEditPrice"
		width="w-full max-w-2xl h-full max-h-[570px]"
		@close="modalBulkEditPrice = false">
		<div class="text-xl font-semibold text-center">
			{{ trans("Edit Product Price") }}
		</div>

		<div class="mb-3">
			<label for="edit-product-rrp" class="block text-sm font-semibold">{{
				trans("Up Selling Price")
			}}</label>
			<div class="mt-2 flex flex-row gap-2">
				<Button
					v-for="percent in [20, 40, 60, 80, 100]"
					:key="'p' + percent"
					@click="
						calculateAdjustedPrice(
								percent,
								'percent'
							)"
					:label="`+${percent}%`"
					size="xs"
					:disabled="bulkUpdatePriceData.type === 'percent' && bulkUpdatePriceData.amount === percent"
					:type="'tertiary'" />
				<Button
					v-for="amount in [2, 4, 6, 8, 10]"
					:key="'a' + amount"
					@click="
						calculateAdjustedPrice(
								amount,
								'fixed'
							)"
					:label="`+${amount}`"
					size="xs"
					:disabled="bulkUpdatePriceData.type === 'fixed' && bulkUpdatePriceData.amount === amount"
					:type="'tertiary'" />
				<Button
					@click="
						calculateAdjustedPrice(
								0,
								'reset'
							)"
					:label="trans('Reset')"
					:tooltip="trans('Reset to the original selling price')"
					size="xs"
					:disabled="bulkUpdatePriceData.type === 'reset'"
					:type="'tertiary'" />
			</div>
		</div>

		<div class="mt-3 flex gap-2">
			<Button
				type="tertiary"
				@click="() => submitBulkEditPrice('draft')"
				:label="trans('Save as Draft')"
				full
				:loading="loadingAction.includes('bulk-edit')" />
			<Button
				@click="() => submitBulkEditPrice('publish')"
				:label="trans('Save & Upload')"
				full
				:loading="loadingAction.includes('bulk-edit')" />
		</div>
	</Modal>

	<!-- Modal: Clone Progress -->
	<Modal
		:isOpen="isOpenModalCloneProgress"
		@onClose="
			cloneProgressData.done >= cloneProgressData.total && cloneProgressData.total > 0
				? (isOpenModalCloneProgress = false)
				: null
		"
		width="w-full max-w-md">
		<div class="flex min-h-full items-end justify-center text-center sm:items-center px-2 py-3">
			<div
				class="relative transform overflow-hidden rounded-lg bg-white text-left transition-all w-full">
				<div>
					<div
						class="mx-auto flex size-12 items-center justify-center rounded-full"
						:class="
							cloneProgressData.done >= cloneProgressData.total &&
							cloneProgressData.total > 0
								? cloneProgressData.data.number_fails > 0
									? 'bg-yellow-100'
									: 'bg-green-100'
								: 'bg-gray-100'
						">
						<FontAwesomeIcon
							v-if="
								cloneProgressData.done >= cloneProgressData.total &&
								cloneProgressData.total > 0
							"
							:icon="
								cloneProgressData.data.number_fails > 0
									? 'fas fa-exclamation-triangle'
									: 'fas fa-check'
							"
							:class="
								cloneProgressData.data.number_fails > 0
									? 'text-yellow-500'
									: 'text-green-500'
							"
							class="text-2xl"
							fixed-width
							aria-hidden="true" />
						<FontAwesomeIcon
							v-else
							icon="fas fa-spinner"
							class="text-gray-500 text-2xl animate-spin"
							fixed-width
							aria-hidden="true" />
					</div>

					<div class="mt-3 text-center sm:mt-5">
						<div as="h3" class="font-semibold text-xl">
							<template
								v-if="
									cloneProgressData.done >= cloneProgressData.total &&
									cloneProgressData.total > 0
								">
								{{ trans("Cloning Complete!") }}
							</template>
							<template v-else>
								{{ trans("Cloning Portfolios...") }}
							</template>
						</div>

						<div class="mt-2 text-sm text-gray-500">
							<template v-if="cloneSourceChannelName">
								{{
									trans("From channel: :channel", {
										channel: cloneSourceChannelName,
									})
								}}
							</template>
						</div>

						<!-- Percentage Display -->
						<div v-if="cloneProgressData.total > 0" class="mt-4">
							<div
								class="text-4xl font-bold tabular-nums transition-all duration-300"
								:class="
									cloneProgressData.done >= cloneProgressData.total
										? cloneProgressData.data.number_fails > 0
											? 'text-yellow-500'
											: 'text-green-500'
										: 'text-gray-700'
								">
								{{
									Math.round(
										(cloneProgressData.done / cloneProgressData.total) * 100
									)
								}}%
							</div>
							<div class="text-sm text-gray-500 mt-1">
								{{ cloneProgressData.done }} / {{ cloneProgressData.total }}
								{{ trans("products") }}
							</div>
						</div>

						<!-- Progress Bar -->
						<div class="mt-4 px-4">
							<PureProgressBar
								v-if="cloneProgressData.total > 0"
								:progressBars="cloneProgressData" />
							<div
								v-else
								class="flex items-center justify-center gap-2 text-gray-500">
								<FontAwesomeIcon
									icon="fas fa-spinner"
									class="animate-spin"
									aria-hidden="true" />
								<span>{{ trans("Preparing...") }}</span>
							</div>
						</div>

						<div
							v-if="
								cloneProgressData.done >= cloneProgressData.total &&
								cloneProgressData.total > 0
							"
							class="mt-4 text-sm text-gray-600">
							{{ trans("Page will reload automatically...") }}
						</div>
					</div>
				</div>
			</div>
		</div>
	</Modal>
</template>
