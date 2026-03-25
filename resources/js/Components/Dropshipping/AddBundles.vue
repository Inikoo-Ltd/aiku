<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import Button from '../Elements/Buttons/Button.vue'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import { onMounted, ref, watch, computed } from 'vue'
import { routeType } from '@/types/route'
import { set } from 'lodash-es'
import axios from 'axios'
import EmptyState from '../Utils/EmptyState.vue'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import BundlesSelector from './BundlesSelector.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Textarea, Dialog, Checkbox, FileUpload } from "primevue"
import { debounce } from 'lodash-es'
import { route } from 'ziggy-js'
import Image from '../Image.vue'
import { faLayerGroup, faSparkles, faTrash,faImages } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faLayerGroup, faSparkles, faTrash, faImages)

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
    // console.log('Step changed to:', oldStep, newStep)
    if (newStep === 1 || newStep === 2) {
        fetchIndexUnuploadedPortfolios()
    }
})

const selectedPortfoliosToSync = ref([])

const summary = ref({
    total_price: 0,
    total_bundle_price: 0,
    total_rrp: 0,
    profit: 0,
    profit_percentage: 0
})

const selectedProducts = ref<any[]>([]) 

const bundleDescription = ref('')
const isGeneratingAI = ref(false)

const showMediaModal = ref(false)
const isLoadingMedia = ref(false)

const selectedMedia = ref<any[]>([])
const selectedMediaIds = ref<number[]>([])

const mediaGallery = ref<string[]>([])

const calculateBundle = async () => {
    try {

        const payload = {
           products: bundleProductsPayload.value
        }

        const { data } = await axios.post(
            route(
                props.bundle_routes.calculate.name,
                props.bundle_routes.calculate.parameters
            ),
            payload
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
    fetchMediaGallery()
}

const flatMediaGallery = computed(() => {

    const result:any[] = []

    mediaGallery.value.forEach(product => {

        if(!product.image) return

        Object.entries(product.image).forEach(([imageId, imageData]:any) => {

            result.push({
                product_id: product.id,
                image_id: Number(imageId),
                url: imageData.original,
                image: imageData
            })

        })

    })
    console.log("result", result)
    return result
})

const toggleSelect = (img:any) => {

    const index = selectedMediaIds.value.indexOf(img.image_id)

    if(index !== -1){

        selectedMediaIds.value.splice(index,1)

        selectedMedia.value =
            selectedMedia.value.filter(m => m.image_id !== img.image_id)

    } else {

        selectedMediaIds.value.push(img.image_id)

        selectedMedia.value.push({
            image_id: img.image_id,
            product_id: img.product_id,
            url: img.url,
            image: img.image
        })

    }
}

const removeMedia = (media:any) => {
    selectedMedia.value =
        selectedMedia.value.filter(m => m.image_id !== media.image_id)
}

const generateAIDescription = async () => {
    try {
        isGeneratingAI.value = true

        const { data } = await axios.post(
            route(
                props.bundle_routes.ai.generate_description.name
            ),
            {
                prompt: bundleDescription.value
            }
        )

        bundleDescription.value = data.description

    } finally {
        isGeneratingAI.value = false
    }
}

const showGenerateModal = ref(false)
const aiPrompt = ref('')
const selectedMediaForAI = ref<any[]>([])

const generateAIImages = async () => {
    try {
        isGeneratingAI.value = true
        console.log("selectedMediaForai", selectedMediaForAI.value)
        
        const payload = {
            images: selectedMediaForAI.value.map(m => m.image_id),
            prompt: aiPrompt.value
        }

       const { data } = await axios.post(
            route(
                props.bundle_routes.ai.generate_images.name
            ),
            payload
        )

        selectedMedia.value.push(...data.images)

        showGenerateModal.value = false
        aiPrompt.value = ''
        selectedMediaForAI.value = []

    } finally {
        isGeneratingAI.value = false
    }
}

const productIds = computed(() => {
    return selectedProducts.value.map(p => p.id)
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

        console.log('MEDIA URL =', url)

        const response = await axios.get(url)

        console.log('MEDIA RESPONSE', response.data)

        mediaGallery.value = response.data.data || []
        console.log('mediaGallery', mediaGallery.value)
    } catch (e) {
        console.log(e)

        notify({
            title: trans('Error'),
            text: trans('Failed to load media'),
            type: 'error'
        })
    } finally {
        isLoadingMedia.value = false
    }
}

const onUpdateSelectedProducts = (products:any[]) => {
    selectedProducts.value = products.map(p => ({
        ...p,
        quantity: p.quantity_selected ?? p.quantity ?? 1
    }))
}

const isStoringBundle = ref(false)

const submitBundle = async () => {
    try {
        isStoringBundle.value = true

       const payload = {
            name: 'asd',
            code: 'r',
            description: bundleDescription,
            price: summary.value.total_bundle_price || 0,
            rrp: summary.value.total_rrp || 0,
            products: selectedProducts.value.map(p => ({
                product_id: p.id,
                quantity: p.quantity || 1
            }))
        }

        console.log('STORE BUNDLE PAYLOAD', payload)

        await axios.post(
            route(
                props.bundle_routes.store.name,
                props.bundle_routes.store.parameters
            ),
            payload
        )

        emits('onDone')

    } catch (e) {
        notify({
            title: trans('Error'),
            text: trans('Failed to create bundle'),
            type: 'error'
        })
    } finally {
        isStoringBundle.value = false
    }
}

const bundleProductsPayload = computed(() => {
    return selectedProducts.value.map(p => ({
        product_id: p.id,
        quantity: p.quantity || 1
    }))
})

const fileInput = ref<HTMLInputElement | null>(null)
const localUploadedFiles = ref<File[]>([])

const uploadFilesLocal = (files: FileList) => {
    Array.from(files).forEach(file => {
        localUploadedFiles.value.push(file)

        // preview
        console.log("file",file)
        selectedMedia.value.push(file)
        console.log("selectedMedia", selectedMedia.value)
    })
}

const selectedMediaAIIds = ref<any[]>([])
const toggleSelectAI = (media:any) => {

    const index = selectedMediaAIIds.value.indexOf(media.image_id)

    if(index !== -1){

        selectedMediaAIIds.value.splice(index,1)

        selectedMediaForAI.value =
            selectedMediaForAI.value.filter(
                m => m.image_id !== media.image_id
            )

    } else {

        selectedMediaAIIds.value.push(media.image_id)

        selectedMediaForAI.value.push(media)

    }
}


const openFilePicker = () => {
    fileInput.value?.click()
}

const onDrop = (e:DragEvent) => {
   if(!e.dataTransfer?.files?.length) return

   uploadFilesLocal(e.dataTransfer.files)
}

const onFileChange = (e:Event) => {
   const target = e.target as HTMLInputElement
//    if(!target.files?.length) return

   uploadFilesLocal(target.files)
}

watch(selectedProducts, () => {
    if(selectedProducts.value.length){
        calculateBundle()
    } else {
        summary.value = {
            total_price: 0,
            total_bundle_price: 0,
            total_rrp: 0,
            profit: 0,
            profit_percentage: 0
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
                    <span class="font-medium">{{ summary.total_price }}</span>
                </div>

                <div class="flex justify-between border-b pb-1">
                    <span class="text-gray-500">Bundle Price (-10%)</span>
                    <span class="font-medium text-green-600">{{ summary.total_bundle_price }}</span>
                </div>

                <div class="flex justify-between border-b pb-1">
                    <span class="text-gray-500">RRP</span>
                    <span class="font-medium">{{ summary.total_rrp }}</span>
                </div>

                <div class="flex justify-between pt-1">
                    <span class="text-gray-500">Profit</span>
                    <span class="font-semibold text-green-600"> [{{ summary.profit_percentage }}%] {{ summary.profit }}</span>
                </div>
            </div>
        </div>

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
                         <Button @click="step.current = 1" label="Next" iconRight="fal fa-arrow-right" :disabled="!selectedProducts.length"/>
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

                            <Button @click="generateAIDescription" :loading="isGeneratingAI" type="primary" :disabled="!bundleDescription">
                                <FontAwesomeIcon icon="fal fa-sparkles" class="mr-2" fixed-width />
                                Generate with AI
                            </Button>

                        </div>
                    </div>

                    <!-- MEDIA -->
                    <div class="mb-5">
                        <div
                            class="border-2 border-dashed border-gray-300 rounded-xl h-[140px]
                                flex flex-col items-center justify-center
                                text-gray-400 cursor-pointer hover:bg-gray-50 transition"
                            @dragover.prevent
                            @drop.prevent="onDrop"
                            @click="openFilePicker"
                        >

                            <FontAwesomeIcon icon='fal fa-upload'
                            class='!border-2 !rounded-full !p-2 !text-xl !text-muted-color' fixed-width
                            aria-hidden='true' />

                            <div class="text-sm font-medium">
                                Upload Media
                            </div>

                            <div class="text-xs">
                                Drag & drop images or click
                            </div>

                        </div>

                        <input
                            ref="fileInput"
                            type="file"
                            multiple
                            accept="image/*"
                            class="hidden"
                            @change="onFileChange"
                        />

                        <!-- ACTION -->
                        <div class="flex gap-2 mt-3">
                            <Button @click="openExistingMedia" type="secondary">
                                <FontAwesomeIcon :icon="faImages" class="mr-2" fixed-width />
                                Select existing media
                            </Button>

                            <Button @click="showGenerateModal = true" type="primary" icon="fal fa-arrow-left"  :disabled="!selectedMedia.length">
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

                        <div class="bg-gray-100 rounded-xl p-3 mt-1 grid grid-cols-3 gap-3 min-h-[110px]">
                            <div v-for="img in selectedMedia" class="relative group">
                                <Image
                                    :key="img.id"
                                    :src="img.image"
                                    class="h-24 w-full rounded-lg"
                                    imageCover
                                />

                                <button
                                    class="absolute top-1 right-1 bg-black/70 text-white text-xs px-1 rounded opacity-0 group-hover:opacity-100"
                                    @click="removeMedia(img)">
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- SUBMIT -->
                    <Button @click="submitBundle" class="flex justify-center items-center w-full" type="primary" :loading="isStoringBundle">
                        Create Bundle
                        <FontAwesomeIcon icon="fal fa-layer-group" class="mr-2" fixed-width />
                    </Button>

                </div>
                <!-- Modal Existing media -->
                <Dialog v-model:visible="showMediaModal" modal header="Select Images" :style="{ width: '600px' }">
                    <div v-if="isLoadingMedia" class="py-10 text-center">
                        <LoadingIcon />
                    </div>
                   <div v-else class="grid grid-cols-4 gap-3">

                        <div
                            v-for="img in flatMediaGallery"
                            :key="img.image_id"
                            class="relative aspect-square rounded-xl overflow-hidden border cursor-pointer group"
                            @click="toggleSelect(img)"
                        >

                            <!-- IMAGE -->
                            <div class="absolute inset-0 z-0">
                                <Image
                                    :src="img.image"
                                    class="w-full h-full"
                                    imageCover
                                />
                            </div>

                            <!-- DARK OVERLAY WHEN SELECTED -->
                            <div
                                v-if="selectedMediaIds.includes(img.image_id)"
                                class="absolute inset-0 bg-black/40 z-10"
                            />

                            <!-- CHECKBOX -->
                        <Checkbox
                            :modelValue="selectedMediaIds.includes(img.image_id)"
                            binary
                            class="absolute top-2 left-2 z-20 bg-white rounded shadow pointer-events-none"
                        />

                            <!-- HOVER EFFECT -->
                            <div
                                class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition z-5"
                            />

                        </div>

                    </div>

                    <template #footer>
                        <Button @click="showMediaModal = false" type="primary">
                            Done
                        </Button>
                    </template>

                </Dialog>

                <Dialog v-model:visible="showGenerateModal" header="Generate AI Image" modal :style="{ width: '600px' }">

                    <div class="mb-4">
                        <div class="text-sm font-semibold mb-2">
                            Select images of products you want to include in generated image
                        </div>

                       <div class="grid grid-cols-4 gap-3">

                            <div
                                v-for="media in selectedMedia"
                                :key="media.image_id"
                                class="relative aspect-square rounded-xl overflow-hidden border cursor-pointer group"
                                @click="toggleSelectAI(media)"
                            >

                            
                                <!-- IMAGE -->
                                <div class="absolute inset-0 z-0">
                                    <Image
                                        :src="media.image"
                                        class="w-full h-full"
                                        imageCover
                                    />
                                </div>

                                <!-- DARK OVERLAY -->
                                <div
                                    v-if="selectedMediaAIIds.includes(media.image_id)"
                                    class="absolute inset-0 bg-black/40 z-10"
                                />

                                <!-- CHECKBOX (visual only) -->
                                <Checkbox
                                    :modelValue="selectedMediaAIIds.includes(media.image_id)"
                                    binary
                                    class="absolute top-2 left-2 z-20 bg-white rounded shadow pointer-events-none"
                                />

                                <!-- HOVER -->
                                <div
                                    class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition"
                                />

                            </div>

                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="text-sm font-semibold mb-1">
                            Describe your image
                        </div>

                        <Textarea v-model="aiPrompt" rows="3" class="w-full" placeholder="Input description"/>
                    </div>

                    <template #footer>
                        <Button
                            label="Generate"
                            @click="generateAIImages"
                            :loading="isGeneratingAI"
                            :disabled="!selectedMediaForAI.length || !aiPrompt"
                        />
                    </template>

                </Dialog>
            </div>

        </KeepAlive>
    </div>
</template>


