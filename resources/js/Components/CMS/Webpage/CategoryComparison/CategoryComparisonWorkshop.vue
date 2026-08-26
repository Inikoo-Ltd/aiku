<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import CategoryComparisonRender from "@/Iris/Components/BlocksUtils/CategoryComparison/CategoryComparisonRender.vue"
import type { CategoryComparisonValue } from "@/Iris/Components/BlocksUtils/CategoryComparison/types"

defineProps<{
    modelValue: CategoryComparisonValue
    screenType: "mobile" | "tablet" | "desktop"
    indexBlock?: number | string
    webpageData?: any
    blockData?: Record<string, any>
}>()
</script>

<template>
    <CategoryComparisonRender
        :fieldValue="modelValue"
        :screenType="screenType"
        :indexBlock="indexBlock"
        :emptyStateMessage="ctrans('No comparison data yet. Fill in the Category Comparison of this family and its sibling families.')"
    />
</template>
