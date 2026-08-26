<script setup lang="ts">
import { computed, toRef } from "vue"
import { ctrans } from "@/Composables/useTrans"
import CategoryComparisonRender from "@/Iris/Components/BlocksUtils/CategoryComparison/CategoryComparisonRender.vue"
import { useComparisonFamilies } from "@/Iris/Components/BlocksUtils/CategoryComparison/useComparisonFamilies"
import type { CategoryComparisonValue, ScreenType } from "@/Iris/Components/BlocksUtils/CategoryComparison/types"

const props = defineProps<{
    modelValue: CategoryComparisonValue
    screenType: ScreenType
    indexBlock?: number | string
    webpageData?: any
    blockData?: Record<string, any>
}>()

const fetchParameters = computed<Record<string, string> | undefined>(() => {
    const productCategory = props.webpageData?.model_slug
    const website = route().params["website"] as string | undefined

    return productCategory && website ? { website, productCategory } : undefined
})

const {
    families,
    familyOptions,
    selectedSlugs,
    numberOfComparedFamilies,
    toggleFamily,
    loading,
} = useComparisonFamilies(
    "grp.json.website.category.range_for_comparison",
    fetchParameters,
    toRef(props, "modelValue"),
    toRef(props, "screenType")
)
</script>

<template>
    <CategoryComparisonRender
        :fieldValue="modelValue"
        :screenType="screenType"
        :indexBlock="indexBlock"
        :families="families"
        :familyOptions="familyOptions"
        :selectedSlugs="selectedSlugs"
        :maxComparedFamilies="numberOfComparedFamilies"
        :loading="loading"
        @toggleFamily="toggleFamily"
        :emptyStateMessage="ctrans('No comparison data yet. Fill in the Category Comparison of this family and its sibling families.')"
    />
</template>
