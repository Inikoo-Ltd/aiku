<script setup lang="ts">
import { useBundle } from '@/Composables/useBundle';
import { onMounted, ref, watch, computed, inject } from 'vue'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import axios from 'axios'
import { routeType } from '@/types/route'
import { route } from 'ziggy-js'
import { debounce } from 'lodash-es'
import Button from '../Elements/Buttons/Button.vue';
import { InputText, Select, Dialog, Textarea, Checkbox } from "primevue"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import InformationIcon from '../Utils/InformationIcon.vue';
import { faLayerGroup, faSparkles, faTrash, faImages, faSpinner } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faLayerGroup, faSparkles, faTrash, faImages, faSpinner)
import { router } from '@inertiajs/vue3';
import { useIrisLayoutStore } from "@/Stores/irisLayout"
import Image from '../Image.vue';

const props = defineProps<{
    layout: string
}>()

const layout = useIrisLayoutStore()

const mediaGallery = ref<string[]>([])
const selectedMedia = ref<any[]>([])
const selectedMediaIds = ref<number[]>([])
const selectedMediaAIIds = ref<any[]>([])
const selectedMediaForAI = ref<any[]>([])
const aiPrompt = ref<string>('')

const isGeneratingAI = ref<boolean>(false)
const isStoringBundle = ref<boolean>(false)
const showMediaModal = ref<boolean>(false)
const isLoadingMedia = ref<boolean>(false)
const showGenerateModalAI = ref<boolean>(false)
const customerChannelsId = ref<string | null>(null)

const customerChannelOptions = computed(() => {
    const data = layout?.user.customerSalesChannels

    return data ? Object.values(data) : []
})

const resolveParams = (config: any) => {
    if (!config) return {}

    if (typeof config.getParameters === 'function') {
        return config.getParameters()
    }

    return config.parameters || {}
}

const generateAIImages = async () => {
    try {
        isGeneratingAI.value = true

        const payload = {
            images: selectedMediaForAI.value.map(m => m.image_id),
            prompt: aiPrompt.value
        }

        const routeConfig = bundleRoutes.ai.generate_images
        
        const routeParams = {
            ...resolveParams(routeConfig),
            product: bundle.product_id.value
        }

        const res = await axios.post(
            route(
                routeConfig.name,
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
                image: media.thumbnail?.original || media.source?.original,
                is_ai: true,
                is_main: false
            })

        }

        showGenerateModalAI.value = false

        aiPrompt.value = ''
        selectedMediaForAI.value = []

        notify({
            title: 'AI Image Generated',
            type: 'success'
        })

    } catch (e) {
        console.error("e", e)
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

const openExistingMedia = async () => {
    showMediaModal.value = true
    fetchMediaGallery()
}

const removeMedia = (media: any) => {
    selectedMedia.value =
        selectedMedia.value.filter(m => m.image_id !== media.image_id)
}

const fetchMediaGallery = async () => {
    try {
        isLoadingMedia.value = true

        const routeConfig = bundleRoutes.images.get
        const url = route(
            routeConfig.name,
            {
                ...resolveParams(routeConfig),
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

// image action
const fileInput = ref<HTMLInputElement | null>(null)

const openFilePicker = () => {
    fileInput.value?.click()
}

const uploadFilesLocal = async (files: FileList) => {
    if (!bundle.product_id.value) {
        notify({
            title: trans('Error'),
            text: trans('Reload Pages'),
            type: 'error'
        })
    }

    try {
        const formData = new FormData()

        Array.from(files).forEach(file => {
            formData.append('images[]', file)
        })

        const routeConfig = bundleRoutes.images.store
        
        const routeParams = {
            ...resolveParams(routeConfig),
            product: bundle.product_id.value
        }

        const res = await axios.post(
            route(
                routeConfig.name, routeParams
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
                image: media.source.original,
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

const onDrop = (e: DragEvent) => {
    if (!e.dataTransfer?.files?.length) return
    uploadFilesLocal(e.dataTransfer.files)
}

const onFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement
    if (!target.files) return
    uploadFilesLocal(target.files)
}

const setMainImage = (imageId: number) => {
    selectedMedia.value = selectedMedia.value.map(img => ({
        ...img,
        is_main: img.image_id === imageId
    }))
}

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

const handleStoreBundle = async () => {
    try {
        await bundle.storeBundle()

        bundle.step.value = 2

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
    try {
        isStoringBundle.value = true

        const payload = {
            description: bundle.description.value,
            images: selectedMedia.value.map(img => ({
                id: img.image_id,
                is_main: img.is_main
            }))
        }

        const routeConfig = bundleRoutes.update

        const routeParams = {
            ...resolveParams(routeConfig),
            bundle: bundle.bundle_id.value
        }

        router.patch(
            route(
                routeConfig.name,
                routeParams
            ),
            payload,
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    notify({
                        title: trans('Success'),
                        text: trans('Success submit bundle'),
                        type: 'success'
                    })

                    bundle.step.value = 1
                    bundle.close()
                }
            }
        )

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

const bundle = useBundle({
    calculate: {
        name: 'iris.models.dropshipping.bundles.products.calculate',
        getParameters: () => ({
            customerSalesChannel: customerChannelsId.value
        })
    },
    ai: {
        generate_title: {
            name: 'iris.models.dropshipping.bundles.title.generate',
        },
        generate_description: {
            name: 'iris.models.dropshipping.bundles.description.generate',
        },
        generate_images: {
            name: 'iris.models.dropshipping.bundles.products.images.generate',
            getParameters: () => ({
                customerSalesChannel: customerChannelsId.value
            })
        }
    },
    store: {
        name: 'iris.models.dropshipping.bundles.store',
        getParameters: () => ({
            customerSalesChannel: customerChannelsId.value
        })
    },
    update: {
        name: 'iris.models.dropshipping.bundles.update',
        getParameters: () => ({
            customerSalesChannel: customerChannelsId.value
        })
    },
    images: {
        get: {
            name: 'iris.catalogue.products.images.index',
        },
        store: {
            name: 'iris.models.dropshipping.bundles.products.images.store',
            getParameters: () => ({
                customerSalesChannel: customerChannelsId.value
            })
        }
    },
})

const bundleRoutes = bundle.bundleRoutes

watch(customerChannelsId, (val) => {
    if (val) {
        bundle.calculateBundle()
    }
})
</script>

<template>
    <Transition name="slide">
        <div v-if="bundle.open.value" class="bg-white flex flex-col min-h-0 h-full rounded-md overflow-auto">
            <template v-if="bundle.step.value === 1">
                <!-- HEADER -->
                <div class="p-4 border-b flex justify-between items-center">
                    <div class="font-semibold text-lg">
                        Create Your Bundle
                    </div>
                    <button @click="bundle.close()">✕</button>
                </div>

                <!-- BODY -->
                <div class="flex-1 overflow-auto min-h-0 p-4">

                    <div class="text-xs text-gray-400 mb-3">
                        STEP {{ bundle.step.value }}/2
                    </div>
                    <div class="mb-4">
                        <label class="block mb-2 text-sm font-semibold">
                            Sales Channel
                        </label>

                        <Select v-model="customerChannelsId" :options="customerChannelOptions"
                            optionValue="customer_sales_channel_id" optionLabel="customer_sales_channel_name"
                            placeholder="Choose Customer Sales Channel" checkmark class="w-full">
                            <template #loadingicon>
                                <LoadingIcon />
                            </template>
                        </Select>
                    </div>
                    <div class="mb-4">
                        <label class="text-sm font-semibold block mb-1">
                            Bundle Title
                        </label>

                        <div class="relative">

                            <InputText v-model="bundle.title.value" type="text" class="w-full pr-10 text-base p-2"
                                :placeholder="ctrans('Bundle Title')" required />

                            <!-- AI ICON BUTTON -->
                            <Button type="button" @click="bundle.generateAITitle" :tooltip="trans('Generate AI')"  :loading="bundle.isGeneratingAI.value"
                                :disabled="isGeneratingAI || !bundle.products.value.length" class="absolute right-2 top-1/2 -translate-y-1/2 
                        h-7 w-7 flex items-center justify-center 
                        rounded-md border bg-white hover:bg-gray-100 
                        transition shadow-sm">
                                <FontAwesomeIcon :icon="bundle.isGeneratingAI.value ? 'fal fa-spinner' : 'fal fa-sparkles'"
                                    class="text-xs" :class="bundle.isGeneratingAI.value ? 'animate-pulse text-primary' : ''"
                                    fixed-width />
                            </Button>

                        </div>
                    </div>

                    <div v-for="item in bundle.products.value" :key="item.id" class="flex gap-3 py-3 border-b">
                        <img :src="item.web_images?.main?.gallery?.png"
                            class="w-14 h-14 object-contain bg-gray-50 rounded" />

                        <div class="flex-1">
                            <div class="text-sm font-semibold">{{ item.name }}</div>
                            <div class="flex gap-2">
                                <InformationIcon :information="trans('Individual purchased price')" />
                                <div class="font-semibold text-sm line-through">{{ item.price_per_unit }} {{
                                    props.layout }}</div>
                                <div class="font-semibold text-green-600">{{ item.price }} {{ props.layout }}</div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button @click="bundle.decreaseQty(item.id)">-</button>
                            <div>{{ item.quantity }}</div>
                            <button @click="bundle.increaseQty(item.id)">+</button>

                            <button @click="bundle.removeProduct(item.id)" v-tooltip="trans('Delete product')">🗑
                                <FontAwesomeIcon icon="fal fa-layer-group" class="text-gray-500" fixed-width />
                            </button>
                        </div>
                    </div>
                    <div v-if="!bundle.products.value.length" class="text-center text-gray-400 text-sm py-10">
                        No products added yet
                    </div>

                </div>

                <!-- FOOTER -->
                <div class="border-t p-4 space-y-2">
                    <small v-if="!customerChannelsId" class="text-red-500">Please Choose Customer Sales Channel First
                        For Calculate
                        Bundle</small>
                    <template v-if="bundle.isSummaryLoading.value">
                        <div class="text-center text-sm text-gray-400 py-2">Calculating...</div>
                    </template>

                    <template v-else>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Cost Price</span>
                            <span>{{ bundle.summary.value.total_price }} {{ props.layout }}</span>
                        </div>

                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">RRP</span>
                            <span>{{ bundle.summary.value.total_rrp }} {{ props.layout }}</span>
                        </div>

                        <div class="flex justify-between text-sm font-semibold text-green-600">
                            <span>Bundle Price</span>
                            <span>{{ bundle.summary.value.total_bundle_price }} {{ props.layout }}</span>
                        </div>

                        <div class="flex justify-between text-xs text-gray-400">
                            <span>Profit</span>
                            <span>{{ bundle.summary.value.profit }} {{ props.layout }} ({{
                                bundle.summary.value.profit_percentage }}%)</span>
                        </div>
                    </template>

                    <Button @click="handleStoreBundle" :loading="bundle.isStoringBundle" label="Next"
                        iconRight="fas fa-arrow-right"
                        :disabled="!bundle.products.value.length || !bundle.title.value.length || !customerChannelsId"
                        class="w-full text-white rounded" />

                </div>
            </template>
            <!-- UI Step 2 -->
            <template v-if="bundle.step.value === 2">
                <div class="w-full p-3 h-full overflow-auto">
                    <!-- HEADER -->
                    <div class="mb-5">
                        <div class="text-xl font-semibold flex items-center justify-between gap-2">
                            <div>Create Your Bundle
                                <FontAwesomeIcon v-tooltip="trans('Bundle generator')" icon="fal fa-layer-group"
                                    class="text-gray-500" fixed-width />
                            </div>
                            <button @click="bundle.close()">✕</button>
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

                        <Textarea v-model="bundle.description.value" rows="6" autoResize class="w-full mt-1"
                            placeholder="Input your description" />

                        <div class="flex justify-between items-center mt-2">

                            <div class="text-xs text-gray-400">
                                Characters {{ bundle.description.value.length }} words
                            </div>

                            <Button @click="bundle.generateAIDescription" :loading="bundle.isGeneratingAI.value" type="primary"
                                :disabled="!productIds.length">
                                <FontAwesomeIcon :icon="bundle.isGeneratingAI.value ? 'fal fa-spinner' : 'fas fa-sparkles'"
                                    class="mr-2" fixed-width />
                                Generate with AI
                            </Button>

                        </div>
                    </div>

                    <!-- MEDIA -->
                    <div class="mb-5">
                        <div class="border-2 border-dashed border-gray-300 rounded-xl h-[140px]
                                flex flex-col items-center justify-center
                                text-gray-400 cursor-pointer hover:bg-gray-50 transition" @dragover.prevent
                            @drop.prevent="onDrop" @click="openFilePicker">

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

                        <input ref="fileInput" type="file" multiple accept="image/*" class="hidden"
                            @change="onFileChange" />

                        <!-- ACTION -->
                        <div class="flex gap-2 mt-3">
                            <Button @click="openExistingMedia" type="secondary">
                                <FontAwesomeIcon :icon="faImages" class="mr-2" fixed-width />
                                Select existing media
                            </Button>

                            <Button @click="showGenerateModalAI = true" type="primary" icon="fal fa-arrow-left"
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

                        <div class="bg-gray-100 rounded-xl p-3 mt-1 grid grid-cols-3 gap-3 min-h-[110px]">
                            <div v-for="img in selectedMedia" class="relative group">
                                <Image :key="img.id" :src="img.image" class="h-24 w-full rounded-lg" imageCover />

                                <input type="radio" name="main_image" :checked="img.is_main"
                                    @change="setMainImage(img.image_id)" class="absolute top-2 left-2 z-20" />

                                <div v-if="img.is_main"
                                    class="absolute bottom-1 left-1 text-[10px] bg-black/70 text-white px-1 rounded">
                                    MAIN IMAGE
                                </div>
                                <button
                                    class="absolute top-1 right-1 bg-black/70 text-white text-xs px-1 rounded opacity-0 group-hover:opacity-100"
                                    @click="removeMedia(img)">
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- SUBMIT -->
                    <Button @click="submitBundle" :disabled="!bundle.description.value.length"
                        class="flex justify-center items-center w-full" type="primary" :loading="isStoringBundle">
                        Create Bundle
                        <FontAwesomeIcon icon="fas fa-layer-group" class="mr-2" fixed-width />
                    </Button>

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

                <Dialog v-model:visible="showGenerateModalAI" header="Generate AI Image" modal
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
                        <Button label="Generate" @click="generateAIImages" :loading="isGeneratingAI"
                            :disabled="!selectedMediaForAI.length || !aiPrompt" />
                    </template>

                </Dialog>
            </template>
        </div>
    </Transition>
</template>

<style>
.slide-enter-from {
    transform: translateX(100%)
}

.slide-enter-to {
    transform: translateX(0)
}

.slide-leave-from {
    transform: translateX(0)
}

.slide-leave-to {
    transform: translateX(100%)
}

.slide-enter-active,
.slide-leave-active {
    transition: all .25s ease;
}
</style>
