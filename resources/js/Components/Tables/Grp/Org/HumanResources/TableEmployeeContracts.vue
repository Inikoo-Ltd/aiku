<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPencil, faTrash, faPlus } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

library.add(faPencil, faTrash, faPlus)

defineProps<{
    data: any
    unlinkedBalances?: any
    tab?: string
}>()

function generateBalance(generateRoute: { name: string; parameters: object }) {
    router.post(route(generateRoute.name, generateRoute.parameters), {}, { preserveScroll: true })
}
</script>

<template>
    <div>
        <!-- Contracts table -->
        <Table :resource="data" :name="tab">

            <template #cell(start_date)="{ item: contract }">
                <span class="whitespace-nowrap">
                    {{ useFormatTime(contract.start_date) }}
                </span>
            </template>

            <template #cell(end_date)="{ item: contract }">
                <span v-if="contract.end_date" class="whitespace-nowrap">
                    {{ useFormatTime(contract.end_date) }}
                </span>
                <span v-else class="text-gray-400 italic text-xs">{{ trans('Open-ended') }}</span>
            </template>

            <template #cell(annual_leave_days)="{ item: contract }">
                <span class="tabular-nums font-semibold">{{ contract.annual_leave_days }}</span>
                <span class="text-gray-400 text-xs ml-1">{{ trans('days') }}</span>
            </template>

            <template #cell(balance)="{ item: contract }">
                <div v-if="contract.balance" class="text-sm tabular-nums">
                    <span class="text-gray-500 dark:text-gray-400">{{ trans('Used') }}:</span>
                    <span class="font-semibold ml-1">{{ contract.balance.annual_used }}</span>
                    <span class="text-gray-400 mx-1">/</span>
                    <span class="font-semibold">{{ contract.annual_leave_days }}</span>
                    <span class="text-gray-400 text-xs ml-1">{{ trans('days') }}</span>
                    <span
                        class="ml-2 text-xs"
                        :class="contract.balance.annual_remaining > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500'"
                    >
                        ({{ contract.balance.annual_remaining }} {{ trans('left') }})
                    </span>
                </div>
                <button
                    v-else-if="contract.generate_balance_route"
                    class="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400"
                    @click="generateBalance(contract.generate_balance_route)"
                >
                    <FontAwesomeIcon :icon="faPlus" class="text-xs" fixed-width />
                    {{ trans('Generate balance') }}
                </button>
            </template>

            <template #cell(actions)="{ item: contract }">
                <div class="flex items-center gap-3">

                    <Link
                        v-if="contract.edit_route"
                        :href="route(contract.edit_route.name, contract.edit_route.parameters)"
                        class="primaryLink text-xs flex items-center gap-1"
                    >
                        <FontAwesomeIcon :icon="faPencil" class="text-xs" fixed-width />
                        {{ trans('Edit') }}
                    </Link>

                    <ModalConfirmationDelete
                        v-if="contract.delete_route"
                        :routeDelete="contract.delete_route"
                        :title="trans('Delete this contract?')"
                        :description="trans('This will also delete the leave balance associated with this contract. This action cannot be undone.')"
                    >
                        <template #default="{ isOpenModal, changeModel }">
                            <button
                                class="text-red-500 hover:text-red-700 text-xs flex items-center gap-1"
                                @click="changeModel(true)"
                            >
                                <FontAwesomeIcon :icon="faTrash" class="text-xs" fixed-width />
                                {{ trans('Delete') }}
                            </button>
                        </template>
                    </ModalConfirmationDelete>
                </div>
            </template>

        </Table>
    </div>
</template>
