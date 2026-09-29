<!--
  - Author Louis Perez
  - Created on 29-09-2026-12h-07m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed } from "vue"
import QuickLookCatalogue from "@/Components/Goods/QuickLookCatalogue.vue"
import StockShowcase from "@/Components/Showcases/Grp/StockShowcase.vue"
import { ctrans } from "@/Composables/useTrans"
import type { QuickLookHistoryEntry, QuickLookHistoryRecord, QuickLookTab } from "@/types/QuickLookCatalogue"

const props = defineProps<{
    isOpen: boolean
    stockSlug: string | null
    code?: string | null
    initialTab?: QuickLookTab
}>()

const emits = defineEmits<{ (e: "onClose"): void }>()

const routes = computed(() =>
    props.stockSlug
        ? {
              overview: route("grp.goods.quick_look.stock", { stock: props.stockSlug }),
              sales_analysis: route("grp.goods.quick_look.stock.sales_analysis", { stock: props.stockSlug }),
              history: route("grp.goods.quick_look.stock.history", { stock: props.stockSlug }),
          }
        : null
)

const pageUrl = computed(() => (props.stockSlug ? route("grp.goods.stocks.show", props.stockSlug) : null))

const historyRecord = (entry: QuickLookHistoryEntry): QuickLookHistoryRecord | null => {
    if (entry.auditable_type === "Stock") {
        return { icon: "fal fa-cloud-rainbow", tooltip: ctrans("Master SKO history"), url: pageUrl.value ? `${pageUrl.value}?tab=history` : null }
    }
    if (entry.auditable_type === "OrgStock") {
        return { icon: "fal fa-box", tooltip: ctrans("Org SKO history"), url: route("grp.majordomo.redirect_org_stock", { orgStock: entry.auditable_id, tab: "history" }) }
    }
    return null
}
</script>

<template>
    <QuickLookCatalogue :isOpen="isOpen" :kind="ctrans('Product')" :code="code" :routes="routes" :pageUrl="pageUrl" :initialTab="initialTab" :historyRecordResolver="historyRecord" :historyNotice="ctrans('Includes changes made in every organisation, such as status changes. The full history on the product page only lists changes to the master SKO itself.')" @onClose="emits('onClose')">
        <template #overview="{ data, openSalesAnalysis }">
            <StockShowcase :data="data.showcase" :salesAnalysisTeaser="data.sales_analysis_teaser" compact :onOpenAnalysis="openSalesAnalysis" />
        </template>
    </QuickLookCatalogue>
</template>
