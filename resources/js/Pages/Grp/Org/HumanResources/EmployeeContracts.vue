<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import EmployeeContractDialog from '@/Components/HumanResources/EmployeeContractDialog.vue'
import TableEmployeeContracts from '@/Components/Tables/Grp/Org/HumanResources/TableEmployeeContracts.vue'
import type { LeaveBalanceRow } from '@/Components/HumanResources/LeaveBalanceAdjustmentDialog.vue'
import { capitalize } from '@/Composables/capitalize'

const props = defineProps<{
    title: string
    pageHead: {}
    data: {}
    unlinked_balances: {}
    leave_balances?: LeaveBalanceRow[]
    can_edit?: boolean
    employee_id: number
    default_annual_leave_days?: number
}>()

const isContractDialogVisible = ref(false)
const editedContract = ref<any>(null)

const unlinkedBalances = computed(() => (props.leave_balances ?? []).filter((balance) => balance.employee_contract_id === null))

const openContractDialog = (contract: any = null) => {
    editedContract.value = contract
    isContractDialogVisible.value = true
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-add-contract="{ action }">
            <Button type="create" icon="fal fa-plus" :label="action.label" @click="openContractDialog()" />
        </template>
    </PageHeading>

    <TableEmployeeContracts :data="data" :leaveBalances="leave_balances" :canEdit="can_edit" @edit="openContractDialog" />

    <EmployeeContractDialog
        v-model:visible="isContractDialogVisible"
        :employeeId="employee_id"
        :contract="editedContract"
        :defaultAnnualLeaveDays="default_annual_leave_days"
        :unlinkedBalances="unlinkedBalances"
    />
</template>
