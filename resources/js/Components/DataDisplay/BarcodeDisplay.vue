<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Fri, 31 July 2026 09:12:44 Bali, Indonesia
    -  Copyright (c) 2026, Vika Aqordi
-->

<script setup lang="ts">
import { ref, watch, onMounted } from "vue"
import JsBarcode from "jsbarcode"

const props = withDefaults(defineProps<{
    value?: string | null
    height?: number
    width?: number
    displayValue?: boolean
    fontSize?: number
}>(), {
    height: 34,
    width: 1.4,
    displayValue: true,
    fontSize: 12,
})

const svgRef = ref<SVGElement | null>(null)
const isRendered = ref(false)

const formatOf = (value: string) => /^\d{13}$/.test(value) ? "EAN13" : "CODE128"

const renderBarcode = () => {
    isRendered.value = false

    if (!svgRef.value || !props.value) {
        return
    }

    JsBarcode(svgRef.value, props.value, {
        format: formatOf(props.value),
        lineColor: "#000",
        width: props.width,
        height: props.height,
        fontSize: props.fontSize,
        margin: 0,
        displayValue: props.displayValue,
        valid: (valid: boolean) => {
            isRendered.value = valid
        },
    })
}

onMounted(renderBarcode)
watch(() => props.value, renderBarcode)
</script>

<template>
    <div v-if="value">
        <svg ref="svgRef" class="max-w-full" />
        <span v-if="!isRendered" class="text-xs text-gray-400 tabular-nums">{{ value }}</span>
    </div>
</template>
