<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight, faImage } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from '@/Components/Image.vue'
library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)

defineProps<{
    data: {
        name: string
        description: string
        images: { source: string }[]
    }[]
}>()

</script>

<template>
    <div
        class="bg-white rounded-lg shadow-lg overflow-hidden transition-all duration-300 hover:scale-105 hover:shadow-xl">
        <div class="relative w-full mb-3">
            <!-- Display image or fallback icon -->
            <Image v-if="data.image" :src="data.image" alt="Product image"
                class="w-full h-48 object-cover" />
            <div v-else class="flex justify-center items-center bg-gray-100 w-full h-48">
                <FontAwesomeIcon :icon="faImage" class="w-8 h-8 text-gray-400" />
            </div>
            <div class="px-4 py-2">
                <!-- Product name -->
                <div class="text-lg font-semibold text-gray-800">{{ data.name }}</div>
                <!-- Product description -->
                <p class="mt-2 text-sm text-gray-600 truncate">{{ data.description || 'No description available' }}</p>
            </div>
        </div>
    </div>
</template>
