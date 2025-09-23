<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Wed, 14 Sept 2022 18:26:10 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faEmptySet, faStar, faWrench, faWarehouse, faStore, faCashRegister, faMoneyCheckAlt, faTasks } from '@fal'
import { useLocaleStore } from '@/Stores/locale'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { capitalize } from "@/Composables/capitalize"
import { routeType } from '@/types/route'
import { inject, ref } from 'vue'
import { Icon } from '@/types/Utils/Icon'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
library.add(faEmptySet, faStar, faWrench, faWarehouse, faStore, faCashRegister, faMoneyCheckAlt, faTasks)


// Props for dynamic behavior
const props = defineProps<{
    widget: {
        nodes: {
            index?: {
                number: number
            }
            name: string
            description?: string
            route: routeType
            icon: string | string[]
            shortName: string
            rightSubLink: {
                icon: string
                route: routeType
                tooltip: string
            }
            sub_data: {
                icon: Icon
                count: number | null
                route: routeType
            }[]
        }[]
        mode?: string
    }
}>()

const locale = inject('locale', aikuLocaleStructure)
const isLoading = ref<string | boolean>(false)

// Example data in BE
// 'dashboard_stats'   => [
//     'widgets'   => [
//         'column_count'  => 1,
//         'components'    => [
//             [
//                 'type'      => 'flat_tree_map',  // 'basic'
//                 // 'col_span'  => '2',
//                 // 'row_span'  => '2',
//                 'visual'    => [],
//                 'data'      => [
//                     'nodes'     => [
//                         [
//                             'name'  => __('agents'),
//                             'icon'  => ['fal', 'fa-people-arrows'],
//                             'route'  => [
//                                 'name' => 'grp.supply-chain.agents.index'
//                             ],
//                             'index' => [
//                                 'number' => $this->group->supplyChainStats->number_active_agents
//                             ],
//                         ],
//                         [
//                             'name'  => __('suppliers'),
//                             'icon'  => ['fal', 'fa-person-dolly'],
//                             'route'  => ['name' => 'grp.supply-chain.suppliers.index'],
//                             'index' => [
//                                 'number' => $this->group->supplyChainStats->number_active_independent_suppliers
//                             ],

//                         ],
//                         [
//                             'name'      => __('supplier products'),
//                             'shortName' => __('products'),
//                             'icon'      => ['fal', 'fa-box-usd'],
//                             'route'      => ['name' => 'grp.supply-chain.supplier_products.index'],
//                             'index'     => [
//                                 'number' => $this->group->supplyChainStats->number_current_supplier_products
//                             ],

//                         ],
//                     ],
//                     // 'mode'  => 'compact'
//                 ],
//             ]
//         ],
//     ]
// ]
</script>

<template>
    <nav aria-label="Tree maps" class="py-1 md:py-0" :class="[widget.mode == 'compact' ? 'mt-2' : 'mt-3']">
        <ol v-if="widget.nodes" role="list" class="overflow-x-auto divide-y divide-gray-300 rounded-md border border-gray-300 md:flex md:divide-y-0">
            <li v-for="(node, nodeIdx) in widget.nodes" :key="node.name" class="pb-0 relative flex flex-1 items-center">
                <!-- Main Tree -->
                <component
                    :is="node.route?.name ? Link : 'div'"
                    :href="node.route?.name ? route(node.route.name, node.route.parameters) : ''"
                    class="group/node flex flex-col md:flex-row w-full items-start md:items-center justify-between pr-10"
                    @start="() => isLoading = 'node' + nodeIdx"
                    @finish="() => isLoading = false"
                >
                    <div class="flex items-center px-4 text-lg xl:px-6 font-medium gap-x-4" :class="[widget.mode == 'compact' ? 'py-2' : node.sub_data?.length ? 'pt-4 md:pt-0 ' : 'py-4']">
                        <LoadingIcon v-if="isLoading === 'node' + nodeIdx" :size="widget.mode == 'compact' ? undefined : 'lg'" class="flex-shrink-0 text-gray-400" />
                        <FontAwesomeIcon v-else-if="node.icon" :size="widget.mode == 'compact' ? undefined : 'lg'" :icon="node.icon" class="flex-shrink-0 text-gray-400" aria-hidden="true" fixed-width />
                        <p class="md:leading-none md:text-sm lg:text-base inline font-medium text-gray-500 group-hover/node:text-gray-700">
                            <span class="hidden lg:inline">{{ node.name }}</span>
                            <span class="inline lg:hidden">{{ node.shortName ? node.shortName : node.name }}</span>
                        </p>

                        <!-- Bars and count -->
                        <span class="font-medium whitespace-nowrap text-gray-500 group-hover/node:text-gray-700">
                            <FontAwesomeIcon icon="fal fa-bars" class="mr-1" fixed-width />
                            <span v-if="node.index?.number">{{ locale?.number(node.index.number) }}</span>
                            <FontAwesomeIcon v-else icon="fal fa-empty-set" fixed-width />
                            <span v-if="node.description" class="ml-2">
                                {{ node.description }}
                            </span>
                        </span>

                    </div>

                    <!-- Section: Sub data -->
                    <div v-if="node.sub_data?.length" class="py-2 px-3 md:px-0 text-sm text-gray-500 flex gap-x-3 gap-y-0.5 justify-end items-center flex-wrap">
                        <Link
                            v-for="subData in node.sub_data"
                            :is="subData.route?.name ? Link : 'div'"
                            :href="subData.route?.name ? route(subData.route.name, subData.route.parameters) : ''"
                            class="group/sub px-2 flex gap-x-0.5 items-center font-normal"
                            v-tooltip="capitalize(subData.icon?.tooltip)"
                        >
                            <FontAwesomeIcon :icon="subData.icon?.icon" class="" :class="subData.icon?.class" fixed-width :title="subData.icon?.tooltip" aria-hidden="true" />
                            <span class=" ">
                                {{ locale?.number(subData.count || 0) }}
                            </span>
                        </Link>
                    </div>
                </component>


                <!-- Sublink on right each section (Marketplace) -->
                <div v-if="node.rightSubLink" class="pr-4 " :title="capitalize(node.rightSubLink.tooltip)">
                    <component
                        :is="node.rightSubLink?.route?.name ? Link : 'div'"
                        :href="node.route?.name ? route(node.rightSubLink.route.name, node.rightSubLink.route.parameters) : ''"
                        @start="() => isLoading = 'subLink' + nodeIdx"
                        @finish="() => isLoading = false"
                        class="w-9 h-9 flex justify-center items-center specialBox">
                        <LoadingIcon v-if="isLoading === 'subLink' + nodeIdx" />
                        <FontAwesomeIcon v-else-if="node.rightSubLink?.icon" :icon="node.rightSubLink.icon" class="flex-shrink-0 " aria-hidden="true" fixed-width />
                    </component>
                </div>

                <template v-if="nodeIdx !== widget.nodes?.length - 1">
                    <!-- Arrow separator for lg screens and up -->
                    <div class="hidden w-5 md:block" aria-hidden="true" :class="[widget.mode == 'compact' ? 'h-11' : 'h-full']">
                        <svg class="h-full w-full text-gray-300" viewBox="0 0 22 80" fill="none" preserveAspectRatio="none">
                            <path d="M0 -2L20 40L0 82" vector-effect="non-scaling-stroke" stroke="currentcolor"
                                stroke-linejoin="round" />
                        </svg>
                    </div>
                </template>
            </li>
        </ol>
    </nav>
</template>
