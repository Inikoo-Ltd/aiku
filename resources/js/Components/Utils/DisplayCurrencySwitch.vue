<script setup lang="ts">
import { ref } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import Select from "primevue/select"
import { ctrans } from "@/Composables/useTrans"
import type { DisplayCurrency } from "@/Composables/useDisplayCurrency"

const props = defineProps<{
    currencies: DisplayCurrency[]
    currentCode: string
    saveToProfileUrl: string
}>()

const selectedCode = ref(props.currentCode)
const isSaving = ref(false)
const errorMessage = ref<string | null>(null)

const onChange = async (code: string) => {
    errorMessage.value = null
    isSaving.value = true
    try {
        await axios.patch(props.saveToProfileUrl, { display_currency: code })
        router.reload({ onFinish: () => (isSaving.value = false) })
    } catch {
        isSaving.value = false
        selectedCode.value = props.currentCode
        errorMessage.value = ctrans("The currency could not be changed, please try again")
    }
}
</script>

<template>
    <div class="flex flex-col items-end">
        <Select
            v-model="selectedCode"
            :options="currencies"
            optionLabel="code"
            optionValue="code"
            :loading="isSaving"
            :disabled="isSaving"
            :aria-label="ctrans('Show prices in')"
            v-tooltip="ctrans('Show prices in')"
            size="small"
            @update:modelValue="onChange"
        >
            <template #option="{ option }">
                {{ option.code }} <span class="text-gray-400 text-xs ml-1">{{ option.name }}</span>
            </template>
        </Select>
        <div v-if="errorMessage" class="text-red-500 text-xs mt-1">{{ errorMessage }}</div>
    </div>
</template>
