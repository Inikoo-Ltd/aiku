<script setup lang="ts">
import { onMounted, ref, watch } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faGift, faRepeat, faPencil, faTrash, faPlus, faCubes, faCalendarPlus } from "@fal"
import { faSpinnerThird } from "@fad"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import ConfirmPopup from "primevue/confirmpopup"
import { useConfirm } from "primevue/useconfirm"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import UpcomingTransactionForm from "./UpcomingTransactionForm.vue"
import type { UpcomingTransaction, UpcomingTransactionRoutes } from "./upcomingTransaction"
import { upcomingTransactionTypeMeta } from "./upcomingTransaction"

library.add(faGift, faRepeat, faPencil, faTrash, faPlus, faCubes, faCalendarPlus, faSpinnerThird)

const props = defineProps<{
    routes: UpcomingTransactionRoutes
    shopSlug: string
    openFormSignal?: number
}>()

const confirm = useConfirm()

const transactions = ref<UpcomingTransaction[]>([])
const total = ref(0)
const isLoading = ref(true)
const isModalOpen = ref(false)
const editingTransaction = ref<UpcomingTransaction | null>(null)
const deletingId = ref<number | null>(null)

const fetchTransactions = async () => {
    isLoading.value = true

    try {
        const response = await axios.get(route(props.routes.index.name, props.routes.index.parameters))        
        transactions.value = response.data?.data ?? []
        total.value = response.data?.meta?.total ?? transactions.value.length
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error?.response?.data?.message || trans("Failed to load upcoming transactions"),
            type: "error",
        })
    } finally {
        isLoading.value = false
    }
}

onMounted(fetchTransactions)

const openCreateModal = () => {
    editingTransaction.value = null
    isModalOpen.value = true
}

watch(() => props.openFormSignal, openCreateModal)

const openEditModal = (transaction: UpcomingTransaction) => {
    editingTransaction.value = transaction
    isModalOpen.value = true
}

const closeModal = () => {
    isModalOpen.value = false
    editingTransaction.value = null
}

const onSaved = () => {
    closeModal()
    fetchTransactions()
}

const deleteTransaction = async (transaction: UpcomingTransaction) => {
    deletingId.value = transaction.id

    try {
        await axios.delete(route(transaction.delete.name, transaction.delete.parameters))

        notify({
            title: trans("Success"),
            text: trans("Upcoming transaction deleted"),
            type: "success",
        })

        await fetchTransactions()
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error?.response?.data?.message || trans("Failed to delete, please try again"),
            type: "error",
        })
    } finally {
        deletingId.value = null
    }
}

const confirmDelete = (event: Event, transaction: UpcomingTransaction) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: trans("Are you sure you want to delete this upcoming transaction?"),
        group: "upcoming-transaction",
        accept: () => deleteTransaction(transaction),
    })
}
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-3 py-2">
            <div class="flex items-center gap-2 min-w-0">
                <FontAwesomeIcon icon="fal fa-calendar-plus" class="text-xs text-indigo-500" />
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 truncate">
                    {{ trans("Upcoming Transactions") }}
                </span>
                <span
                    v-if="total"
                    class="rounded-full bg-indigo-50 px-1.5 py-0.5 text-xs font-semibold text-indigo-600 tabular-nums"
                >
                    {{ total }}
                </span>
            </div>           
        </div>

        <div v-if="isLoading" class="space-y-3 px-3 py-4">
            <div v-for="i in 2" :key="i" class="flex animate-pulse items-start gap-3">
                <div class="h-10 w-10 flex-shrink-0 rounded-md bg-gray-200" />
                <div class="flex-1 space-y-2 pt-1">
                    <div class="h-3 w-3/4 rounded bg-gray-200" />
                    <div class="h-2 w-1/2 rounded bg-gray-100" />
                </div>
            </div>
        </div>

        <div v-else-if="transactions.length" class="max-h-64 divide-y divide-gray-100 overflow-y-auto">
            <div
                v-for="transaction in transactions"
                :key="transaction.id"
                class="group flex items-start gap-3 px-3 py-2.5 transition-colors hover:bg-gray-50"
                :class="{ 'opacity-50': deletingId === transaction.id }"
            >
                <div
                    class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md ring-1"
                    :class="upcomingTransactionTypeMeta[transaction.type].iconWrapperClass"
                >
                    <FontAwesomeIcon
                        :icon="upcomingTransactionTypeMeta[transaction.type].icon"
                        :class="upcomingTransactionTypeMeta[transaction.type].iconClass"
                    />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <span class="truncate text-sm font-semibold text-gray-800">{{ transaction.product_code }}</span>
                        <span class="flex-shrink-0 rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600 tabular-nums">
                            ×{{ transaction.quantity }}
                        </span>
                    </div>

                    <div class="mt-0.5 flex items-center gap-1.5">
                        <span
                            class="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-xs font-medium ring-1"
                            :class="upcomingTransactionTypeMeta[transaction.type].badgeClass"
                        >
                            <FontAwesomeIcon :icon="upcomingTransactionTypeMeta[transaction.type].icon" class="text-xs" />
                            {{ trans(upcomingTransactionTypeMeta[transaction.type].label) }}
                        </span>
                        <span v-if="transaction.product_name" class="truncate text-xs text-gray-400">
                            {{ transaction.product_name }}
                        </span>
                    </div>

                    <p v-if="transaction.notes" v-tooltip="transaction.notes" class="mt-1 truncate text-xs italic text-gray-500">
                        {{ transaction.notes }}
                    </p>
                </div>

                <div class="flex flex-shrink-0 items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                    <button
                        type="button"
                        @click="() => openEditModal(transaction)"
                        v-tooltip="trans('Edit')"
                        class="rounded p-1.5 text-gray-400 transition-colors hover:bg-indigo-50 hover:text-indigo-600"
                    >
                        <FontAwesomeIcon icon="fal fa-pencil" class="text-xs" fixed-width />
                    </button>
                    <button
                        type="button"
                        :disabled="deletingId === transaction.id"
                        @click="(event) => confirmDelete(event, transaction)"
                        v-tooltip="trans('Delete')"
                        class="rounded p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600"
                    >
                        <FontAwesomeIcon
                            :icon="deletingId === transaction.id ? 'fad fa-spinner-third' : 'fal fa-trash'"
                            :class="{ 'animate-spin': deletingId === transaction.id }"
                            class="text-xs"
                            fixed-width
                        />
                    </button>
                </div>
            </div>
        </div>

        <button
            v-else
            type="button"
            @click="openCreateModal"
            class="flex w-full flex-col items-center gap-1 rounded-b-lg px-3 py-5 text-center transition-colors hover:bg-gray-50"
        >
            <FontAwesomeIcon icon="fal fa-cubes" class="text-lg text-gray-300" />
            <span class="text-xs text-gray-500">{{ trans("No upcoming transactions yet") }}</span>
            <span class="text-xs font-medium text-indigo-600">{{ trans("Add the first one") }}</span>
        </button>

        <ConfirmPopup group="upcoming-transaction">
            <template #container="{ message, acceptCallback, rejectCallback }">
                <div class="w-64 rounded p-4">
                    <span class="text-sm text-gray-700">{{ message.message }}</span>
                    <div class="mt-4 flex items-center gap-2">
                        <Button :label="trans('Cancel')" :style="'tertiary'" full @click="rejectCallback" />
                        <Button :label="trans('Delete')" :style="'red'" @click="acceptCallback" />
                    </div>
                </div>
            </template>
        </ConfirmPopup>

        <Modal :isOpen="isModalOpen" @onClose="closeModal" width="w-full max-w-2xl">
            <UpcomingTransactionForm
                :key="editingTransaction?.id ?? 'new'"
                :shopSlug="shopSlug"
                :storeRoute="routes.store"
                :transaction="editingTransaction"
                @close="closeModal"
                @saved="onSaved"
            />
        </Modal>
    </div>
</template>
