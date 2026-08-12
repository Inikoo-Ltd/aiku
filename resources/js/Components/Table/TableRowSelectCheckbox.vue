<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckSquare } from "@fas"
import { faSquare } from "@fal"

const props = defineProps<{
    rowKey: number | string
    selection: Set<number | string>
}>()

const isSelected = computed(() => props.selection.has(props.rowKey))

const onToggleSelection = () => {
    if (isSelected.value) {
        props.selection.delete(props.rowKey)
    } else {
        props.selection.add(props.rowKey)
    }
}
</script>

<template>
    <FontAwesomeIcon
        :icon="isSelected ? faCheckSquare : faSquare"
        :class="isSelected ? 'text-green-500' : 'text-gray-500 hover:text-gray-700'"
        class="p-2 cursor-pointer text-lg mx-auto block"
        fixed-width
        aria-hidden="true"
        @click="onToggleSelection"
    />
</template>
