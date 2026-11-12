<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import ShoppingAssistant from "@/Iris/Pages/ShoppingAssistant.vue"

defineProps<{
    title: string
    assistant_name: string
    products_in_range?: number | null
    web_block_family?: { fieldValue?: Record<string, any> }
    web_block_family_code?: string
}>()
</script>

<template>
    <Head :title="title" />
    <ShoppingAssistant
        :assistant_name
        :products_in_range
        :web_block_family
        :web_block_family_code
    />
</template>
