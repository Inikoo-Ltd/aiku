<script setup lang="ts">
import { ref, onMounted, inject, watch } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Dropdown from "primevue/dropdown"
import { notify } from "@kyvg/vue3-notification"
import InputSwitch from "primevue/inputswitch"
import InputNumber from "primevue/inputnumber"
import axios from "axios"

import { useChatLanguages } from "@/Composables/useLanguages"

const emit = defineEmits(["close"])

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""
const userId = layout?.user?.id
const isEditMode = ref(false)
const agent = ref<any>(null)

const form = ref({
    max_concurrent_chats: 100,
    is_available: true,
    specialization: [] as string[],
    language_id: null as number | null,
})

const { languages, fetchLanguages } = useChatLanguages(baseUrl)

const specializations = ref<
    { value: string; label: string }[]
>([])

const specializationValue = ref<string | null>(null)

const fetchSpecializations = async () => {
    try {
        const response = await axios.get(
            `${baseUrl}/app/api/chats/agents/specializations`
        )
        specializations.value = response.data.data
    } catch (e) {
        console.error("Failed to fetch specializations", e)
    }
}

const fetchAgentSetting = async () => {
    try {
        const { data } = await axios.get(
            `${baseUrl}/app/api/chats/agents/${userId}`
        )
        const agentData = data.data
        agent.value = agentData

        form.value = {
            max_concurrent_chats: agentData.max_concurrent_chats,
            is_available: agentData.is_available,
            specialization: agentData.specialization ?? [],
            language_id: agentData.language?.id ?? null,
        }

        specializationValue.value = form.value.specialization[0] ?? null
    } catch (e) {
        console.error("Failed to fetch agent setting", e)
    }
}

const saveSettings = async () => {
    try {
        if (form.value.max_concurrent_chats > 100) {
            notify({
                title: "Failed",
                text: "Max concurrent chats must not be greater than 100",
                type: "error"
            })
            return
        }

        await axios.put(
            `${baseUrl}/app/api/chats/agents/${userId}/update`,
            {
                max_concurrent_chats: form.value.max_concurrent_chats,
                is_available: form.value.is_available,
                specialization: form.value.specialization,
                language_id: form.value.language_id,
            }
        )
        isEditMode.value = false
        await fetchAgentSetting()
    } catch (e) {
        console.error("Failed to fetch agent setting", e)
    }
}

watch(specializationValue, (val) => {
    form.value.specialization = val ? [val] : []
})

watch(
    () => specializations.value,
    (opts) => {
        if (!opts.length) return

        form.value.specialization = [...form.value.specialization]
    },
    { immediate: true }
)

onMounted(async () => {
    await fetchSpecializations()
    await fetchLanguages()
    await fetchAgentSetting()
})

</script>

<template>
    <div class="flex flex-col gap-5 text-sm min-w-[520px]">

        <div class="border-b pb-3">
            <div class="font-semibold text-gray-800">
                {{ agent?.user?.name }}
            </div>
            <div class="text-xs text-gray-500">
                Agent Settings
            </div>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs text-gray-500">
                Max Concurrent Chats
            </label>
            <InputNumber v-model="form.max_concurrent_chats" :disabled="!isEditMode" :min="1" class="w-full" />
        </div>

        <div class="flex items-center justify-between">
            <span class="text-gray-700">Available for chats</span>
            <InputSwitch v-model="form.is_available" :disabled="!isEditMode" />
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs text-gray-500">
                Specialization
            </label>
            <Dropdown v-model="specializationValue" :options="specializations" optionLabel="label" optionValue="value"
                placeholder="Select specialization" :disabled="!isEditMode" class="w-full" />
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs text-gray-500">
                Default Language
            </label>
            <Dropdown v-model="form.language_id" :options="languages" optionLabel="name" optionValue="id"
                placeholder="Select language" :disabled="!isEditMode" class="w-full" />
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t">
            <Button v-if="!isEditMode" type="edit" label="Edit" @click="isEditMode = true" />

            <template v-else>
                <Button label="Cancel" type="cancel" @click="isEditMode = false" />
                <Button label="Save" type="save" @click="saveSettings" />
            </template>
        </div>
    </div>
</template>
