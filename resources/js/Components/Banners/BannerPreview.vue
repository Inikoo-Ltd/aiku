<script setup lang="ts">
import { computed } from 'vue'
import { useRangeFromNow } from '@/Composables/useFormatTime'
import SliderLandscape from "@/Components/Banners/Slider/SliderLandscape.vue"
import SliderSquare from "@/Components/Banners/Slider/SliderSquare.vue"
import Image from '@/Components/Image.vue'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    data: {
        type: string
        compiled_layout: any
        ratio?: string
        published_snapshot?: {
            publisher?: string
            publisher_avatar?: string
            comment?: string
            published_at?: string
        }
    }
}>()

const aspectClass = computed(() => {
    const type = props.data?.type
    const ratio = props.data?.ratio

    if (!ratio) {
        return type === 'landscape'
            ? 'aspect-[4/1]'
            : 'aspect-square'
    }

    if (typeof ratio === 'string' && ratio.includes('/')) {
        return `aspect-[${ratio}]`
    }

    const numeric = Number(ratio)
    if (!isNaN(numeric) && numeric > 0) {
        const width = 100
        const height = Math.round(width / numeric)
        return `aspect-[${width}/${height}]`
    }

    return type === 'landscape'
        ? 'aspect-[4/1]'
        : 'aspect-square'
})

const publishedAgo = computed(() => {
    const publishedAt = props.data?.published_snapshot?.published_at
    if (!publishedAt) return null
    return `${useRangeFromNow(publishedAt)} ago`
})
</script>

<template>
    <div v-if="data.published_snapshot" class="w-full bg-white flex items-center justify-between py-3 px-4 border-b min-w-44">
        <div class="flex gap-2 items-center min-w-0">
            <div v-if="data?.published_snapshot?.publisher_avatar"
                class="h-6 w-6 rounded-full overflow-hidden ring-1 ring-gray-300 shrink-0">
                <Image :src="data.published_snapshot.publisher_avatar" />
            </div>

            <div v-if="data.published_snapshot?.publisher" class="font-semibold text-sm truncate">
                {{ data.published_snapshot.publisher }}
            </div>

            <div v-else class="text-gray-400 italic text-sm">
                {{ trans("Not published yet") }}
            </div>

            <div v-if="data.published_snapshot?.comment" class="text-xs text-gray-500 italic truncate">
                ({{ data.published_snapshot.comment }})
            </div>
        </div>

        <div v-if="data.published_snapshot?.published_at" class="text-xs text-gray-600 shrink-0">
            {{ useRangeFromNow(data.published_snapshot.published_at) }} ago
        </div>
    </div>
    <component :is="data.type === 'landscape' ? SliderLandscape : SliderSquare" :data="data.compiled_layout"
        :production="true" :ratio="data.ratio" class="w-full h-full" />

</template>

<style scoped></style>