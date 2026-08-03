<script setup lang="ts">
import { computed } from "vue"

const props = withDefaults(
    defineProps<{
        modelValue: number | string
        stars?: number
    }>(),
    {
        stars: 5,
    },
)

const percentage = computed(() => {
    const value = Math.min(Math.max(parseFloat(String(props.modelValue)) || 0, 0), props.stars)

    return (value / props.stars) * 100
})
</script>

<template>
    <div class="star-rating" role="img" :aria-label="`${modelValue} / ${stars}`">
        <div class="star-rating-empty">
            <span v-for="index in stars" :key="index">★</span>
        </div>
        <div class="star-rating-filled" :style="{ width: `${percentage}%` }">
            <span v-for="index in stars" :key="index">★</span>
        </div>
    </div>
</template>

<style scoped>
.star-rating {
    position: relative;
    display: inline-flex;
    line-height: 1;
    white-space: nowrap;
}

.star-rating-empty {
    color: #e5e7eb;
}

.star-rating-filled {
    position: absolute;
    top: 0;
    left: 0;
    overflow: hidden;
    color: #f59e0b;
}
</style>
