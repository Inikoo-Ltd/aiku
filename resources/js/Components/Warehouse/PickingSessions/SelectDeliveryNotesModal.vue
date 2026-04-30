<script setup lang="ts">
import Modal from "@/Components/Utils/Modal.vue"
import { ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLayoutStore } from "@/Stores/layout"

interface DeliveryNoteItem {
    id: number
    reference: string
    state_label: string
    number_items: number
    created_at: string
}

const props = defineProps<{
    isOpen: boolean
    pickingSessionId: number
    mode: 'add' | 'remove'
}>()

const emit = defineEmits<{
    (e: 'onClose'): void
    (e: 'success'): void
}>()

const layout = useLayoutStore()
const deliveryNotes = ref<DeliveryNoteItem[]>([])
const selectedDeliveryNotes = ref<number[]>([])
const isLoading = ref(false)
const isSubmitting = ref(false)
const error = ref<string | null>(null)
const searchQuery = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const activeOrganisation = () => layout.currentParams?.organisation
    ? layout.organisationsState?.[layout.currentParams.organisation]
    : null

const fetchDeliveryNotes = async () => {
    isLoading.value = true
    error.value = null

    const orgState = activeOrganisation()
    const params: Record<string, string> = { mode: props.mode }

    if (orgState?.currentType === 'shop' && orgState.currentShop) {
        params.shop = orgState.currentShop
    } else if (orgState?.currentType === 'fulfilment' && orgState.currentFulfilment) {
        params.fulfilment = orgState.currentFulfilment
    }

    if (searchQuery.value.trim()) {
        params.search = searchQuery.value.trim()
    }

    try {
        const response = await axios.get(
            route('grp.json.picking_session.delivery_notes.index', {
                pickingSession: props.pickingSessionId
            }),
            { params }
        )
        deliveryNotes.value = response.data
    } catch (e) {
        console.log('Failed to load delivery notes', e)
        error.value = trans('Failed to load delivery notes')
    } finally {
        isLoading.value = false
    }
}

const onSearchInput = () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }
    searchTimeout = setTimeout(() => {
        fetchDeliveryNotes()
    }, 350)
}

watch(() => props.isOpen, (isOpen) => {
    if (isOpen) {
        selectedDeliveryNotes.value = []
        searchQuery.value = ''
        error.value = null
        fetchDeliveryNotes()
    }
})

const toggleDeliveryNote = (id: number) => {
    const index = selectedDeliveryNotes.value.indexOf(id)
    if (index === -1) {
        selectedDeliveryNotes.value.push(id)
    } else {
        selectedDeliveryNotes.value.splice(index, 1)
    }
}

const isSelected = (id: number) => selectedDeliveryNotes.value.includes(id)

const handleSubmit = async () => {
    if (selectedDeliveryNotes.value.length === 0) {
        error.value = trans('Please select at least one delivery note')
        return
    }

    isSubmitting.value = true
    error.value = null

    const routeName = props.mode === 'add'
        ? 'grp.models.picking_session.add_delivery_notes'
        : 'grp.models.picking_session.remove_delivery_notes'

    router.patch(
        route(routeName, { pickingSession: props.pickingSessionId }),
        { delivery_notes: selectedDeliveryNotes.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('success')
                emit('onClose')
            },
            onError: (errors) => {
                error.value = Object.values(errors).flat().join(', ') || trans('An error occurred')
            },
            onFinish: () => {
                isSubmitting.value = false
            }
        }
    )
}

const handleClose = () => {
    selectedDeliveryNotes.value = []
    error.value = null
    emit('onClose')
}
</script>

<template>
    <Modal :isOpen="isOpen" @onClose="handleClose" width="w-full max-w-2xl xmin-h-96">
        <div class="flex flex-col gap-4 h-[550px]">
            <h3 class="text-lg font-semibold">
                {{ mode === 'add' ? ctrans('Add Delivery Notes') : ctrans('Remove Delivery Notes') }}
            </h3>

            <input
                v-model="searchQuery"
                @input="onSearchInput"
                type="text"
                :placeholder="ctrans('Search by reference or date...')"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />

            <div v-if="isLoading" class="text-gray-400 text-center py-8 h-full">
                {{ ctrans('Loading...') }}
            </div>

            <div v-else-if="deliveryNotes.length === 0" class="text-gray-500 text-center py-8 h-full">
                {{ mode === 'add' ? ctrans('No available delivery notes to add') : ctrans('No delivery notes in this picking session') }}
            </div>

            <div v-else class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
                <div
                    v-for="dn in deliveryNotes"
                    :key="dn.id"
                    @click="toggleDeliveryNote(dn.id)"
                    class="flex items-center gap-3 p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                    :class="{ 'bg-blue-50': isSelected(dn.id) }"
                >
                    <input
                        type="checkbox"
                        :checked="isSelected(dn.id)"
                        class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                        @click.stop
                        @change="toggleDeliveryNote(dn.id)"
                    />
                    <div class="flex-1">
                        <div class="font-bold">#{{ dn.reference }}</div>
                        <div class="text-sm text-gray-500">{{ useFormatTime(dn.created_at, { formatTime: 'hms'}) }}</div>
                    </div>
                    <div class="flex flex-col items-end gap-0.5">
                        <div class="text-sm text-gray-500">{{ dn.state_label }}</div>
                        <div class="text-xs text-gray-400">{{ dn.number_items }} {{ trans('items') }}</div>
                    </div>
                </div>
            </div>

            <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>

            <div class="place-self-end flex justify-end w-full gap-3 mt-4">
                <Button type="tertiary" key="3" :label="ctrans('Cancel')" @click="handleClose" />
                <Button
                    v-if="mode === 'remove'"
                    type="red"
                    key="2"
                    :label="ctrans('Remove Selected')"
                    :loading="isSubmitting"
                    :disabled="selectedDeliveryNotes.length === 0"
                    @click="handleSubmit"
                    full
                />
                <Button
                    v-if="mode === 'add'"
                    :label="ctrans('Add Selected')"
                    :loading="isSubmitting"
                    :disabled="selectedDeliveryNotes.length === 0"
                    full
                    @click="handleSubmit"
                />
            </div>
        </div>
    </Modal>
</template>


