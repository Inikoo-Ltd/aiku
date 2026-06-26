<script setup lang="ts">
import Modal from "@/Components/Utils/Modal.vue"
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import { useFormatTime } from "@/Composables/useFormatTime"

interface PalletReturnItem {
    id: number
    reference: string
    state_label: string
    number_items: number
    type?: string | null
    created_at?: string | null
}

const props = defineProps<{
    isOpen: boolean
    pickingSessionId: number
    mode: "add" | "remove"
    returnType?: "stored_item" | "pallet" | string
}>()

const emit = defineEmits<{
    (e: "onClose"): void
    (e: "success"): void
}>()

const palletReturns = ref<PalletReturnItem[]>([])
const selectedPalletReturns = ref<number[]>([])
const isLoading = ref(false)
const isSubmitting = ref(false)
const error = ref<string | null>(null)
const normalizedReturnType = computed<"stored_item" | "pallet">(() => {
    if (typeof props.returnType !== "string") {
        return "pallet"
    }

    const normalized = props.returnType.toLowerCase().replace(/-/g, "_")
    return normalized === "stored_item" ? "stored_item" : "pallet"
})

const fetchPalletReturns = async () => {
    isLoading.value = true
    error.value = null

    try {
        const response = await axios.get(
            route("grp.json.picking_session.pallet_returns.index", {
                pickingSession: props.pickingSessionId,
            }),
            {
                params: {
                    mode: props.mode,
                    return_type: normalizedReturnType.value,
                },
            }
        )
        palletReturns.value = response.data
    } catch {
        error.value = trans("Failed to load pallet returns")
    } finally {
        isLoading.value = false
    }
}

watch(
    () => props.isOpen,
    (isOpen) => {
        if (isOpen) {
            selectedPalletReturns.value = []
            error.value = null
            fetchPalletReturns()
        }
    }
)

const togglePalletReturn = (id: number) => {
    const index = selectedPalletReturns.value.indexOf(id)
    if (index === -1) {
        selectedPalletReturns.value.push(id)
    } else {
        selectedPalletReturns.value.splice(index, 1)
    }
}

const isSelected = (id: number): boolean => selectedPalletReturns.value.includes(id)

const handleSubmit = () => {
    if (selectedPalletReturns.value.length === 0) {
        error.value = trans("Please select at least one pallet return")
        return
    }

    isSubmitting.value = true
    error.value = null

    const routeName =
        props.mode === "add"
            ? "grp.models.picking_session.add_pallet_returns"
            : "grp.models.picking_session.remove_pallet_returns"

    router.patch(
        route(routeName, { pickingSession: props.pickingSessionId }),
        { pallet_returns: selectedPalletReturns.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                emit("success")
                emit("onClose")
            },
            onError: (errors) => {
                error.value =
                    Object.values(errors).flat().join(", ") || trans("An error occurred")
            },
            onFinish: () => {
                isSubmitting.value = false
            },
        }
    )
}

const handleClose = () => {
    selectedPalletReturns.value = []
    error.value = null
    emit("onClose")
}
</script>

<template>
    <Modal :isOpen="isOpen" @onClose="handleClose" width="w-full max-w-2xl min-h-96">
        <div class="flex flex-col gap-4">
            <h3 class="text-lg font-semibold">
                {{ mode === "add" ? ctrans("Add Pallet Returns") : ctrans("Remove Pallet Returns") }}
            </h3>

            <div v-if="isLoading" class="text-gray-400 text-center py-8 h-full">
                {{ ctrans("Loading...") }}
            </div>

            <div v-else-if="palletReturns.length === 0" class="text-gray-500 text-center py-8">
                {{
                    mode === "add"
                        ? ctrans("No available pallet returns to add")
                        : ctrans("No pallet returns in this picking session")
                }}
            </div>

            <div v-else class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
                <div
                    v-for="palletReturn in palletReturns"
                    :key="palletReturn.id"
                    @click="togglePalletReturn(palletReturn.id)"
                    class="flex items-center gap-3 p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                    :class="{ 'bg-blue-50': isSelected(palletReturn.id) }"
                >
                    <input
                        type="checkbox"
                        :checked="isSelected(palletReturn.id)"
                        class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                        @click.stop
                        @change="togglePalletReturn(palletReturn.id)"
                    />
                    <div class="flex-1">
                        <div class="font-bold">#{{ palletReturn.reference }}</div>
                        <div v-if="palletReturn.created_at" class="text-sm text-gray-500">
                            {{ useFormatTime(palletReturn.created_at, { formatTime: "hms" }) }}
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-0.5">
                        <div class="text-sm text-gray-500">{{ palletReturn.state_label }}</div>
                        <div class="text-xs text-gray-400">
                            {{ palletReturn.number_items }} {{ trans("items") }}
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>

            <div class="flex justify-end gap-3 mt-4">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="handleClose" />
                <Button
                    v-if="mode === 'remove'"
                    type="red"
                    :label="ctrans('Remove Selected')"
                    :loading="isSubmitting"
                    :disabled="selectedPalletReturns.length === 0"
                    @click="handleSubmit"
                    full
                />
                <Button
                    v-else
                    :label="ctrans('Add Selected')"
                    :loading="isSubmitting"
                    :disabled="selectedPalletReturns.length === 0"
                    @click="handleSubmit"
                    full
                />
            </div>
        </div>
    </Modal>
</template>
