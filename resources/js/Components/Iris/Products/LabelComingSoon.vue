<script setup lang="ts">
import { ctrans } from '@/Composables/useTrans'

const props = defineProps<{
    product: {
        status: string
        status_label?: string
    }
}>()

</script>

<template>
    <span class="bg-yellow-400/50 border border-yellow-400 text-yellow-700 px-3 rounded-sm rounded-tr-xl rounded-bl-xl">
        {{ product.status_label ?? ctrans("Coming soon") }}
    </span>
</template>