<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTriangle, faEquals } from "@fas"
import { Link } from "@inertiajs/vue3"
import { Intervals } from "@/types/Components/Dashboard"
import { getDashboardDateRange } from "@/Composables/useDashboard"

interface RouteTarget {
    name?: string
    parameters?: any
    key_date_filter?: string
}

const props = defineProps<{
    cell: {
        route_target?: RouteTarget
        tooltip?: string
        formatted_value?: string
        delta_icon?: {
            change?: string
            state?: string
        },
        icon?: string
    }
    interval: Intervals
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

// const dashboardDateRange = inject('dashboardDateRange', '')
// // console.log('vcxvcxvcx', dashboardDateRange)

// // To take key_date_filter from route_target
// const generateRouteParameter = (route_target: RouteTarget) => {
//     if (route_target?.key_date_filter) {
//         return {
//             ...route_target?.parameters,
//             [route_target?.key_date_filter]: dashboardDateRange
//         }
//     }

//     return route_target?.parameters
// }
</script>

<template>
    <component
        class="flex gap-2 items-center tabular-nums text-xs md:text-base"
        :class="[
            cell?.route_target?.name ? 'cursor-pointer hover:underline' : '',
        ]"
        :is="cell?.route_target?.name ? Link : 'div'"
        :href="cell?.route_target?.name ? route(cell?.route_target.name, cell?.route_target.key_date_filter ? {
            ...cell?.route_target?.parameters,
            [cell?.route_target?.key_date_filter]: getDashboardDateRange(props.interval.value, props.interval.range_interval)
        } : cell?.route_target.parameters) : '#'"
    >
        <img
            v-if="cell?.icon"
            :src="`/assets/channel_logo/${cell.icon}.svg`"
            class="w-4 h-4"
            :alt="cell.icon"
            v-tooltip="cell?.tooltip ?? cell.icon"
        />
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
