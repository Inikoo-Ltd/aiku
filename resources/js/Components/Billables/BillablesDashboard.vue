<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faShippingFast, faChargingStation, faConciergeBell, faArrowRight } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import ButtonWithLink from '../Elements/Buttons/ButtonWithLink.vue'
import { routeType } from '@/types/route'

library.add(faShippingFast, faChargingStation, faConciergeBell, faArrowRight)

interface SectionStats {
    stats: Record<string, number>
    route: routeType
}

interface DashboardData {
    shipping: SectionStats
    charges: SectionStats
    services: SectionStats
}

defineProps<{
    data?: DashboardData
}>()

const sections = (data: DashboardData) => [
    {
        key: 'shipping',
        label: trans('Shipping'),
        icon: 'fal fa-shipping-fast',
        iconColor: 'text-blue-500',
        bgColor: 'bg-blue-50',
        borderColor: 'border-blue-200',
        badgeColor: 'bg-blue-100 text-blue-700',
        section: data.shipping,
        statLabels: {
            total:          trans('Total'),
            live:           trans('Live'),
            in_process:     trans('In process'),
            decommissioned: trans('Decommissioned'),
        },
        statColors: {
            total:          'text-gray-700',
            live:           'text-green-600',
            in_process:     'text-yellow-600',
            decommissioned: 'text-red-500',
        },
    },
    {
        key: 'charges',
        label: trans('Charges'),
        icon: 'fal fa-charging-station',
        iconColor: 'text-amber-500',
        bgColor: 'bg-amber-50',
        borderColor: 'border-amber-200',
        badgeColor: 'bg-amber-100 text-amber-700',
        section: data.charges,
        statLabels: {
            total:        trans('Total'),
            active:       trans('Active'),
            in_process:   trans('In process'),
            discontinued: trans('Discontinued'),
        },
        statColors: {
            total:        'text-gray-700',
            active:       'text-green-600',
            in_process:   'text-yellow-600',
            discontinued: 'text-red-500',
        },
    },
    {
        key: 'services',
        label: trans('Services'),
        icon: 'fal fa-concierge-bell',
        iconColor: 'text-purple-500',
        bgColor: 'bg-purple-50',
        borderColor: 'border-purple-200',
        badgeColor: 'bg-purple-100 text-purple-700',
        section: data.services,
        statLabels: {
            total:        trans('Total'),
            active:       trans('Active'),
            in_process:   trans('In process'),
            discontinued: trans('Discontinued'),
        },
        statColors: {
            total:        'text-gray-700',
            active:       'text-green-600',
            in_process:   'text-yellow-600',
            discontinued: 'text-red-500',
        },
    },
]
</script>

<template>
    <div v-if="data" class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6">
        <div
            v-for="section in sections(data)"
            :key="section.key"
            class="rounded-xl border shadow-sm overflow-hidden"
            :class="[section.borderColor, section.bgColor]"
        >
            <div class="px-5 py-4 flex items-center justify-between border-b" :class="section.borderColor">
                <div class="flex items-center gap-x-2">
                    <FontAwesomeIcon :icon="section.icon" class="text-xl" :class="section.iconColor" fixed-width aria-hidden="true" />
                    <span class="font-semibold text-gray-800 text-base">{{ section.label }}</span>
                </div>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="section.badgeColor">
                    {{ section.section.stats.total ?? 0 }}
                </span>
            </div>

            <div class="px-5 py-4 space-y-2">
                <div
                    v-for="(label, key) in section.statLabels"
                    :key="key"
                    class="flex justify-between items-center text-sm"
                >
                    <span class="text-gray-500">{{ label }}</span>
                    <span class="font-semibold" :class="section.statColors[key]">
                        {{ section.section.stats[key] ?? 0 }}
                    </span>
                </div>
            </div>

            <div class="px-5 pb-4">
                <ButtonWithLink
                    :routeTarget="section.section.route"
                    :label="ctrans('View all')"
                    type="tertiary"
                    full
                    iconRight="fal fa-arrow-right"
                    
                >
                    <!-- <template #label>
                        <div :class="[section.borderColor, section.iconColor]">
                            {{ trans('View all') }}
                        </div>
                    </template> -->
                </ButtonWithLink>
            </div>
        </div>
    </div>

    <div v-else class="p-6 text-center text-gray-400 text-sm">
        {{ trans('No data available') }}
    </div>
</template>
