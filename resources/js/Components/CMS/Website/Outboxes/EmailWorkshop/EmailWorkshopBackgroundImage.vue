<script setup lang="ts">
import { computed } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faImage, faTrashAlt } from '@fal'
import EmailWorkshopField from './EmailWorkshopField.vue'
import { backgroundImageUrl, setBackgroundImage } from './emailWorkshopBlocks'
import { ctrans } from '@/Composables/useTrans'

library.add(faImage, faTrashAlt)

const props = defineProps<{
    target: Record<string, any>
    label: string
}>()

const emits = defineEmits<{
    (e: 'pick'): void
}>()

const imageUrl = computed({
    get: () => backgroundImageUrl(props.target['background-image']),
    set: (url: string) => setBackgroundImage(props.target, url ?? ''),
})

const isRepeated = computed({
    get: () => (props.target['background-repeat'] ?? 'no-repeat') !== 'no-repeat',
    set: (repeats: boolean) => {
        props.target['background-repeat'] = repeats ? 'repeat' : 'no-repeat'
    },
})

const backgroundSize = computed({
    get: () => props.target['background-size'] ?? 'auto',
    set: (size: string) => {
        props.target['background-size'] = size
    },
})

const backgroundPosition = computed({
    get: () => props.target['background-position'] ?? 'top left',
    set: (position: string) => {
        props.target['background-position'] = position
    },
})

const sizeOptions = [
    { label: ctrans('Fill the area'), value: 'cover' },
    { label: ctrans('Fit inside'), value: 'contain' },
    { label: ctrans('Original size'), value: 'auto' },
]

const positionOptions = [
    { label: ctrans('Center'), value: 'center' },
    { label: ctrans('Top left'), value: 'top left' },
    { label: ctrans('Top center'), value: 'top center' },
    { label: ctrans('Top right'), value: 'top right' },
    { label: ctrans('Bottom center'), value: 'bottom center' },
]

const removeImage = () => {
    props.target['background-image'] = 'none'
}
</script>

<template>
    <div class="border-b border-gray-100 py-2.5">
        <span class="text-[13px] text-gray-600">{{ label }}</span>
        <div class="my-2 flex h-24 items-center justify-center overflow-hidden rounded border border-dashed border-gray-300 bg-gray-50 bg-cover bg-center"
            :style="imageUrl ? { backgroundImage: target['background-image'] } : {}">
            <span v-if="!imageUrl" class="text-xs text-gray-400">{{ ctrans('No image') }}</span>
        </div>
        <div class="flex gap-2">
            <button type="button" class="flex flex-1 items-center justify-center gap-1.5 rounded bg-[var(--theme-color-4)] py-2 text-[13px] font-medium text-[var(--theme-color-5)] hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)]"
                @click="emits('pick')">
                <FontAwesomeIcon icon="fal fa-image" fixed-width aria-hidden="true" />
                {{ imageUrl ? ctrans('Change image') : ctrans('Upload or browse image') }}
            </button>
            <button v-if="imageUrl" type="button" v-tooltip="ctrans('Remove image')" :aria-label="ctrans('Remove image')"
                class="shrink-0 rounded border border-gray-300 px-3 text-red-500 hover:bg-gray-50"
                @click="removeImage">
                <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
            </button>
        </div>
        <EmailWorkshopField v-model="imageUrl" :label="ctrans('Image URL')" />
        <template v-if="imageUrl">
            <EmailWorkshopField v-model="backgroundSize" type="select" :options="sizeOptions" :label="ctrans('Image size')" />
            <EmailWorkshopField v-model="backgroundPosition" type="select" :options="positionOptions" :label="ctrans('Image position')" />
            <EmailWorkshopField v-model="isRepeated" type="toggle" :label="ctrans('Repeat image')" />
        </template>
    </div>
</template>
