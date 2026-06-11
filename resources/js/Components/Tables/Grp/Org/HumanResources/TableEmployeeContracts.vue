<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPencil, faTrash } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

library.add(faPencil, faTrash)

defineProps<{
    data: {}
    tab?: string
}>()
</script>

<template>
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
</template>
