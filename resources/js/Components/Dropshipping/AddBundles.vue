<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import Button from '../Elements/Buttons/Button.vue'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import { onMounted, ref, watch } from 'vue'
import { routeType } from '@/types/route'
import { set } from 'lodash-es'
import axios from 'axios'
import PortfoliosStepSyncShopify from '../Retina/Dropshipping/PortfoliosStepSyncShopify.vue'
import EmptyState from '../Utils/EmptyState.vue'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import PortfoliosStepEdit from '../Retina/Dropshipping/PortfoliosStepEdit.vue'
import BundlesSelector from './BundlesSelector.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Textarea, Dialog, Checkbox } from "primevue"
import { debounce } from 'lodash-es'

const props = defineProps<{
    step: {
        current: number
    }
    bundle_routes:{
        store: routeType
        images: routeType
        calculate: routeType
        ai: any
    }
    routes: {
        syncAllRoute: routeType
        addPortfolioRoute: routeType
        bulk_upload: routeType
        itemRoute: routeType
        updatePortfolioRoute: routeType
        batchDeletePortfolioRoute: routeType
    }
    platform_data: {
        id: number
        code: string
        name: string
        type: string
    }
    platform_user_id: number
    is_platform_connected: boolean
    customerSalesChannel: {

    }
    onClickReconnect: Function
}>()

const emits = defineEmits<(e: "onDone") => void>()

const recentlyUpdatedProduct = ref(null)

// Section: Add portfolios
const isLoadingSubmit = ref(false)
const idxSubmitSuccess = ref(0)
const onSubmitAddPortfolios = async (idProduct: number[]) => {
    router.post(route(props.routes.addPortfolioRoute.name, props.routes.addPortfolioRoute.parameters), {
        items: idProduct
    }, {
        onBefore: () => isLoadingSubmit.value = true,
        onError: (error) => {
            notify({
                title: trans("Something went wrong."),
                text: error.products || undefined,
                type: "error"
            })
        },
        onSuccess: () => {
            router.reload({ only: ['pageHead', 'products'] })
            emits('onDone')

            // notify({
            //     title: trans("Success!"),
            //     text: trans("Successfully added portfolios"),
            //     type: "success"
            // })
            // props.step.current = 1
            // isOpenModalPortfolios.value = false
            // idxSubmitSuccess.value += 1
        },
        onFinish: () => isLoadingSubmit.value = false
    })
}


// Filter portfolios by type
const filterList = [
    {
        label: trans("Product"),
        value: "product",
    },
    {
        label: trans("Department"),
        value: "department",
    },
    {
        label: trans("Sub-department"),
        value: "sub_department",
    },
    {
        label: trans("Family"),
        value: "family",
    }
]
const selectedList = ref(filterList[0])


// Step 1: Submit
const progressToUploadToShopify = ref({})


const portfoliosList = ref([])
const stepLoading = ref(false)

const fetchIndexUnuploadedPortfolios = async () => {
    stepLoading.value = true
    const data = await axios.get(
        route('retina.dropshipping.customer_sales_channels.portfolios.index',
            {
                customerSalesChannel: route().params.customerSalesChannel,
                'filter[un_upload]': 'true',
            }
        )
    )
    portfoliosList.value = data.data.data
    // Automatically select all portfolios for syncing
    selectedPortfoliosToSync.value = [...portfoliosList.value]
    stepLoading.value = false
}

watch(() => props.step.current, async (newStep, oldStep) => {
    // console.log('Step changed to:', oldStep, newStep)
    if (newStep === 1 || newStep === 2) {
        fetchIndexUnuploadedPortfolios()
    }
})

// Step 2: Update portfolios
const listState = ref({})
const updateSelectedProducts = async (portfolio: { id: number }, modelData: {}, section: string) => {
    set(listState.value, [portfolio.id, section], 'loading')


    try {
        const response = await axios[props.routes.updatePortfolioRoute.method || 'patch'](
            route(props.routes.updatePortfolioRoute.name,
                {
                    portfolio: portfolio.id,
                }
            ), modelData
        )

        // console.log('11111 Portfolio updated successfully:', response.data)
        recentlyUpdatedProduct.value = response.data
        set(listState.value, [portfolio.id, section], 'success')
    } catch (error) {
        console.log('Error updating portfolio:', error)
        set(listState.value, [portfolio.id, section], 'error')
    }

    setTimeout(() => {
        set(listState.value, [portfolio.id, section], null)
    }, 3000);
}

// Step 3: bulk upload to Shopify
const isLoadingBulkDeleteUpload = ref(false)
const selectedPortfoliosToSync = ref([])
const bulkUpload = () => {
    router[props.routes.bulk_upload.method || 'post'](
        route(props.routes.bulk_upload.name, props.routes.bulk_upload.parameters),
        {
            portfolios: selectedPortfoliosToSync.value.map((product: any) => product.id),
        },
        {
            preserveScroll: true,
            // onBefore: () => isLoadingUpload.value = true,
            onStart: () => {
                isLoadingBulkDeleteUpload.value = true
            },
            onSuccess: () => {
                selectedPortfoliosToSync.value.forEach((product) => {
                    set(progressToUploadToShopify.value, [product.id], 'loading')
                })
                selectedPortfoliosToSync.value = []
                // notify({
                // 	title: trans("Success!"),
                // 	text: trans("Successfully uploaded portfolios"),
                // 	type: "success",
                // })
            },
            onFinish: () => {
                isLoadingBulkDeleteUpload.value = false
            },
            onError: (error) => {
                notify({
                    title: trans("Something went wrong"),
                    text: error.message || trans("An error occurred while uploading portfolios"),
                    type: "error",
                })
            }
        }
    )
}
const bulkDelete = () => {
    router[props.routes.batchDeletePortfolioRoute.method || 'post'](
        route(props.routes.batchDeletePortfolioRoute.name, props.routes.batchDeletePortfolioRoute.parameters),
        {
            portfolios: selectedPortfoliosToSync.value.map((product: any) => product.id),
        },
        {
            preserveScroll: true,
            // onBefore: () => isLoadingUpload.value = true,
            onStart: () => {
                isLoadingBulkDeleteUpload.value = true
            },
            onSuccess: () => {
                // selectedPortfoliosToSync.value.forEach((product) => {
                // 	set(progressToUploadToShopify.value, [product.id], 'loading')
                // })
                portfoliosList.value = portfoliosList.value.filter(
                    (portfolio) => !selectedPortfoliosToSync.value.some((p: any) => p.id === portfolio.id)
                )
                selectedPortfoliosToSync.value = []
                notify({
                    title: trans("Success!"),
                    text: trans("Deleted portfolios successfully"),
                    type: "success",
                })
            },
            onFinish: () => {
                isLoadingBulkDeleteUpload.value = false
            },
            onError: (error) => {
                notify({
                    title: trans("Something went wrong"),
                    text: error.message || trans("An error occurred while uploading portfolios"),
                    type: "error",
                })
            }
        }
    )
}

const summary = ref({
    cost_price: 0,
    bundle_price: 0,
    rrp: 0,
    profit: 0,
    profit_percent: 0
})

const selectedProducts = ref<any[]>([]) 

const bundleDescription = ref('')
const isGeneratingAI = ref(false)

const showMediaModal = ref(false)
const isLoadingMedia = ref(false)

const selectedMedia = ref<string[]>([])

const mediaGallery = ref<string[]>([])

const calculateBundle = async () => {
    try {
        const { data } = await axios.post(
            route(
                props.bundle_routes.calculate.name,
                props.bundle_routes.calculate.parameters
            ),
            {
                // items: selectedProducts.value.map(p => ({
                //     id: p.id,
                //     quantity: p.quantity || 1
                // }))
            }
        )

        summary.value = data

    } catch (e) {
        notify({
            title: trans('Error'),
            text: trans('Failed to calculate bundle'),
            type: 'error'
        })
    }
}

const openExistingMedia = async () => {
    showMediaModal.value = true

    if (!mediaGallery.value.length) {
        fetchMediaGallery()
    }
}

const toggleSelect = (img: string) => {
    if (selectedMedia.value.includes(img)) {
        selectedMedia.value =
            selectedMedia.value.filter(i => i !== img)
    } else {
        selectedMedia.value.push(img)
    }
}

const removeMedia = (img: string) => {
    selectedMedia.value =
        selectedMedia.value.filter(i => i !== img)
}

const generateAITitle = async () => {
    try {
        isGeneratingAI.value = true

        const { data } = await axios.post(
            route(
                props.bundle_routes.ai.generate_title.name,
                props.bundle_routes.ai.generate_title.parameters
            ),
            {
                items: selectedProducts.value
            }
        )

        bundleTitle.value = data.title

    } finally {
        isGeneratingAI.value = false
    }
}

const generateAIDescription = async () => {
    try {
        isGeneratingAI.value = true

        const { data } = await axios.post(
            route(
                props.bundle_routes.ai.generate_description.name,
                props.bundle_routes.ai.generate_description.parameters
            ),
            {
                items: selectedProducts.value
            }
        )

        bundleDescription.value = data.description

    } finally {
        isGeneratingAI.value = false
    }
}

const generateAIImages = async () => {
    try {
        isGeneratingAI.value = true

        const { data } = await axios.post('/api/bundle/ai-images', {
            description: bundleDescription.value
        })

        selectedMedia.value = [
            ...selectedMedia.value,
            ...data.images
        ]

    } finally {
        isGeneratingAI.value = false
    }
}

const fetchMediaGallery = async () => {
    try {
        isLoadingMedia.value = true

        const { data } = await axios.get(
            route(
                props.bundle_routes.images.get.name,
                props.bundle_routes.images.get.parameters
            )
        )

        mediaGallery.value = data

    } catch (e) {
        notify({
            title: trans('Error'),
            text: trans('Failed to load media'),
            type: 'error'
        })
    } finally {
        isLoadingMedia.value = false
    }
}

const openUpload = () => {
    // TODO open file picker / dropzone
}

const onUpdateSelectedProducts = (products:any[]) => {
    selectedProducts.value = products.map(p => ({
        ...p,
        quantity: p.quantity_selected || 1
    }))
}

const submitBundle = async () => {
    try {
        await axios.post(
            route(
                props.bundle_routes.store.name,
                props.bundle_routes.store.parameters
            ),
            {
                title: bundleTitle.value,
                description: bundleDescription.value,
                media: selectedMedia.value,
                items: selectedProducts.value.map(p => ({
                    id: p.id,
                    quantity: p.quantity || 1
                }))
            }
        )

        notify({
            title: trans('Success'),
            text: trans('Bundle created successfully'),
            type: 'success'
        })

        emits('onDone')

    } catch (e) {
        notify({
            title: trans('Error'),
            text: trans('Failed to create bundle'),
            type: 'error'
        })
    }
}

watch(selectedProducts, () => {
    if(selectedProducts.value.length){
        calculateBundle()
    } else {
        summary.value = {
            cost_price: 0,
            bundle_price: 0,
            rrp: 0,
            profit: 0,
            profit_percent: 0
        }
    }
}, { deep:true })

const debouncedCalculate = debounce(calculateBundle, 400)

watch(selectedProducts, () => {
    debouncedCalculate()
}, { deep:true })
onMounted(() => {
    if (props.step.current > 0) {
        fetchIndexUnuploadedPortfolios()
    }
})
</script>

<template>
    <div>
        <!-- NEW HEADER STEP 0 -->
        <div v-if="step.current === 0" class="flex items-start justify-between border-b pb-4 mb-4">
            <!-- LEFT -->
            <div>
                <div class="text-xl font-semibold">
                    Create Your Bundle
                    <FontAwesomeIcon icon="fal fa-layer-group" class="text-xl text-black" fixed-width
                        aria-hidden="true" />
                </div>

                <div class="text-sm mt-1">
                    STEP {{ step.current + 1 }}/2
                </div>
            </div>

            <!-- RIGHT SUMMARY -->
            <div class="w-[320px] text-sm space-y-2">
                <div class="flex justify-between border-b pb-1">
                    <span class="text-gray-500">Cost Price (Individual Purchase)</span>
                    <span class="font-medium">{{ summary.cost_price }}</span>
                </div>

                <div class="flex justify-between border-b pb-1">
                    <span class="text-gray-500">Bundle Price (-10%)</span>
                    <span class="font-medium text-green-600">{{ summary.bundle_price }}</span>
                </div>

                <div class="flex justify-between border-b pb-1">
                    <span class="text-gray-500">RRP</span>
                    <span class="font-medium">{{ summary.rrp }}</span>
                </div>

                <div class="flex justify-between pt-1">
                    <span class="text-gray-500">Profit</span>
                    <span class="font-semibold text-green-600"> [{{ summary.profit_percent }}%] {{ summary.profit }}</span>
                </div>
            </div>
        </div>

        <!-- Head: step 2 (Sync to Shopify) -->

        <!-- 0: Select Product -->
        <KeepAlive>
            <BundlesSelector v-if="step.current === 0" xheadLabel="trans('Add products to portfolios')" @update:selected="onUpdateSelectedProducts" :route-fetch="{
                name: props.routes.itemRoute.name,
                parameters: {
                    ...props.routes.itemRoute.parameters,
                    'filter[type]': selectedList.value,
                },
            }" :valueToRefetch="selectedList.value" :label_result="selectedList.label" :isLoadingSubmit
                :idxSubmitSuccess
                @submit="(products: {}[]) => onSubmitAddPortfolios(products.map((product: any) => product.id))"
                class="px-4">
                <template #header>
                    <div>

                    </div>
                </template>
                <template #afterInput>
                    <div class="flex items-center justify-between mt-3">

                        <!-- FILTER LIST -->
                        <div class="flex gap-2 text-sm font-semibold text-gray-500">
                            <div v-for="list in filterList" @click="selectedList = list"
                                class="whitespace-nowrap py-2 px-3 cursor-pointer rounded border" :class="selectedList.value === list.value
                                    ? 'bg-gray-800 text-white border-gray-800'
                                    : 'border-gray-300 hover:bg-gray-100'
                                    ">
                                {{ list.label }}
                            </div>
                        </div>

                        <!-- NEXT BUTTON -->
                        <Button @click="step.current = 1" label="Next" iconRight="fal fa-arrow-right" />

                    </div>
                </template>
            </BundlesSelector>
        </KeepAlive>

        <!-- 1: change the UI generate bundle -->
        <KeepAlive>
            <div v-if="step.current === 1" class="flex justify-center">

                <div class="w-full">

                    <!-- BACK -->
                    <div class="mb-3">
                        <Button @click="step.current = 0" type="tertiary" icon="fal fa-arrow-left"
                            :label="trans('Back')" />
                    </div>

                    <!-- HEADER -->
                    <div class="mb-5">
                        <div class="text-xl font-semibold flex items-center gap-2">
                            Create Your Bundle

                            <FontAwesomeIcon v-tooltip="trans('Bundle generator')" icon="fal fa-layer-group"
                                class="text-gray-500" fixed-width />
                        </div>

                        <div class="text-sm text-gray-400">
                            STEP 2 / 2
                        </div>
                    </div>

                    <!-- DESCRIPTION -->
                    <div class="mb-5">
                        <label class="text-sm font-semibold">
                            {{ trans('Description') }}
                        </label>

                        <Textarea v-model="bundleDescription" rows="6" autoResize class="w-full mt-1" />

                        <div class="flex justify-between items-center mt-2">

                            <div class="text-xs text-gray-400">
                                {{ bundleDescription.length }}/300
                            </div>

                            <Button @click="generateAIDescription" :loading="isGeneratingAI" type="primary">
                                <FontAwesomeIcon icon="fal fa-sparkles" class="mr-2" fixed-width />
                                Generate with AI
                            </Button>

                        </div>
                    </div>

                    <!-- MEDIA -->
                    <div class="mb-5">
                        <label class="text-sm font-semibold">
                            {{ trans('Media') }}
                        </label>

                        <!-- DROPZONE -->
                        <div class="border-2 border-dashed rounded-xl h-[120px] flex flex-col items-center justify-center text-gray-400 mt-1 cursor-pointer hover:bg-gray-50"
                            @click="openUpload">
                            <FontAwesomeIcon icon="fal fa-cloud-upload" class="text-2xl mb-1" fixed-width />

                            <div class="text-sm">
                                Upload Media
                            </div>

                            <div class="text-xs">
                                Drag & drop images
                            </div>
                        </div>

                        <!-- ACTION -->
                        <div class="flex gap-2 mt-3">
                            <Button @click="openExistingMedia" type="secondary">
                                <FontAwesomeIcon icon="fal fa-images" class="mr-2" fixed-width />
                                Select existing media
                            </Button>

                            <Button @click="generateAIImages" :loading="isGeneratingAI" type="primary">
                                <FontAwesomeIcon icon="fal fa-wand-magic-sparkles" class="mr-2" fixed-width />
                                Generate Image AI
                            </Button>
                        </div>
                    </div>

                    <!-- PREVIEW -->
                    <div class="mb-5">
                        <label class="text-sm font-semibold">
                            {{ trans('Bundle media') }}
                        </label>

                        <div class="bg-gray-100 rounded-xl p-3 mt-1 grid grid-cols-3 gap-3 min-h-[110px]">
                            <div v-for="img in selectedMedia" class="relative group">
                                <img :src="img" class="rounded-lg h-24 w-full object-cover" />

                                <button
                                    class="absolute top-1 right-1 bg-black/70 text-white text-xs px-1 rounded opacity-0 group-hover:opacity-100"
                                    @click="removeMedia(img)">
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- SUBMIT -->
                    <Button @click="submitBundle" class="flex justify-center items-center w-full" type="primary">
                        Create Bundle
                        <FontAwesomeIcon icon="fal fa-layer-group" class="mr-2" fixed-width />
                    </Button>

                </div>
                <Dialog v-model:visible="showMediaModal" modal header="Select Images" :style="{ width: '600px' }">

                    <div v-if="isLoadingMedia" class="py-10 text-center">
                        <LoadingIcon />
                    </div>

                    <div v-else class="grid grid-cols-4 gap-3">
                        <div v-for="img in mediaGallery" class="relative cursor-pointer" @click="toggleSelect(img)">
                            <img :src="img" class="rounded-lg h-24 w-full object-cover" />

                            <Checkbox :modelValue="selectedMedia.includes(img)" binary
                                class="absolute top-2 left-2 bg-white rounded" />
                        </div>
                    </div>

                    <template #footer>
                        <Button @click="showMediaModal = false" type="primary">
                            Done
                        </Button>
                    </template>

                </Dialog>
            </div>

        </KeepAlive>
    </div>
</template>
