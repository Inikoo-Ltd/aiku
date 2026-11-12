<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import { bannerVideo } from "@/Composables/bannerVideo"
import InputText from "primevue/inputtext"
import { computed } from "vue"

const props = defineProps<{
    modelValue: string | null
}>()

const emit = defineEmits(["update:modelValue"])

const isUnplayable = computed(() => !!props.modelValue?.trim() && !bannerVideo(props.modelValue))
</script>

<template>
    <div class="space-y-2">
        <InputText :modelValue="props.modelValue || ''" @update:modelValue="(value) => emit('update:modelValue', value)"
            :placeholder="ctrans('Paste video link')" class="w-full" size="small" :invalid="isUnplayable" />

        <p v-if="isUnplayable" class="text-xs text-red-500">
            {{ ctrans("This link cannot be played. Use a YouTube or Vimeo link, or a link to an .mp4 file.") }}
        </p>

        <div class="text-xs text-gray-500 leading-relaxed space-y-1">
            <p class="font-medium text-gray-600">{{ ctrans("Paste any of these:") }}</p>
            <ul class="list-disc list-inside space-y-1">
                <li>{{ ctrans("YouTube link, e.g. https://www.youtube.com/watch?v=… or https://youtu.be/…") }}</li>
                <li>{{ ctrans("Vimeo link, e.g. https://vimeo.com/123456789 (the video must allow embedding)") }}</li>
                <li>{{ ctrans("Direct video file, e.g. https://domain.com/video.mp4") }}</li>
            </ul>
            <p class="text-gray-400">{{ ctrans("Videos play muted and on a loop.") }}</p>
        </div>
    </div>
</template>
