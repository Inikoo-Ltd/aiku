<script setup lang='ts'>
import { useFormatTime } from '@/Composables/useFormatTime'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { inject } from 'vue'
import Icon from '../Icon.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

const locale = inject('locale', aikuLocaleStructure)

const props = defineProps<{
    item: Record<string, any>
    column: {
        key: string
        type: string
    }
}>()
</script>

<template>
    <template v-if="typeof item[column.key] == 'number' || column.type === 'number'">
        {{ locale.number(item[column.key]) }}
    </template>
    <template v-else-if="column.type === 'currency'">
        {{ locale.currencyFormat(item.currency_code || '', item[column.key]) }}
    </template>
    <template v-else-if="column.type === 'date'">
        <span v-tooltip="useFormatTime(item[column.key], { formatTime: 'hms' })" class="whitespace-nowrap">
            {{ useFormatTime(item[column.key]) }}
        </span>
    </template>
    <template v-else-if="column.type === 'date_hm'">
        <span class="whitespace-nowrap">
            {{ useFormatTime(item[column.key], { formatTime: 'hm' }) }}
        </span>
    </template>
    <template v-else-if="column.type === 'date_hms'">
        <span class="whitespace-nowrap">
            {{ useFormatTime(item[column.key], { formatTime: 'hms' }) }}
        </span>
    </template>
    <template v-else-if="column.type === 'badge'">
        <span v-if="item[column.key]"
            :class="item[column.key].class"
            class="inline-flex items-center whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset">
            {{ item[column.key].label }}
        </span>
    </template>
    <template v-else-if="column.type === 'icon'">
        <Icon v-if="item[column.key]?.icon || item[column.key]?.text || item[column.key]?.svg"
            :data="item[column.key]" />
        <FontAwesomeIcon v-else :icon="item[column.key]" class="" fixed-width aria-hidden="true" />
    </template>
    <template v-else>
        {{ item[column.key] }}
    </template>
</template>
