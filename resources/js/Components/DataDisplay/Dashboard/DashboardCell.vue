<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTriangle, faEquals } from "@fas"
import { Link } from "@inertiajs/vue3"

const props = defineProps<{
    cell: {
        route_target?: {
            name?: string
            parameters?: any
        }
        tooltip?: string
        formatted_value?: string
        delta_icon?: {
            change?: string
            state?: string
        }
    }
}>()

const getIntervalChangesIcon = (change: string) => {
	if (change === 'increase') {
		return {
			icon: faTriangle
		}
	} else if (change === 'decrease') {
		return {
			icon: faTriangle,
			class: 'rotate-180'
		}
	} else if (change === 'no_change') {
		return {
			icon: faEquals,
		}
	} else {
		return null
	}
}
const getIntervalStateColor = (state?: string) => {
    if (!state) {
        return ''
    }

	if (state === 'positive') {
		return 'text-green-500'
	} else if (state === 'negative') {
		return 'text-red-500'
	} else if (state === 'neutral') {
		return 'text-gray-400'
	} else {
		return ''
	}
}
</script>

<template>
    <component
        class="tabular-nums text-xs md:text-base"
        :class="[
            cell?.route_target?.name ? 'cursor-pointer hover:underline' : '',
        ]"
        :is="cell?.route_target?.name ? Link : 'div'"
        :href="cell?.route_target?.name ? route(cell?.route_target.name, cell?.route_target.parameters) : '#'"
    >
        <span v-tooltip="`${cell?.tooltip ?? ''}`">{{ cell?.formatted_value }}</span>
        <FontAwesomeIcon
            v-if="cell?.delta_icon?.change"
            :icon="getIntervalChangesIcon(cell?.delta_icon?.change)?.icon"
            class="text-xxs md:text-sm"
            :class="[
                getIntervalChangesIcon(cell?.delta_icon?.change)?.class,
                getIntervalStateColor(cell?.delta_icon?.state),
            ]"
            fixed-width
            aria-hidden="true"
        />
    </component>
</template>