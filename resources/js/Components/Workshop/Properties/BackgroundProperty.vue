<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { ref } from 'vue'
import Image from '@/Components/Image.vue'
import ImageUploadWithCroppedFunction from '@/Components/ImageUploadWithCroppedFunction.vue'
import PureRadio from '@/Components/Pure/PureRadio.vue'
import ColorPicker from '@/Components/Utils/ColorPicker.vue'
import Dialog from 'primevue/dialog'
import RadioButton from 'primevue/radiobutton'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import ColorGradientPicker from '@/Components/Utils/ColorGradientPicker.vue'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faImage, faMinus, faPalette } from '@fal'
import { faImage as fasImage } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { routeType } from '@/types/route'
import { ImageData } from '@/types/Image'
import InputNumber from 'primevue/inputnumber';
import { faPlus } from '@far'


library.add(faImage, faPalette, fasImage)

interface BackgroundProperty {
    type: string // 'color', 'image', 'gradient'
    color: string
    image: ImageData
    gradient?: {
        type: string
        angle: string
        colors: string[]
        value: string
    }
    repeat?: string
    size?: string
    positionX?: string
    positionY?: string
}

const props = defineProps<{
    uploadImageRoute?: routeType
}>()

const emits = defineEmits<{
    (e: 'update:modelValue', value: BackgroundProperty): void
}>()

const model = defineModel<BackgroundProperty>({
    required: true,
    default: {
        type: 'color'
    }
})

const isOpenGallery = ref(false)

const closeUploadImage = (visible: boolean) => {
    isOpenGallery.value = visible
}

const imageSettings = {
    key: ['image', 'source'],
    stencilProps: {
        aspectRatio: null,
        movable: true,
        scalable: true,
        resizable: true,
    },
}

const repeatOptions = [
    { label: 'No Repeat', value: 'no-repeat' },
    { label: 'Repeat', value: 'repeat' },
    { label: 'Repeat X', value: 'repeat-x' },
    { label: 'Repeat Y', value: 'repeat-y' },
]

const sizeOptions = [
    { label: 'Auto', value: 'auto' },
    { label: 'Cover', value: 'cover' },
    { label: 'Contain', value: 'contain' },
    { label: 'Custom', value: 'custom' },
]

</script>

<template>
    <div v-if="model?.type"
        class="grid grid-cols-2 items-center justify-between gap-x-3 flex-wrap px-6 w-full relative">
        <!-- === IMAGE BACKGROUND === -->
        <div class="relative flex items-center gap-x-2 py-1" v-tooltip="trans('Image background')">
            <div class="group rounded-md relative shadow-lg border border-gray-300">
                <div class="relative h-12 w-12 cursor-pointer rounded overflow-hidden">
                    <Image v-if="model?.image?.source" :src="model?.image?.source" :alt="'background image'"
                        :imageCover="true" class="h-full" />
                    <div v-else class="h-full flex items-center justify-center">
                        <FontAwesomeIcon icon="fas fa-image" fixed-width aria-hidden="true" />
                    </div>

                    <div v-if="model?.type === 'image'" @click="() => (isOpenGallery = true)"
                        class="hidden group-hover:flex absolute inset-0 bg-black/30 items-center justify-center cursor-pointer">
                        <FontAwesomeIcon icon="fal fa-image" class="text-white" fixed-width aria-hidden="true" />
                    </div>

                    <div v-else @click="() => (model.type = 'image', emits('update:modelValue', model))"
                        class="flex absolute inset-0 bg-gray-200/70 hover:bg-gray-100/40 items-center justify-center cursor-pointer" />
                </div>
            </div>
            <PureRadio v-model="model.type" @update:modelValue="() => emits('update:modelValue', model)"
                :options="[{ name: 'image' }]" by="name" key="image1" />
        </div>

        <!-- === COLOR BACKGROUND === -->
        <div class="flex items-center gap-x-4 h-min" v-tooltip="trans('Color background')">
            <div class="relative h-12 aspect-square rounded-md shadow">
                <ColorPicker :color="model.color || '#111111'" @changeColor="(newColor) => {
                    model.color = `rgba(${newColor.rgba.r}, ${newColor.rgba.g}, ${newColor.rgba.b}, ${newColor.rgba.a})`
                    model.type = 'color'
                    emits('update:modelValue', model)
                }" closeButton :isEditable="!model.color?.includes('var')">
                    <template #button>
                        <div class="group relative h-12 w-12 overflow-hidden rounded"
                            :style="{ backgroundColor: model.color }">
                            <div
                                class="hidden group-hover:flex absolute inset-0 bg-black/30 items-center justify-center cursor-pointer">
                                <FontAwesomeIcon icon="fal fa-palette" class="text-white" fixed-width
                                    aria-hidden="true" />
                            </div>
                        </div>
                    </template>

                    <template #before-main-picker>
                        <div class="flex items-center gap-2">
                            <RadioButton size="small" v-model="model.color" inputId="bg-color-picker-1"
                                name="bg-color-picker" value="var(--iris-color-primary)" />
                            <label class="cursor-pointer" for="bg-color-picker-1">
                                {{ trans('Primary color') }}
                            </label>
                        </div>

                        <div class="flex items-center gap-2">
                            <RadioButton size="small" :modelValue="!model.color?.includes('var') ? '#111111' : null"
                                @update:modelValue="(e) => model.color.includes('var') ? (model.color = '#111111', emits('update:modelValue', model)) : false"
                                inputId="bg-color-picker-3" name="bg-color-picker" value="#111111" />
                            <label class="cursor-pointer" for="bg-color-picker-3">{{ trans('Custom solid') }}</label>
                        </div>
                    </template>
                </ColorPicker>

                <div v-if="model.type !== 'color'"
                    @click="() => (model.type = 'color', emits('update:modelValue', model))"
                    class="flex absolute inset-0 items-center justify-center cursor-pointer" />
            </div>
            <PureRadio v-model="model.type" @update:modelValue="() => emits('update:modelValue', model)"
                :options="[{ name: 'color' }]" by="name" key="color2" />
        </div>

        <!-- === GRADIENT BACKGROUND === -->
        <div class="col-span-2 flex items-center gap-x-4">
            <Popover class="relative" v-slot="{ open: isOpen, close }">
                <PopoverButton>
                    <div class="group relative h-12 w-28 rounded-md overflow-hidden ring-1 ring-gray-100 ring-inset"
                        :style="{ background: model?.gradient?.value || 'linear-gradient(45deg, rgba(20, 20, 20, 1), rgba(240, 240, 240, 1))' }">
                        <div
                            class="hidden group-hover:flex absolute inset-0 bg-black/30 items-center justify-center cursor-pointer">
                            <FontAwesomeIcon icon="fal fa-palette" class="text-white" fixed-width aria-hidden="true" />
                        </div>
                    </div>
                </PopoverButton>

                <Transition name="headlessui">
                    <PopoverPanel
                        class="top-[100%] absolute z-10 left-0 bg-white shadow-lg border border-gray-300 rounded-md p-4 w-72">
                        <ColorGradientPicker :data="model?.gradient" @onChange="(e) => {
                            model.type = 'gradient'
                            model.gradient = e
                            emits('update:modelValue', model)
                        }" />
                    </PopoverPanel>
                </Transition>
            </Popover>

            <PureRadio v-model="model.type" @update:modelValue="() => emits('update:modelValue', model)"
                :options="[{ name: 'gradient' }]" by="name" key="color_gradient" />
        </div>
    </div>

    <!-- === IMAGE SETTINGS SECTION === -->
    <div v-if="model.type === 'image'" class="col-span-2 px-6 mt-3 space-y-3">
        <div>
            <label class="block text-sm font-medium mb-1">Background Repeat</label>
            <PureMultiselect v-model="model.repeat" :options="repeatOptions" label="label" track-by="value"
                :mode="'single'" @update:modelValue="() => emits('update:modelValue', model)" />
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Background Size</label>
            <InputNumber 
                v-model="model.size" 
                suffix="%" 
                placeholder="e.g. center, 50%" 
                inputClass="w-[80%]" 
                :max="100"
                @input="() => emits('update:modelValue', model)" 
                buttonLayout="horizontal" 
                showButtons
            >
                <template #incrementbuttonicon>
                   <FontAwesomeIcon :icon="faPlus" />
                </template>
                <template #decrementbuttonicon>
                   <FontAwesomeIcon :icon="faMinus" />
                </template>
            </InputNumber>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Position X</label>
            <InputNumber 
                v-model="model.positionX" 
                placeholder="e.g. center, 50%" 
                suffix="%"
                @input="() => emits('update:modelValue', model)" 
                buttonLayout="horizontal"
                showButtons  
                inputClass="w-[80%]" 
                :max="100"
            >
                <template #incrementbuttonicon>
                   <FontAwesomeIcon :icon="faPlus" />
                </template>
                <template #decrementbuttonicon>
                   <FontAwesomeIcon :icon="faMinus" />
                </template>
            </InputNumber>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Position Y</label>
            <InputNumber 
                v-model="model.positionY" 
                placeholder="e.g. top, 50%" 
                suffix="%"
                buttonLayout="horizontal" 
                showButtons  
                inputClass="w-[80%]" 
                :max="100"
                @value-change="() => emits('update:modelValue', model)"
            >
             <template #incrementbuttonicon>
                   <FontAwesomeIcon :icon="faPlus" />
                </template>
                <template #decrementbuttonicon>
                   <FontAwesomeIcon :icon="faMinus" />
                </template>
            </InputNumber>
        </div>
    </div>

    <!-- === DIALOG FOR IMAGE UPLOAD === -->
    <Dialog v-model:visible="isOpenGallery" modal header="Select Background Image" class="w-[75vw] max-w-5xl">
        <ImageUploadWithCroppedFunction @dialog="(visible) => closeUploadImage(visible)" @update:model-value="(val) => {
            model.image = { source: val }
            emits('update:modelValue', model)
        }" :stencilProps="imageSettings.stencilProps" :uploadRoutes="uploadImageRoute" :maxSelected="1"
            :multiple="false" />
    </Dialog>
</template>
