<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import Button from "@/Components/Elements/Buttons/Button.vue";
import { notify } from '@kyvg/vue3-notification'
import { onMounted, ref, watch, computed, inject } from 'vue'
import { routeType } from '@/types/route'
import { set } from 'lodash-es'
import axios from 'axios'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import BundlesSelector from './BundlesSelector.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Textarea, Dialog, Checkbox, InputText, Skeleton } from "primevue"
import { debounce } from 'lodash-es'
import { route } from 'ziggy-js'
import Image from '../Image.vue'
import { faLayerGroup, faSparkles, faTrash, faImages, faUpload } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { router } from '@inertiajs/vue3';
import { useBundle } from '@/Composables/useBundle';
library.add(faLayerGroup, faSparkles, faTrash, faImages, faUpload)

const props = defineProps<{
    step: {
        current: number
    }
    bundle_routes: {
        store: routeType
        update: routeType
        delete: routeType
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
    shop_data: {
        currency_code: string
        currency_symbol: string
    }
}>()

const locale = inject('locale', null)

const emits = defineEmits<{
  (e: "onDone"): void
  (e: "onClose"): void
}>()

// Section: Add portfolios
const isLoadingSubmit = ref(false)
const idxSubmitSuccess = ref(0)

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
    if (newStep === 1 || newStep === 2) {
        fetchIndexUnuploadedPortfolios()
    }
})

const selectedPortfoliosToSync = ref([])

const bundle = useBundle(props.bundle_routes)
const preselectedProducts = ref<any[]>([])

const isGeneratingAI = ref(false)
const showMediaModal = ref(false)
const isLoadingMedia = ref(false)
const selectedMedia = ref<any[]>([])
const selectedMediaIds = ref<number[]>([])
const mediaGallery = ref<string[]>([])

const openExistingMedia = async () => {
    showMediaModal.value = true
    fetchMediaGallery()
}

const flatMediaGallery = computed(() => {

    const result: any[] = []

    mediaGallery.value.forEach(product => {

        if (!product.image) return

        Object.entries(product.image).forEach(([imageId, imageData]: any) => {

            result.push({
                product_id: product.id,
                image_id: Number(imageId),
                key: `${product.id}-${imageId}`,
                url: imageData.original,
                image: imageData
            })

        })

    })
    return result
})

const toggleSelect = (img: any) => {

    const index = selectedMediaIds.value.indexOf(img.key)

    if (index !== -1) {

        selectedMediaIds.value.splice(index, 1)

        selectedMedia.value =
            selectedMedia.value.filter(m => m.key !== img.key)

    } else {

        selectedMediaIds.value.push(img.key)

        selectedMedia.value.push({
            key: img.key,
            image_id: img.image_id,
            product_id: img.product_id,
            url: img.url,
            image: img.image,
            is_main: false
        })
    }
}

const setMainImage = (imageId: number) => {
    selectedMedia.value = selectedMedia.value.map(img => ({
        ...img,
        is_main: img.image_id === imageId
    }))
}

const removeMedia = (media: any) => {
    selectedMedia.value =
        selectedMedia.value.filter(m => m.image_id !== media.image_id)
}

const showGenerateModal = ref(false)
const aiPrompt = ref('')
const selectedMediaForAI = ref<any[]>([])

const generateAIImages = async () => {
    try {
        isGeneratingAI.value = true

        const payload = {
            images: selectedMediaForAI.value.map(m => m.image_id),
            prompt: aiPrompt.value
        }

        const routeParams = {
            ...props.bundle_routes.ai.generate_images.parameters,
            product: bundle.product_id.value
        }


        const res = await axios.post(
            route(
                props.bundle_routes.ai.generate_images.name,
                routeParams
            ),
            payload
        )

        const media = res.data?.data

        if (media) {

            selectedMedia.value.push({
                id: media.id,
                image_id: media.id,
                url: media.source?.original || media.thumbnail?.original,
                image: media.thumbnail || media.source,
                is_ai: true,
                is_main: false
            })

        }
        showGenerateModal.value = false

        aiPrompt.value = ''
        selectedMediaForAI.value = []

        notify({
            title: 'AI Image Generated',
            type: 'success'
        })
    } catch (e) {
        notify({
            title: trans('Error'),
            text: trans('Failed to generate AI'),
            type: 'error'
        })
    } finally {
        isGeneratingAI.value = false
    }
}

const productIds = computed(() => {
    return bundle.products.value.map(p => p.id)
})

const fetchMediaGallery = async () => {
    try {
        isLoadingMedia.value = true

        const url = route(
            props.bundle_routes.images.get.name,
            {
                product_ids: productIds.value
            }
        )
        const response = await axios.get(url)
        mediaGallery.value = response.data.data || []
    } catch (e) {
        console.error(e)

        notify({
            title: trans('Error'),
            text: trans('Failed to load media'),
            type: 'error'
        })
    } finally {
        isLoadingMedia.value = false
    }
}


const onUpdateSelectedProducts = (products: any[]) => {
    bundle.products.value = products.map(p => ({
        ...p,
        quantity: p.quantity_selected ?? 1
    }))
}

const isSubmitBundle = ref(false)

const handleStoreBundle = async () => {
    try {
        await bundle.storeBundle()

        props.step.current = 1

        notify({
            title: trans('Success'),
            type: 'success'
        })
    } catch (e) {
        notify({
            title: trans('Error'),
            text: trans('Failed to create bundle'),
            type: 'error'
        })
    }
}

const submitBundle = async () => {

    const payload = {
        description: bundle.description.value,
        images: selectedMedia.value.map(img => ({
            id: img.image_id,
            is_main: img.is_main
        }))
    }

    const routeParams = {
        ...props.bundle_routes.update.parameters,
        bundle: bundle.bundle_id.value
    }

    router.patch(
        route(props.bundle_routes.update.name, routeParams),
        payload,
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isSubmitBundle.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans('Success'),
                    text: trans('Success submit bundle'),
                    type: 'success'
                })
                bundle.resetBundle()
                selectedMedia.value = []
                selectedMediaIds.value = []
                selectedMediaForAI.value = []
                props.step.current = 0
                localStorage.removeItem('iris_bundle_products')
                emits('onDone')
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to submit the data, please try again"),
                    type: "error"
                })
            },
                onFinish: () => {
                isSubmitBundle.value = false
            },
        }
    )
}

const selectedMediaAIIds = ref<any[]>([])
const toggleSelectAI = (media: any) => {

    const index = selectedMediaAIIds.value.indexOf(media.key)

    if (index !== -1) {

        selectedMediaAIIds.value.splice(index, 1)

        selectedMediaForAI.value =
            selectedMediaForAI.value.filter(
                m => m.key !== media.key
            )

    } else {

        selectedMediaAIIds.value.push(media.key)

        selectedMediaForAI.value.push(media)

    }
}

// action iamge
const fileInput = ref<HTMLInputElement | null>(null)

const uploadFilesLocal = async (files: FileList) => {
    if (!bundle.product_id.value) {
        notify({
            title: trans('Error'),
            text: trans('Reload Pages'),
            type: 'error'
        })
    }
    console.log("productid", bundle.product_id.value)
    try {
        const formData = new FormData()

        Array.from(files).forEach(file => {
            formData.append('images[]', file)
        })

        const routeParams = {
            ...props.bundle_routes.images.store.parameters,
            product: bundle.product_id.value
        }

        const res = await axios.post(
            route(
                props.bundle_routes.images.store.name,
                routeParams
            ),
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }
        )
        const media = res.data?.data || []

        if (media) {
            selectedMedia.value.push({
                id: media.id,
                image_id: media.id,
                url: media.source.original,
                image: media.source,
                is_ai: false,
                is_main: false
            })
        }
        
        notify({
            title: 'Success Upload Image',
            type: 'success'
        })

    } catch (e) {
        console.error('UPLOAD ERROR', e)
        notify({
            title: 'Failed Upload Image',
            type: 'error'
        })
    }
}

const openFilePicker = () => {
    fileInput.value?.click()
}

const onDrop = (e: DragEvent) => {
    if (!e.dataTransfer?.files?.length) return
    uploadFilesLocal(e.dataTransfer.files)
}

const onFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement
    if (!target.files) return
    uploadFilesLocal(target.files)
}

const handleClose = () => {
    emits('onClose')
}

const handleBack = () => {
    props.step.current = 0
    fetchGetBundle()
}

const fetchGetBundle = async () => {
    if (!bundle.bundle_id.value && !props.bundle_routes?.images) return

    try {
        await bundle.loadBundle({
            routeConfig: props.bundle_routes.images.edit,
            bundleId: bundle.bundle_id.value,
            bundleParamOverride: {
                ...props.bundle_routes.images.edit.parameters,
                bundle: [bundle.bundle_id.value]
            },
            onProductsLoaded: (products) => {
                preselectedProducts.value = products
            }
        })
    } catch (e) {
        console.error('[AddBundles] fetchGetBundle failed', e)
        notify({
            title: trans('Error'),
            text: trans('Failed to load bundle'),
            type: 'error'
        })
    }
}

watch(bundle.products.value, () => {
    if (bundle.products.value.length) {
        bundle.addProduct(bundle.products.value)
    } else {
        bundle.summary.value = {
            total_price: 0,
            total_bundle_price: 0,
            total_rrp: 0,
            profit: 0,
            profit_percentage: 0
        }
    }
}, { deep: true })

const debouncedCalculate = debounce(bundle.calculateBundle, 400)

watch(bundle.products.value, () => {
debouncedCalculate()
}, { deep: true })

onMounted(() => {
    try {
        localStorage.removeItem('iris_bundle_products')
    } catch (e) {
        console.warn('[AddBundles] unable to clear cached bundle products', e)
    }
    bundle.resetBundle()

    if (props.step.current > 0) {
        fetchIndexUnuploadedPortfolios()
    }
})
watch(
    selectedMedia,
    (val) => {
        if (!val.length) return

        let found = false

        val.forEach((img, i) => {
            if (img.is_main && !found) {
                found = true
            } else {
                img.is_main = false
            }
        })

        if (!found) {
            val[0].is_main = true
        }
    },
    { deep: true }
)
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
             <div class="relative">
                <!-- <button
                    @click="handleClose"
                    class="absolute -top-4 -right-4 text-gray-500 hover:text-red-500"
                    >
                    <FontAwesomeIcon icon="fal fa-times" class="text-lg" />
                </button> -->
                <div class="w-[320px] text-sm space-y-2">
                    <template v-if="bundle.isSummaryLoading.value">
                        <div v-for="idx in 4" :key="idx" class="flex items-center justify-between border-b pb-1 last:border-b-0">
                            <Skeleton width="9rem" height="0.9rem" />
                            <Skeleton width="6rem" height="0.9rem" />
                        </div>
                    </template>

                    <template v-else>
                        <div class="flex justify-between border-b pb-1">
                            <span class="text-gray-500">Cost Price (Individual Purchase)</span>
                            <span class="font-medium">
                                {{ locale?.currencyFormat(props.shop_data?.currency_code ?? 'usd', bundle.summary.value.total_price ?? 0) }}
                            </span>
                        </div>

                        <div class="flex justify-between border-b pb-1">
                            <span class="text-gray-500">Bundle Price</span>
                            <span class="font-medium text-green-600">
                                 {{ locale?.currencyFormat(props.shop_data?.currency_code ?? 'usd', bundle.summary.value.total_bundle_price ?? 0) }}
                            </span>
                        </div>

                        <div class="flex justify-between border-b pb-1">
                            <span class="text-gray-500">RRP</span>
                            <span class="font-medium">
                                {{ locale?.currencyFormat(props.shop_data?.currency_code ?? 'usd', bundle.summary.value.total_rrp ?? 0) }}
                            </span>
                        </div>

                        <div class="flex justify-between pt-1">
                            <span class="text-gray-500">Profit</span>
                            <span class="font-semibold text-green-600"> [{{ bundle.summary.value.profit_percentage }}%] {{ locale?.currencyFormat(props.shop_data?.currency_code ?? 'usd', bundle.summary.value.profit ?? 0) }}
                                </span>
                        </div>
                    </template>
                </div>
                
            </div>
        </div>

        <!-- 0: Select Product -->
        <KeepAlive>
            <BundlesSelector v-if="step.current === 0" xheadLabel="trans('Add products to portfolios')"
                @update:selected="onUpdateSelectedProducts" :route-fetch="{
                    name: props.routes.itemRoute.name,
                    parameters: {
                        ...props.routes.itemRoute.parameters,
                        'filter[type]': selectedList.value,
                    },
                }" :valueToRefetch="selectedList.value" :label_result="selectedList.label" :isLoadingSubmit
                :idxSubmitSuccess :preselected="preselectedProducts" class="px-4" withQuantity>
                <template #header>
                    <div>
                        <div class="mb-4">
                            <label class="text-sm block mb-1">
                                Bundle Title
                            </label>

                            <div class="relative">

                                <InputText v-model="bundle.title.value" type="text" class="w-full pr-10 text-base p-2"
                                    :placeholder="ctrans('Bundle Title')" required />

                                <Button type="button" @click="bundle.generateAITitle"
                                    :loading="bundle.isGeneratingAI.value"
                                    :disabled="!bundle.productIds.value.length || bundle.isGeneratingAI.value"
                                    icon="fal fa-sparkles"
                                    v-tooltip="trans('Generate AI')" class="absolute right-2 top-1/2 -translate-y-1/2 
                                        h-7 w-7 flex items-center justify-center 
                                        rounded-md border bg-white hover:bg-gray-100 
                                        transition shadow-sm" />
                            </div>
                        </div>
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
                        <Button @click="handleStoreBundle" :loading="bundle.isStoringBundle.value" label="Next"
                            iconRight="fal fa-arrow-right" :disabled="!bundle.products.value.length" />
                        
                    </div>
                </template>
            </BundlesSelector>
        </KeepAlive>

        <!-- 1: change the UI generate bundle -->
        <KeepAlive>
            <div v-if="step.current === 1" class="flex justify-center">

                <div class="w-full">
                    <!-- HEADER -->
                     <div class="mb-5 flex items-start justify-between">

                        <!-- LEFT: BACK BUTTON -->
                         <div class="flex flex-col gap-2">
                            <button
                                @click="handleBack"
                                class="flex items-center gap-2 text-gray-600 hover:text-black"
                            >
                                <FontAwesomeIcon icon="fal fa-arrow-left" />
                                <span class="text-sm">Back</span>
                            </button>

                            <!-- CENTER: TITLE -->
                            <div class="text-left">
                                <div class="text-xl font-semibold flex items-center justify-center gap-2">
                                    Create Your Bundle

                                    <FontAwesomeIcon
                                        v-tooltip="trans('Bundle generator')"
                                        icon="fal fa-layer-group"
                                        class="text-gray-500"
                                        fixed-width
                                    />
                                </div>

                                <div class="text-sm text-gray-400">
                                    STEP 2 / 2
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT: CLOSE BUTTON -->
                        <button
                            @click="handleClose"
                            class="text-gray-500 hover:text-red-500"
                        >
                            <FontAwesomeIcon icon="fal fa-times" class="text-lg" />
                        </button>

                    </div>

                    <!-- DESCRIPTION -->
                    <div class="mb-5">
                        <label class="text-sm font-semibold">
                            {{ trans('Description') }}
                        </label>

                        <Textarea v-model="bundle.description.value" rows="6" autoResize class="w-full mt-1"
                            placeholder="Input your description" />

                        <div class="flex justify-between items-center mt-2">

                            <div class="text-xs text-gray-400">
                                Characters {{ bundle.description.value.length }} words
                            </div>

                            <Button @click="bundle.generateAIDescription" :loading="bundle.isGeneratingAI.value"
                            icon="fal fa-sparkles"
                            :label="trans('Generate with AI')"
                                type="primary" :disabled="!bundle.productIds.value.length" />
                        </div>
                    </div>

                    <!-- MEDIA -->
                    <div class="mb-5">
                        <div class="border-2 border-dashed border-gray-300 rounded-xl h-[140px]
                                flex flex-col items-center justify-center
                                text-gray-400 cursor-pointer hover:bg-gray-50 transition" @dragover.prevent
                            @drop.prevent="onDrop" @click="openFilePicker">

                            <FontAwesomeIcon icon='fas fa-upload'
                                class='!border-2 !rounded-full !p-2 !text-xl !text-muted-color' fixed-width
                                aria-hidden='true' />

                            <div class="text-sm font-medium">
                                Upload Media
                            </div>

                            <div class="text-xs">
                                Drag & drop images or click
                            </div>

                        </div>

                        <input ref="fileInput" type="file" multiple accept="image/*" class="hidden"
                            @change="onFileChange" />

                        <!-- ACTION -->
                        <div class="flex gap-2 mt-3">
                            <Button @click="openExistingMedia" type="secondary">
                                <FontAwesomeIcon :icon="faImages" class="mr-2" fixed-width />
                                Select existing media
                            </Button>

                            <Button @click="showGenerateModal = true" type="primary" icon="fal fa-arrow-left"
                                :disabled="!selectedMedia.length">
                                <FontAwesomeIcon :icon="faSparkles" class="mr-2" fixed-width />
                                Generate Image AI
                            </Button>
                        </div>
                    </div>

                    <!-- PREVIEW -->
                    <div class="mb-5">
                        <label class="text-sm font-semibold">
                            {{ trans('Bundle media') }}
                        </label>

                        <div class="bg-gray-100 rounded-xl p-4 mt-2 grid grid-cols-2 md:grid-cols-3 gap-4 min-h-[140px]">
                            <div v-for="img in selectedMedia" class="relative group rounded-xl border bg-white flex items-center justify-center h-36 md:h-44">
                                <Image :key="img.id" :src="img.image" class="h-36 md:h-40 object-contain rounded-xl" />

                                <input type="radio" name="main_image" :checked="img.is_main"
                                    @change="setMainImage(img.image_id)" class="absolute top-2 left-2 z-20" />
                                <div v-if="img.is_main"
                                    class="absolute bottom-1 left-1 text-[10px] bg-black/70 text-white px-1 rounded">
                                    MAIN IMAGE
                                </div>
                                <button
                                    class="absolute top-1 right-1 bg-black/70 text-white text-xs px-1 rounded opacity-0 group-hover:opacity-100"
                                    @click="removeMedia(img)">
                                    <FontAwesomeIcon icon="fal fa-times" class="text-lg text-red-500" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- SUBMIT -->
                    
                    <Button @click="submitBundle" icon="fal fa-layer-group" class="flex justify-center items-center w-full" type="primary" 
                    :label="trans('Create Bundle')"
                    :disabled="!bundle.description.value.length || bundle.isStoringBundle.value"
                        :loading="isSubmitBundle" />
                  

                </div>
                
                <!-- Modal Existing media -->
                <Dialog v-model:visible="showMediaModal" modal header="Select Images" :style="{ width: '600px' }">
                    <div v-if="isLoadingMedia" class="py-10 text-center">
                        <LoadingIcon />
                    </div>
                    <div v-else class="grid grid-cols-4 gap-3">
                        <template v-if="flatMediaGallery.length">
                            <div v-for="img in flatMediaGallery" :key="img.key"
                                class="relative aspect-square rounded-xl overflow-hidden border cursor-pointer group"
                                @click="toggleSelect(img)">

                                <div class="absolute inset-0 z-0">
                                    <Image :src="img.image" class="w-full h-full" imageCover />
                                </div>

                                <div v-if="selectedMediaIds.includes(img.key)"
                                    class="absolute inset-0 bg-black/40 z-10" />

                                <Checkbox :modelValue="selectedMediaIds.includes(img.key)" binary
                                    class="absolute top-2 left-2 z-20 bg-white rounded shadow pointer-events-none" />

                                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition z-5" />

                            </div>
                        </template>
                        <template v-else>
                            <div class="col-span-4 text-center text-gray-400 py-6">
                                No images yet
                            </div>
                        </template>
                    </div>

                    <template #footer>
                        <Button @click="showMediaModal = false" type="primary">
                            Done
                        </Button>
                    </template>

                </Dialog>

                <Dialog v-model:visible="showGenerateModal" header="Generate AI Image" modal
                    :style="{ width: '600px' }">

                    <div class="mb-4">
                        <div class="text-sm font-semibold mb-2">
                            Select images of products you want to include in generated image
                        </div>

                        <div class="grid grid-cols-4 gap-3">

                            <div v-for="media in selectedMedia" :key="media.key"
                                class="relative aspect-square rounded-xl overflow-hidden border cursor-pointer group"
                                @click="toggleSelectAI(media)">


                                <!-- IMAGE -->
                                <div class="absolute inset-0 z-0">
                                    <Image :src="media.image" class="w-full h-full" imageCover />
                                </div>

                                <!-- DARK OVERLAY -->
                                <div v-if="selectedMediaAIIds.includes(media.key)"
                                    class="absolute inset-0 bg-black/40 z-10" />

                                <!-- CHECKBOX (visual only) -->
                                <Checkbox :modelValue="selectedMediaAIIds.includes(media.key)" binary
                                    class="absolute top-2 left-2 z-20 bg-white rounded shadow pointer-events-none" />

                                <!-- HOVER -->
                                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition" />

                            </div>

                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="text-sm font-semibold mb-1">
                            Describe your image
                        </div>

                        <Textarea v-model="aiPrompt" rows="3" class="w-full" placeholder="Input description" />
                    </div>

                    <template #footer>
                        <Button :label="trans('Generate')" @click="generateAIImages" :loading="isGeneratingAI"
                            :disabled="!selectedMediaForAI.length || !aiPrompt" />
                    </template>

                </Dialog>
            </div>

        </KeepAlive>
    </div>
</template>
