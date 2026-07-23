<!--
  - Author: Vika Aqordi <aqordivika@yahoo.co.id>
  - Github: aqordeon
  - Copyright (c) 2026, Vika Aqordi
  -->

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, nextTick, watch } from "vue"

const props = defineProps<{
    text: string
}>()

const expanded = ref(false)
const isOverflowing = ref(false)
const textRef = ref<HTMLElement | null>(null)

let resizeObserver: ResizeObserver | null = null

const measureOverflow = () => {
    const el = textRef.value
    if (!el) {
        return
    }
    isOverflowing.value = el.scrollHeight > el.clientHeight + 1
}

const checkOverflow = async () => {
    if (expanded.value) {
        return
    }
    await nextTick()
    measureOverflow()
}

onMounted(() => {
    checkOverflow()
    resizeObserver = new ResizeObserver(() => checkOverflow())
    if (textRef.value) {
        resizeObserver.observe(textRef.value)
    }
})

onBeforeUnmount(() => {
    resizeObserver?.disconnect()
})

watch(() => props.text, () => {
    expanded.value = false
    checkOverflow()
})
</script>

<template>
    <div class="mt-2">
        <p
            ref="textRef"
            class="text-sm text-gray-700 whitespace-pre-line"
            :class="{ 'line-clamp-4': !expanded }"
        >
            {{ text }}
        </p>

        <button
            v-if="isOverflowing || expanded"
            type="button"
            class="mt-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:underline"
            @click="expanded = !expanded"
        >
            {{ expanded ? $t('Show less') : $t('Read more') }}
        </button>
    </div>
</template>
