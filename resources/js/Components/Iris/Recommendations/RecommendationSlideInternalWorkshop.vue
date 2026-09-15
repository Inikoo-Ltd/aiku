<script setup lang="ts">
import Image from "@common/Components/Image.vue"
import { ctrans } from "@/Composables/useTrans"
import { InternalRecommendationProduct } from "@/types/Recommendations/RecommendationTypes"

defineProps<{
    product: InternalRecommendationProduct
}>()
</script>

<template>
    <div class="relative flex flex-col h-full md:p-3 rounded bg-white">
        <div class="mb-3 flex justify-center">
            <div class="w-full max-w-[220px] aspect-square flex items-center justify-center">
                <Image
                    :src="product?.web_images?.main?.original"
                    :alt="product.name"
                    class="object-contain w-full h-full"
                />
            </div>
        </div>

        <span
            class="mb-1 text-[13px] md:text-[16px] text-justify font-semibold leading-snug line-clamp-2 min-h-[3em]"
            :title="product.name"
        >
            {{ product.name }}
        </span>

        <div class="flex justify-between">
            <div class="text-xs text-gray-400 mb-2">
                {{ product.code }}
            </div>
            <div class="font-semibold underline text-xs">
                {{ ctrans('See Details') }}
            </div>
        </div>
    </div>
</template>
