<!--
  - Author Louis Perez
  - Created on 29-09-2026-12h-07m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed } from "vue"
import QuickLookCatalogue from "@/Components/Goods/QuickLookCatalogue.vue"
import StockFamilyShowcase from "@/Components/Showcases/Grp/StockFamilyShowcase.vue"
import { ctrans } from "@/Composables/useTrans"
import type { QuickLookHistoryEntry, QuickLookHistoryRecord, QuickLookTab } from "@/types/QuickLookCatalogue"

const props = defineProps<{
    isOpen: boolean
    familySlug: string | null
    code?: string | null
    initialTab?: QuickLookTab
}>()

const emits = defineEmits<{ (e: "onClose"): void }>()

const routes = computed(() =>
    props.familySlug
        ? {
              overview: route("grp.goods.quick_look.family", { stockFamily: props.familySlug }),
              sales_analysis: route("grp.goods.quick_look.family.sales_analysis", { stockFamily: props.familySlug }),
              history: route("grp.goods.quick_look.family.history", { stockFamily: props.familySlug }),
          }
        : null
)

const pageUrl = computed(() => (props.familySlug ? route("grp.goods.stock-families.show", props.familySlug) : null))

const historyRecord = (entry: QuickLookHistoryEntry): QuickLookHistoryRecord | null => {
    if (entry.auditable_type === "StockFamily") {
        return { icon: "fal fa-boxes-alt", tooltip: ctrans("Stock family history"), url: pageUrl.value ? `${pageUrl.value}?tab=history` : null }
    }
    if (entry.auditable_type === "OrgStock") {
        return { icon: "fal fa-box", tooltip: ctrans("Org SKO history"), url: route("grp.majordomo.redirect_org_stock", { orgStock: entry.auditable_id, tab: "history" }) }
    }
    return null
}
</script>

<template>
    <QuickLookCatalogue :isOpen="isOpen" :kind="ctrans('Family')" :code="code" :routes="routes" :pageUrl="pageUrl" :initialTab="initialTab" :historyRecordResolver="historyRecord" :historyNotice="ctrans('Includes changes made in every organisation to the SKOs in this family, such as status changes. The full history on the family page only lists changes to the family itself.')" @onClose="emits('onClose')">
        <template #overview="{ data, openSalesAnalysis }">
            <StockFamilyShowcase :salesAnalysisTeaser="data.sales_analysis_teaser" compact :onOpenAnalysis="openSalesAnalysis" />
        </template>
    </QuickLookCatalogue>
</template>
