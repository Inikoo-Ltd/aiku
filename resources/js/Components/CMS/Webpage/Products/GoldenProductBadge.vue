<script setup lang="ts">
import GoldenProduct from "@/Iris/Assets/Golden Product.webp"
</script>

<template>
    <div class="xgolden-product-badge w-6 h-6" v-tooltip="ctrans('Golden product')">
        <img :src="GoldenProduct" :alt="ctrans('Golden product badge')" class="h-full w-full object-contain" />
    </div>
</template>
