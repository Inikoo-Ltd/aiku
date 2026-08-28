<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckSquare } from "@fas"
import { faSquare } from "@fal"

type RowKey = number | string

const props = defineProps<{
    rowKeys: RowKey[]
    selection: Set<RowKey> | Map<RowKey, unknown>
}>()

const emits = defineEmits<{
    (e: "toggle", selectAll: boolean): void
}>()

const isAllSelected = computed(
    () => props.rowKeys.length > 0 && props.rowKeys.every(rowKey => props.selection.has(rowKey))
)
</script>

<template>
    <div class="py-1.5 cursor-pointer" @click="emits('toggle', !isAllSelected)">
        <FontAwesomeIcon
            :icon="isAllSelected ? faCheckSquare : faSquare"
            :class="isAllSelected ? 'text-green-500' : 'text-gray-500 hover:text-gray-700'"
            class="mx-auto block h-5 my-auto"
            fixed-width
            aria-hidden="true"
        />
    </div>
</template>
