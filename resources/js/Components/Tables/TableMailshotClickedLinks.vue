<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExternalLink } from '@fal'

defineProps<{
    data: object
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(element)="{ item: link }">
            <span v-if="link.element" class="font-mono text-xs bg-gray-100 text-gray-700 rounded px-1.5 py-0.5">
                {{ link.element }}
            </span>
            <span v-else class="text-xs text-gray-500">{{ trans('not tagged') }}</span>
        </template>

        <template #cell(label)="{ item: link }">
            <a :href="link.url" target="_blank" rel="noopener noreferrer" :title="link.url"
                class="text-gray-700 hover:text-gray-900 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded">
                {{ link.label }}
                <FontAwesomeIcon :icon="faExternalLink" class="text-gray-400 text-xs" fixed-width aria-hidden="true" />
            </a>
        </template>
    </Table>
</template>
