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
import { InputText } from "primevue"
import { faLayerGroup, faSparkles, faTrash,faImages } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faLayerGroup, faSparkles, faTrash, faImages)

const layout: any = inject("layout", {})
const bundle = useBundle()

const step = ref<number>(1)
const bundleDescription = ref<string>('')
const mediaGallery = ref<string[]>([])
const selectedMedia = ref<any[]>([])
const selectedMediaIds = ref<number[]>([])
const selectedMediaAIIds = ref<any[]>([])
const selectedMediaForAI = ref<any[]>([])
const aiPrompt = ref<string>('')

const isGeneratingAI = ref<boolean>(false)
const isStoringBundle = ref<boolean>(false)
const showMediaModal =  ref<boolean>(false)
const isLoadingMedia =  ref<boolean>(false)
const showGenerateModal = ref<boolean>(false)

const bundleProductsPayload = computed(() => {
    return bundle.products.value.map(p => ({
        product_id: p.id,
        quantity: p.quantity || 1
    }))
})

const summary = ref({
    total_price: 0,
    total_bundle_price: 0,
    total_rrp: 0,
    profit: 0,
    profit_percentage: 0
})

const calculateBundle = async () => {
    try {

        const payload = {
           products: bundleProductsPayload.value
        }

        const { data } = await axios.post(
            route(
                layout.bundle_routes.calculate.name,
                layout.bundle_routes.calculate.parameters
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

const generateAITitle = async () => {
    try {
        isGeneratingAI.value = true

        const { data } = await axios.post(
            route(
                layout.bundle_routes.ai.generate_title.name
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


const generateAIDescription = async () => {
    try {
        isGeneratingAI.value = true

        const { data } = await axios.post(
            route(
                layout.bundle_routes.ai.generate_description.name
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

const generateAIImages = async () => {
    try {
        isGeneratingAI.value = true
        console.log("selectedMediaForai", selectedMediaForAI.value)
        
        const payload = {
            images: selectedMediaForAI.value.map(m => m.image_id),
            prompt: aiPrompt.value
        }

      const res = await axios.post(
            route(
                layout.bundle_routes.ai.generate_images.name
            ),
            payload
        )
        console.log("res data", res.data)
        const aiImages = res.data.data || []
        aiImages.forEach((img:any, i:number) => {

            const base64Url = `data:image/png;base64,${img.b64_json}`

            selectedMedia.value.push({
                id: `ai-${Date.now()}-${i}`,
                url: base64Url,
                image: { original: base64Url },
                is_ai: true,
                is_main: false
            })

        })

        showGenerateModal.value = false
        aiPrompt.value = ''
        selectedMediaForAI.value = []

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

const removeMedia = (media:any) => {
    selectedMedia.value =
        selectedMedia.value.filter(m => m.image_id !== media.image_id)
}

const fetchMediaGallery = async () => {
    try {
        isLoadingMedia.value = true

        const url = route(
            layout.bundle_routes.images.get.name,
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

// image action
const fileInput = ref<HTMLInputElement | null>(null)
const localUploadedFiles = ref<File[]>([])

const openFilePicker = () => {
    fileInput.value?.click()
}

const uploadFilesLocal = (files: FileList) => {
    Array.from(files).forEach(file => {
        localUploadedFiles.value.push(file)

        // preview
        console.log("file",file)
        selectedMedia.value.push(file)
        console.log("selectedMedia", selectedMedia.value)
    })
}

const onDrop = (e:DragEvent) => {
   if(!e.dataTransfer?.files?.length) return
   uploadFilesLocal(e.dataTransfer.files)
}

const onFileChange = (e:Event) => {
   const target = e.target as HTMLInputElement
   uploadFilesLocal(target.files)
}

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

const submitBundle = async () => {
    try {
        isStoringBundle.value = true

       const payload = {
            name: 'asd',
            code: 'r',
            description: bundleDescription,
            price: summary.value.total_bundle_price || 0,
            rrp: summary.value.total_rrp || 0,
            products: bundle.products.value.map(p => ({
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

        // close modal

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

watch(bundle.products, () => {
    if(bundle.products.value.length){
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

const debouncedCalculate = debounce(() => {
    bundle.calculateBundle(layout?.bundle_routes)
}, 400)

watch(
    bundle.products,
    () => debouncedCalculate(),
    { deep: true }
)
</script>

<template>
<Transition name="slide">
  <div
    v-if="bundle.open.value"
    class="h-screen bg-white flex flex-col"
  >
    <template v-if="bundle.step.value === 1">
        <!-- HEADER -->
        <div class="p-4 border-b flex justify-between items-center">
        <div class="font-semibold text-lg">
            Create Your Bundle
        </div>
        <button @click="bundle.close()">✕</button>
        </div>

        <!-- BODY -->
        <div class="flex-1 overflow-auto p-4">

            <div class="text-xs text-gray-400 mb-3">
                STEP {{ bundle.step.value }}/2
            </div>

          <div class="mb-4">
            <label class="text-sm font-semibold block mb-1">
                Bundle Title
            </label>

            <div class="relative">

                <InputText
                v-model="bundle.title.value"
                type="text"
                class="w-full pr-10 text-base p-2"
                :placeholder="ctrans('Bundle Title')"
                required
                />

                <!-- AI ICON BUTTON -->
                <button
                type="button"
                @click="generateAITitle"
                :disabled="isGeneratingAI"
                class="absolute right-2 top-1/2 -translate-y-1/2 
                        h-7 w-7 flex items-center justify-center 
                        rounded-md border bg-white hover:bg-gray-100 
                        transition shadow-sm"
                >
                <FontAwesomeIcon
                    icon="fal fa-sparkles"
                    class="text-xs"
                    :class="isGeneratingAI ? 'animate-pulse text-primary' : ''"
                    fixed-width
                />
                </button>

            </div>
            </div>

            <div
                v-for="item in bundle.products.value"
                :key="item.id"
                class="flex gap-3 py-3 border-b"
            >
                <img
                :src="item.web_images?.main?.gallery?.png"
                class="w-14 h-14 object-contain bg-gray-50 rounded"
                />

                <div class="flex-1">
                <div class="text-sm font-semibold">{{ item.name }}</div>
                <div class="text-green-600 font-semibold">{{ item.price }}</div>
                </div>

                <div class="flex items-center gap-2">
                <button @click="bundle.decreaseQty(item.id)">-</button>
                <div>{{ item.quantity }}</div>
                <button @click="bundle.increaseQty(item.id)">+</button>
                <button @click="bundle.removeProduct(item.id)">🗑</button>
                </div>
            </div>
            <div
            v-if="!bundle.products.value.length"
            class="text-center text-gray-400 text-sm py-10"
            >
            No products added yet
            </div>

        </div>

        <!-- FOOTER -->
        <div class="border-t p-4 space-y-2">

            <template v-if="bundle.isSummaryLoading.value">
            <div class="text-center text-sm text-gray-400 py-2">Calculating...</div>
            </template>

            <template v-else>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Cost Price</span>
                <span>{{ bundle.summary.value.total_price }}</span>
            </div>

            <div class="flex justify-between text-sm">
                <span class="text-gray-500">RRP</span>
                <span>{{ bundle.summary.value.total_rrp }}</span>
            </div>

            <div class="flex justify-between text-sm font-semibold text-green-600">
                <span>Bundle Price</span>
                <span>{{ bundle.summary.value.total_bundle_price }}</span>
            </div>

            <div class="flex justify-between text-xs text-gray-400">
                <span>Profit</span>
                <span>{{ bundle.summary.value.profit }} ({{ bundle.summary.value.profit_percentage }}%)</span>
            </div>
            </template>

            <button :disabled="!bundle.products.value.length" @click="bundle.step.value = 2" class="w-full bg-black text-white p-3">
                Next
            </button>

        </div>
    </template>
    <!-- UI Step 2 -->
    <template v-if="bundle.step.value === 2">
        <div class="w-full p-3">

                    <!-- BACK -->
                    <div class="mb-3">
                        <Button @click="bundle.step.value = 1" type="tertiary" icon="fal fa-arrow-left"
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
                                Characters {{ bundleDescription.length }}/300
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
    </template>
  </div>
</Transition>
</template>

<style>
.slide-enter-from { transform: translateX(100%) }
.slide-enter-to   { transform: translateX(0) }
.slide-leave-from { transform: translateX(0) }
.slide-leave-to   { transform: translateX(100%) }
.slide-enter-active,
.slide-leave-active { transition: all .25s ease; }
</style>