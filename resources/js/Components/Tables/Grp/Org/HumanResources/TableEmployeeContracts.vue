<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import Table from '@/Components/Table/Table.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import LeaveBalanceChangesPreview from '@/Components/HumanResources/LeaveBalanceChangesPreview.vue'
import LeaveBalanceAdjustmentDialog, { type LeaveBalanceRow } from '@/Components/HumanResources/LeaveBalanceAdjustmentDialog.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { useLeaveBalancePreview } from '@/Composables/useLeaveBalancePreview'
import { ctrans } from '@/Composables/useTrans'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPencil, faTrash, faPlus, faTimes, faExclamationTriangle, faSlidersH } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

library.add(faPencil, faTrash, faPlus, faTimes, faExclamationTriangle, faSlidersH)

const props = defineProps<{
    data: any
    leaveBalances?: LeaveBalanceRow[]
    canEdit?: boolean
    tab?: string
}>()

const emit = defineEmits<{
    (e: 'edit', contract: any): void
}>()

const balancesOf = (contractId: number | null): LeaveBalanceRow[] =>
    (props.leaveBalances ?? []).filter((balance) => balance.employee_contract_id === contractId)

const unlinkedBalances = computed(() => balancesOf(null))

const days = (value: number | null | undefined): string => String(Number(value ?? 0))

const periodLabel = (balance: LeaveBalanceRow): string =>
    balance.period_start
        ? `${useFormatTime(balance.period_start)} – ${balance.period_end ? useFormatTime(balance.period_end) : ctrans('open-ended')}`
        : ctrans('No period')

const hasAdjustment = (balance: LeaveBalanceRow): boolean =>
    Number(balance.annual_adjustment) !== 0 || Number(balance.medical_adjustment) !== 0 || Number(balance.unpaid_adjustment) !== 0

function generateBalance(generateRoute: { name: string; parameters: object }) {
    router.post(route(generateRoute.name, generateRoute.parameters), {}, { preserveScroll: true })
}

const adjustedBalance = ref<LeaveBalanceRow | null>(null)
const isAdjustmentVisible = ref(false)

const openAdjustment = (balance: LeaveBalanceRow) => {
    adjustedBalance.value = balance
    isAdjustmentVisible.value = true
}

const contractToDelete = ref<any>(null)
const isDeleteVisible = ref(false)
const isDeleting = ref(false)
const deleteErrors = ref<Record<string, string>>({})

const deletePreview = useLeaveBalancePreview(
    () => route('grp.models.employee.contracts.preview', { contract: contractToDelete.value?.id }),
    () => ({ operation: 'delete' })
)

const openDelete = async (contract: any) => {
    contractToDelete.value = contract
    deleteErrors.value = {}
    deletePreview.reset()
    isDeleteVisible.value = true
    await deletePreview.load()
}

const onDeleteChoices = (choices: Record<string, 'carry' | 'recorded'>) => {
    deletePreview.choices.value = choices
    deletePreview.load(true)
}

const confirmDelete = () => {
    if (!contractToDelete.value) {
        return
    }

    router.delete(route(contractToDelete.value.delete_route.name, contractToDelete.value.delete_route.parameters), {
        data: {
            confirm_balance_changes: true,
            balance_choices: deletePreview.choices.value,
        },
        preserveScroll: true,
        onStart: () => {
            isDeleting.value = true
            deleteErrors.value = {}
        },
        onSuccess: () => {
            isDeleteVisible.value = false
        },
        onError: (errors: Record<string, string>) => {
            deleteErrors.value = errors
        },
        onFinish: () => {
            isDeleting.value = false
        },
    })
}

const deleteError = computed(
    () => deleteErrors.value.balance_changes ?? deleteErrors.value.balance_choices ?? deletePreview.errors.value.balance_changes ?? Object.values(deletePreview.errors.value)[0]
)
</script>

<template>
    <div>
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
                <span v-else class="text-gray-400 italic text-xs">{{ ctrans('Open-ended') }}</span>
            </template>

            <template #cell(annual_leave_days)="{ item: contract }">
                <span class="tabular-nums font-semibold">{{ contract.annual_leave_days }}</span>
                <span class="text-gray-400 text-xs ml-1">{{ ctrans('days') }}</span>
            </template>

            <template #cell(balance)="{ item: contract }">
                <ul v-if="balancesOf(contract.id).length" class="space-y-1.5">
                    <li v-for="balance in balancesOf(contract.id)" :key="balance.id" class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm tabular-nums">
                        <span class="whitespace-nowrap text-xs text-gray-500">{{ periodLabel(balance) }}</span>
                        <span class="whitespace-nowrap">
                            <span class="font-semibold">{{ days(balance.annual_used) }}</span>
                            <span class="mx-1 text-gray-400">/</span>
                            <span class="font-semibold">{{ days(balance.annual_allowance) }}</span>
                            <span class="ml-1 text-xs text-gray-400">{{ ctrans('days') }}</span>
                            <span class="ml-1 text-xs" :class="Number(balance.annual_remaining) > 0 ? 'text-green-600' : 'text-red-500'">
                                ({{ days(balance.annual_remaining) }} {{ ctrans('remaining') }})
                            </span>
                        </span>
                        <span v-if="hasAdjustment(balance)" class="whitespace-nowrap rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                            {{ ctrans('incl. :days adjusted', { days: days(balance.annual_adjustment) }) }}
                        </span>
                        <Button v-if="canEdit" type="tertiary" size="xs" :label="ctrans('Adjust')" @click="openAdjustment(balance)">
                            <template #icon>
                                <FontAwesomeIcon :icon="faSlidersH" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </li>
                </ul>
                <Button
                    v-else-if="contract.generate_balance_route && canEdit"
                    type="tertiary"
                    size="xs"
                    :label="ctrans('Generate balance')"
                    @click="generateBalance(contract.generate_balance_route)"
                >
                    <template #icon>
                        <FontAwesomeIcon :icon="faPlus" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </template>

            <template #cell(actions)="{ item: contract }">
                <div v-if="canEdit" class="flex flex-wrap items-center justify-end gap-2">
                    <Button type="tertiary" size="xs" :label="ctrans('Edit')" @click="emit('edit', contract)">
                        <template #icon>
                            <FontAwesomeIcon :icon="faPencil" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button v-if="contract.delete_route" type="tertiary" size="xs" :label="ctrans('Delete')" @click="openDelete(contract)">
                        <template #icon>
                            <FontAwesomeIcon :icon="faTrash" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </template>
        </Table>

        <section v-if="unlinkedBalances.length" class="mx-4 mt-4 rounded-lg border border-gray-200 bg-white">
            <header class="border-b border-gray-100 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">
                {{ ctrans('Leave balances without a contract') }}
            </header>
            <ul class="divide-y divide-gray-100">
                <li v-for="balance in unlinkedBalances" :key="balance.id" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 text-sm tabular-nums">
                    <span class="text-gray-700">
                        {{ ctrans('Annual') }} <span class="font-semibold">{{ days(balance.annual_used) }}</span>
                        <span class="mx-1 text-gray-300">·</span>
                        {{ ctrans('Medical') }} <span class="font-semibold">{{ days(balance.medical_used) }}</span>
                        <span class="mx-1 text-gray-300">·</span>
                        {{ ctrans('Unpaid') }} <span class="font-semibold">{{ days(balance.unpaid_used) }}</span>
                    </span>
                    <Button v-if="canEdit" type="tertiary" size="xs" :label="ctrans('Adjust')" class="ml-auto" @click="openAdjustment(balance)">
                        <template #icon>
                            <FontAwesomeIcon :icon="faSlidersH" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </li>
            </ul>
        </section>

        <LeaveBalanceAdjustmentDialog v-model:visible="isAdjustmentVisible" :balance="adjustedBalance" />

        <Dialog
            v-model:visible="isDeleteVisible"
            modal
            :draggable="false"
            :header="ctrans('Delete this contract?')"
            :style="{ width: deletePreview.requiresConfirmation.value ? '40rem' : '28rem', maxWidth: 'calc(100vw - 2rem)' }"
        >
            <div class="space-y-4">
                <p class="text-sm text-gray-600">
                    {{ deletePreview.requiresConfirmation.value
                        ? ctrans('Deleting the contract removes its yearly leave balances. Choose what happens to their figures. No leave request is deleted.')
                        : ctrans('Its leave balances hold no figures, so nothing else changes. This cannot be undone.') }}
                </p>

                <LeaveBalanceChangesPreview
                    v-if="deletePreview.requiresConfirmation.value"
                    :choices="deletePreview.choices.value"
                    :entries="deletePreview.entries.value"
                    :loading="deletePreview.isLoading.value"
                    @update:choices="onDeleteChoices"
                />

                <p v-if="deleteError" class="text-sm text-red-500">{{ deleteError }}</p>

                <div class="flex justify-end gap-2 pt-2">
                    <Button type="tertiary" :label="ctrans('Cancel')" :disabled="isDeleting" @click="isDeleteVisible = false">
                        <template #icon>
                            <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button type="tertiary" :label="ctrans('Delete contract')" :loading="isDeleting" :disabled="deletePreview.isLoading.value" @click="confirmDelete">
                        <template #icon>
                            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-amber-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </div>
        </Dialog>
    </div>
</template>
