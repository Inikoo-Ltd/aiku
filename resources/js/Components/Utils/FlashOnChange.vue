<script setup lang="ts">
import { ref, watch } from "vue"

const props = withDefaults(defineProps<{
    value: number | string | null | undefined
    enabled?: boolean
}>(), {
    enabled: true,
})

const flashKey = ref(0)
const direction = ref<"up" | "down" | null>(null)

watch(() => props.value, (newValue, oldValue) => {
    if (!props.enabled || newValue === oldValue || newValue === null || newValue === undefined || oldValue === null || oldValue === undefined) {
        return
    }

    const newNumber = Number(newValue)
    const oldNumber = Number(oldValue)
    direction.value = Number.isFinite(newNumber) && Number.isFinite(oldNumber) && newNumber < oldNumber ? "down" : "up"
    flashKey.value++
})
</script>

<template>
    <span :key="flashKey" :class="direction ? `flash-on-change-${direction}` : ''">
        <slot />
    </span>
</template>

<style scoped>
.flash-on-change-up :slotted(*) {
    animation: flash-on-change-up 3s ease-out;
}

.flash-on-change-down :slotted(*) {
    animation: flash-on-change-down 3s ease-out;
}

@keyframes flash-on-change-up {
    0%,
    20% {
        color: #00c853;
    }
    55% {
        color: rgb(21 128 61);
    }
}

@keyframes flash-on-change-down {
    0%,
    20% {
        color: #ff1744;
    }
    55% {
        color: rgb(185 28 28);
    }
}
</style>
