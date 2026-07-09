<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faGift, faRepeat, faPencil, faTrash, faPlus, faCubes, faCalendarPlus } from "@fal"
import { faSpinnerThird } from "@fad"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
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

const transactions = ref<UpcomingTransaction[]>([])
const total = ref(0)
const isLoading = ref(true)
const isModalOpen = ref(false)
const modalView = ref<"list" | "form">("list")
const editingTransaction = ref<UpcomingTransaction | null>(null)
const canReturnToList = ref(false)
const deletingId = ref<number | null>(null)
const confirmingDeleteId = ref<number | null>(null)

const modalWidth = computed(() => (modalView.value === "list" ? "w-full max-w-3xl" : "w-full max-w-2xl"))

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

const openListModal = () => {
    modalView.value = "list"
    editingTransaction.value = null
    confirmingDeleteId.value = null
    isModalOpen.value = true
}

const openCreateForm = (returnToList: boolean) => {
    editingTransaction.value = null
    canReturnToList.value = returnToList
    modalView.value = "form"
    isModalOpen.value = true
}

const openEditForm = (transaction: UpcomingTransaction) => {
    editingTransaction.value = transaction
    canReturnToList.value = true
    modalView.value = "form"
}

watch(() => props.openFormSignal, () => openCreateForm(false))

const closeModal = () => {
    isModalOpen.value = false
    editingTransaction.value = null
    confirmingDeleteId.value = null
    modalView.value = "list"
}

const onFormClosed = () => {
    if (canReturnToList.value) {
        editingTransaction.value = null
        modalView.value = "list"

        return
    }

    closeModal()
}

const onSaved = async () => {
    editingTransaction.value = null
    modalView.value = "list"
    await fetchTransactions()
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

        confirmingDeleteId.value = null
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

const formatQuantity = (quantity: number | string) => Number(quantity).toLocaleString()
</script>

<template>
    <div>
        <button
            type="button"
            @click="openListModal"
            class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
        >
            <FontAwesomeIcon icon="fal fa-calendar-plus" class="text-xs text-indigo-500" />
            {{ trans("Upcoming Transactions") }}
            <FontAwesomeIcon v-if="isLoading" icon="fad fa-spinner-third" class="animate-spin text-xs text-gray-400" />
            <span
                v-else-if="total"
                class="rounded-full bg-indigo-50 px-1.5 py-0.5 text-xs font-semibold text-indigo-600 tabular-nums"
            >
                {{ total }}
            </span>
            <span class="text-xs font-medium text-indigo-600">{{ trans("View list") }}</span>
        </button>

        <Modal :isOpen="isModalOpen" @onClose="closeModal" :width="modalWidth">
            <UpcomingTransactionForm
                v-if="modalView === 'form'"
                :key="editingTransaction?.id ?? 'new'"
                :shopSlug="shopSlug"
                :storeRoute="routes.store"
                :transaction="editingTransaction"
                @close="onFormClosed"
                @saved="onSaved"
            />

            <div v-else class="p-1">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="flex items-center gap-2 text-xl font-bold text-gray-900">
                            {{ trans("Upcoming Transactions") }}
                            <span
                                v-if="total"
                                class="rounded-full bg-indigo-50 px-2 py-0.5 text-sm font-semibold text-indigo-600 tabular-nums"
                            >
                                {{ total }}
                            </span>
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ trans("Products reserved as a gift or queued for the customer's next order.") }}
                        </p>
                    </div>

                    <Button
                        :label="trans('Add')"
                        icon="plus"
                        style="create"
                        size="s"
                        @click="() => openCreateForm(true)"
                    />
                </div>

                <div v-if="isLoading" class="space-y-3 py-2">
                    <div v-for="i in 3" :key="i" class="flex animate-pulse items-start gap-3">
                        <div class="h-11 w-11 flex-shrink-0 rounded-md bg-gray-200" />
                        <div class="flex-1 space-y-2 pt-1">
                            <div class="h-3 w-1/3 rounded bg-gray-200" />
                            <div class="h-2 w-1/2 rounded bg-gray-100" />
                        </div>
                    </div>
                </div>

                <div v-else-if="transactions.length" class="max-h-[26rem] divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200">
                    <div
                        v-for="transaction in transactions"
                        :key="transaction.id"
                        class="flex items-start gap-3 px-3 py-3 transition-colors hover:bg-gray-50"
                        :class="{ 'opacity-50': deletingId === transaction.id }"
                    >
                        <div
                            class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-md ring-1"
                            :class="upcomingTransactionTypeMeta[transaction.type].iconWrapperClass"
                        >
                            <FontAwesomeIcon
                                :icon="upcomingTransactionTypeMeta[transaction.type].icon"
                                :class="upcomingTransactionTypeMeta[transaction.type].iconClass"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-sm font-semibold text-gray-800">{{ transaction.product_code }}</span>
                                <span class="flex-shrink-0 rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600 tabular-nums">
                                    ×{{ formatQuantity(transaction.quantity) }}
                                </span>
                                <span
                                    class="inline-flex flex-shrink-0 items-center gap-1 whitespace-nowrap rounded-full px-1.5 py-0.5 text-xs font-medium ring-1"
                                    :class="upcomingTransactionTypeMeta[transaction.type].badgeClass"
                                >
                                    <FontAwesomeIcon :icon="upcomingTransactionTypeMeta[transaction.type].icon" class="text-xs" />
                                    {{ trans(upcomingTransactionTypeMeta[transaction.type].label) }}
                                </span>
                            </div>

                            <div v-if="transaction.product_name" class="mt-0.5 truncate text-xs text-gray-400">
                                {{ transaction.product_name }}
                            </div>

                            <p v-if="transaction.notes" class="mt-1 text-xs italic text-gray-500">
                                {{ transaction.notes }}
                            </p>
                        </div>

                        <div
                            v-if="confirmingDeleteId === transaction.id"
                            class="flex flex-shrink-0 items-center gap-2"
                        >
                            <span class="text-xs text-gray-600">{{ trans("Delete?") }}</span>
                            <Button
                                :label="trans('Cancel')"
                                :style="'tertiary'"
                                size="xs"
                                @click="() => (confirmingDeleteId = null)"
                            />
                            <Button
                                :label="trans('Delete')"
                                :style="'red'"
                                size="xs"
                                :loading="deletingId === transaction.id"
                                :disabled="deletingId === transaction.id"
                                @click="() => deleteTransaction(transaction)"
                            />
                        </div>

                        <div v-else class="flex flex-shrink-0 items-center gap-1">
                            <button
                                type="button"
                                @click="() => openEditForm(transaction)"
                                v-tooltip="trans('Edit')"
                                class="rounded p-1.5 text-gray-400 transition-colors hover:bg-indigo-50 hover:text-indigo-600"
                            >
                                <FontAwesomeIcon icon="fal fa-pencil" class="text-xs" fixed-width />
                            </button>
                            <button
                                type="button"
                                @click="() => (confirmingDeleteId = transaction.id)"
                                v-tooltip="trans('Delete')"
                                class="rounded p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600"
                            >
                                <FontAwesomeIcon icon="fal fa-trash" class="text-xs" fixed-width />
                            </button>
                        </div>
                    </div>
                </div>

                <button
                    v-else
                    type="button"
                    @click="() => openCreateForm(true)"
                    class="flex w-full flex-col items-center gap-1 rounded-lg border border-dashed border-gray-300 px-3 py-8 text-center transition-colors hover:border-indigo-300 hover:bg-gray-50"
                >
                    <FontAwesomeIcon icon="fal fa-cubes" class="text-2xl text-gray-300" />
                    <span class="text-sm text-gray-500">{{ trans("No upcoming transactions yet") }}</span>
                    <span class="text-sm font-medium text-indigo-600">{{ trans("Add the first one") }}</span>
                </button>

                <div class="mt-5 flex justify-end">
                    <Button :label="trans('Close')" :style="'tertiary'" @click="closeModal" />
                </div>
            </div>
        </Modal>
    </div>
</template>
