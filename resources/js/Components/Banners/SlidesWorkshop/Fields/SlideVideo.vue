<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { ref, watch } from "vue"
import { faUpload } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'

library.add(faUpload)

const props = defineProps<{
    modelValue: string | null
}>()

const emit = defineEmits(["update:modelValue"])

const videoUrl = ref(props.modelValue || '')

watch(
    () => props.modelValue,
    (val) => {
        videoUrl.value = val || ''
    }
)

const updateValue = (val: string) => {
    videoUrl.value = val
    emit("update:modelValue", val)
}

</script>

<template>
    <div class="space-y-2">

        <input type="text" :value="props.modelValue || ''" @input="updateValue($event.target.value)"
            placeholder="Paste video URL" class="w-full border rounded px-3 py-2 text-sm" />

        <div class="text-xs text-gray-500 leading-relaxed space-y-1">
            <p class="font-medium text-gray-600">Supported formats:</p>

            <ul class="list-disc list-inside space-y-1">
                <li>
                    <strong>HTML Video</strong> → URL direct file
                    <br />
                    <span class="text-gray-400">
                        Example: https://domain.com/video.mp4
                    </span>
                </li>

                <li>
                    <strong>Iframe Embed</strong> → URL embed player
                    <br />
                    <span class="text-gray-400">
                        Example: https://www.youtube.com/embed/VIDEO_ID
                    </span>
                </li>
            </ul>

            <p class="text-red-400">
                Links like youtube.com/watch?v=... or regular Google Drive shares cannot be displayed.
            </p>
        </div>

    </div>
</template>
