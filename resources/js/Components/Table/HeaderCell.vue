<script setup lang="ts">
import { faYinYang } from '@fal'
import { capitalize } from "@/Composables/capitalize"

library.add(faYinYang);


import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"

const props = defineProps<{
    cell: {
        key: string
        type?: string  // For width of the column
        label?: {
            type: string  // 'icon', 'text'
            tooltip?: string
            data: string | string[] // 'Pallets', ['fal', 'fa-yinyang']
        } | string
        shortLabel?: string
        icon?: string | string[]
        sortable: boolean
        hidden: boolean
        sorted: string
        onSort: Function
        tooltip?: string
        align?: string
    }
    column: {
        key: string
    }
    resource: any
}>()

function onClick() {
    if (props.cell?.sortable) {
        props.cell?.onSort(props.cell?.key)
    }
}

const isCellNumber = () => {
    if(props.resource?.length) {
        return props.resource?.some((aaa: any) => typeof aaa[props.column.key] === 'number') || false
    }
    
    return false
}
</script>

<template>
    <!-- <pre>{{ cell?.icon }}</pre> -->
    <th v-show="!cell?.hidden" class="font-normal"
        :class="[
            cell?.type == 'avatar' || cell?.type == 'icon' ? 'px-5 w-1' : 'px-6 w-auto',
            cell?.align === 'right' || isCellNumber() || cell?.type == 'number' || cell?.type == 'currency' || cell.type === 'date' || cell.type === 'date_hm' || cell.type === 'date_hms' ? 'text-right' : 'text-left'
        ]"
    >
        <component :is="cell?.sortable ? 'button' : 'div'" class="py-1"
            :dusk="cell?.sortable ? `sort-${cell?.key}` : null" @click.prevent="onClick">
            <!-- <slot name="pagehead" :data="{isCellNumber : isCellNumber, cell}"> -->
                <div class="flex items-center justify-start"
                    :class="{'justify-center': cell?.type == 'avatar' || cell?.type == 'icon', 'justify-end': isCellNumber()}">
                    
                    <!-- Label: object -->
                    <div v-if="typeof cell?.label === 'object'">
                        <FontAwesomeIcon
                            v-if="cell?.icon || cell?.label.type === 'icon'"
                            :icon="cell?.icon || cell?.label.data"
                            v-tooltip="capitalize(cell?.label.tooltip)"
                            aria-hidden="true"
                            size="lg"
                            fixed-width
                        />

                        <div v-else-if="cell?.label.type === 'text'"  v-tooltip="cell?.label.tooltip">
                            {{ cell?.label.data || ''}}
                        </div>

                        <div v-else class="text-gray-400 italic pl-5 pr-3">
                        </div>
                    </div>
                    
                    <!-- Label: simple and icon -->
                    <div v-else class="capitalize text-xs md:text-sm lg:text-base w-full" v-tooltip="cell?.tooltip"
                        :class="[cell?.type == 'number' || cell?.type == 'currency' ? 'text-right pr-3' : '']"
                    >
                        <FontAwesomeIcon
                            v-if="cell?.icon"
                            :icon="cell?.icon"
                            aria-hidden="true"
                            fixed-width
                            class="text-gray-500 mr-2"
                        />
                        <FontAwesomeIcon
                            v-if="cell?.is_interval"
                            icon="fas fa-watch-calculator"
                            aria-hidden="true"
                            fixed-width
                            class="text-gray-500 mr-2"/>
                        <span v-if="cell?.label" class="hidden lg:inline">{{ cell?.label || ''}}</span>
                        <span v-if="cell?.shortLabel || cell?.label" class="inline lg:hidden">{{ cell?.shortLabel || cell?.label || ''}}</span>
                        
                    </div>

                    <!-- Icon: arrow for sort -->
                    <svg v-if="cell?.sortable" aria-hidden="true" class="w-3 h-3 ml-2" :class="{
                        'text-gray-400': !cell?.sorted,
                        'text-green-500': cell?.sorted,
                    }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" :sorted="cell?.sorted">
                        <path v-if="!cell?.sorted" fill="currentColor"
                            d="M41 288h238c21.4 0 32.1 25.9 17 41L177 448c-9.4 9.4-24.6 9.4-33.9 0L24 329c-15.1-15.1-4.4-41 17-41zm255-105L177 64c-9.4-9.4-24.6-9.4-33.9 0L24 183c-15.1 15.1-4.4 41 17 41h238c21.4 0 32.1-25.9 17-41z" />

                        <path v-if="cell?.sorted === 'asc'" fill="currentColor"
                            d="M279 224H41c-21.4 0-32.1-25.9-17-41L143 64c9.4-9.4 24.6-9.4 33.9 0l119 119c15.2 15.1 4.5 41-16.9 41z" />

                        <path v-if="cell?.sorted === 'desc'" fill="currentColor"
                            d="M41 288h238c21.4 0 32.1 25.9 17 41L177 448c-9.4 9.4-24.6 9.4-33.9 0L24 329c-15.1-15.1-4.4-41 17-41z" />
                    </svg>
                </div>
            <!-- </slot> -->
        </component>
    </th>
</template>

