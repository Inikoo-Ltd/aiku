<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { ctrans } from '@/Composables/useTrans'

const props = defineProps<{
    text?: string
}>()

const loadingDot = ref('')
const dots = ref(0)

const updateLoadingText = () => {
    dots.value = (dots.value + 1) % 4
    loadingDot.value = '.'.repeat(dots.value)
}

let intervalId: ReturnType<typeof setTimeout> | null = null

onMounted(() => {
    intervalId = setInterval(updateLoadingText, 400)
})

onUnmounted(() => {
    if (intervalId !== null) {
        clearInterval(intervalId)
    }
})
</script>

<template>
    <div class="relative w-fit">
        <span>{{ text ?? ctrans('Loading') }}</span>
        <div class="absolute bottom-0 left-full">{{ loadingDot }}</div>
    </div>
</template>