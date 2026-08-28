<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckSquare } from "@fas"
import { faSquare } from "@fal"

type RowKey = number | string
type Selection = Set<RowKey> | Map<RowKey, unknown>

const props = defineProps<{
    rowKey: RowKey
    selection: Selection
    rowValue?: unknown
    highlightRow?: boolean
}>()

const isSelected = computed(() => props.selection.has(props.rowKey))

const onToggleSelection = () => {
    if (isSelected.value) {
        props.selection.delete(props.rowKey)
    } else if (props.selection instanceof Map) {
        props.selection.set(props.rowKey, props.rowValue ?? props.rowKey)
    } else {
        props.selection.add(props.rowKey)
    }
}
</script>

<template>
    <FontAwesomeIcon
        :icon="isSelected ? faCheckSquare : faSquare"
        :class="[
            isSelected ? 'text-green-500' : 'text-gray-500 hover:text-gray-700',
            { 'tableRowSelected': isSelected && highlightRow },
        ]"
        class="p-2 cursor-pointer text-lg mx-auto block"
        fixed-width
        aria-hidden="true"
        @click="onToggleSelection"
    />
</template>

<!-- Use tr:has instead of :class to avoid rerendering -->
<style>
tr:has(.tableRowSelected),
tr:has(.tableRowSelected):hover {
    background-color: rgb(220 252 231 / 0.7);
}
</style>
