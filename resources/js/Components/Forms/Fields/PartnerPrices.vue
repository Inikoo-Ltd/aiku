<script setup lang="ts">
import PartnerPricesTable from "@/Components/Masters/PartnerPricesTable.vue"

defineOptions({ inheritAttrs: false })

defineProps<{
    form: any
    fieldName: string
    fieldData: {
        type: string
        masterAsset: number
        masterAssetCode?: string
        meta: any
        row: any
        updateRoute: {
            name: string
            parameters: Record<string, string | number>
        }
    }
}>()
</script>

<template>
    <PartnerPricesTable
        :meta="fieldData.meta"
        :row="fieldData.row"
        :masterAssetCode="fieldData.masterAssetCode"
        :updateRoute="fieldData.updateRoute"
    />
</template>
