<script setup lang='ts'>
import { useRangeFromNow } from '@/Composables/useFormatTime'
import SliderLandscape from "@/Components/Banners/Slider/SliderLandscape.vue"
import SliderSquare from "@/Components/Banners/Slider/SliderSquare.vue"
import Image from '@/Components/Image.vue'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    data: {
        type: string
        compiled_layout: {}
        published_snapshot: {
            publisher: string
            publisher_avatar: string
            comment: string
            published_at: string
        }
    }
}>()
</script>

<template>
<div class="w-full max-w-full overflow-hidden">

    <!-- header -->
    <div
        v-if="data.published_snapshot"
        class="w-full bg-white flex items-center justify-between py-3 px-4 border-b"
    >
        <div class="flex gap-2 items-center min-w-0">
            <div
                v-if="data?.published_snapshot?.publisher_avatar"
                class="h-6 w-6 rounded-full overflow-hidden ring-1 ring-gray-300 shrink-0"
            >
                <Image :src="data.published_snapshot.publisher_avatar" />
            </div>

            <div v-if="data.published_snapshot?.publisher" class="font-semibold text-sm truncate">
                {{ data.published_snapshot.publisher }}
            </div>

            <div v-else class="text-gray-400 italic text-sm">
                {{ trans("Not published yet") }}
            </div>

            <div
                v-if="data.published_snapshot?.comment"
                class="text-xs text-gray-500 italic truncate"
            >
                ({{ data.published_snapshot.comment }})
            </div>
        </div>

        <div
            v-if="data.published_snapshot?.published_at"
            class="text-xs text-gray-600 shrink-0"
        >
            {{ useRangeFromNow(data.published_snapshot.published_at) }} ago
        </div>
    </div>

    <!-- banner wrapper -->
    <div class="w-full flex justify-center bg-gray-50 overflow-hidden">

        <!-- landscape -->
        <div
            v-if="data.type === 'landscape'"
            class="w-full max-w-6xl"
        >
            <div class="relative w-full h-[180px] md:h-[260px] lg:h-[320px] overflow-hidden">
                <SliderLandscape
                    :data="data.compiled_layout"
                    :production="true"
                    class="w-full h-full"
                />
            </div>
        </div>

        <!-- square -->
        <div
            v-else
            class="w-full max-w-md"
        >
            <div class="relative w-full h-[320px] md:h-[420px] overflow-hidden">
                <SliderSquare
                    :data="data.compiled_layout"
                    :production="true"
                    class="w-full h-full"
                />
            </div>
        </div>

    </div>
</div>
</template>