<script setup lang="ts">
import { computed, ref } from "vue"
import Image from "@common/Components/Image.vue"
import ProductImageViewerDialog from "@/Components/Product/ProductImageViewerDialog.vue"

const props = defineProps<{
    src: object
    images?: { source: object | null }[]
}>()

const isViewerVisible = ref(false)
const viewerImages = computed(() => (props.images?.length ? props.images : [{ source: props.src }]))
</script>

<template>
    <span role="button" class="block cursor-zoom-in" @click.stop.prevent="isViewerVisible = true">
        <Image :src="src" class="h-full w-full" />
        <ProductImageViewerDialog v-if="isViewerVisible" v-model:visible="isViewerVisible" :images="viewerImages" />
    </span>
</template>
