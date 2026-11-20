<script setup lang="ts">
import { ref } from "vue"
import axios from "axios"
import Select from "primevue/select"
import { ctrans } from "@/Composables/useTrans"

interface WebsiteLanguage {
    id: number
    code: string
    name: string
    native_name?: string | null
}

const props = defineProps<{
    languages: WebsiteLanguage[]
    currentCode: string
    shopLanguageCode: string
    saveToProfileUrl?: string | null
    keepInUrl?: boolean
}>()

const selectedCode = ref(props.currentCode)
const isSaving = ref(false)
const errorMessage = ref<string | null>(null)

const goToLanguageInUrl = (code: string) => {
    const url = new URL(window.location.href)
    if (code === props.shopLanguageCode) {
        url.searchParams.delete("locale")
    } else {
        url.searchParams.set("locale", code)
    }
    window.location.assign(url.toString())
}

const onChange = async (code: string) => {
    errorMessage.value = null
    const language = props.languages.find((option) => option.code === code)
    if (!language) {
        return
    }

    if (!props.saveToProfileUrl) {
        goToLanguageInUrl(code)
        return
    }

    isSaving.value = true
    try {
        await axios.patch(props.saveToProfileUrl, { language_id: language.id })
    } catch {
        if (!props.keepInUrl) {
            isSaving.value = false
            selectedCode.value = props.currentCode
            errorMessage.value = ctrans("The language could not be changed, please try again")
            return
        }
    }

    if (props.keepInUrl) {
        goToLanguageInUrl(code)
    } else {
        window.location.reload()
    }
}
</script>

<template>
    <div class="flex flex-col items-end">
        <Select
            v-model="selectedCode"
            :options="languages"
            optionLabel="name"
            optionValue="code"
            :loading="isSaving"
            :disabled="isSaving"
            :aria-label="ctrans('Language')"
            size="small"
            @update:modelValue="onChange"
        >
            <template #option="{ option }">
                {{ option.native_name || option.name }}
            </template>
            <template #value="{ value }">
                {{ languages.find((option) => option.code === value)?.native_name || languages.find((option) => option.code === value)?.name }}
            </template>
        </Select>
        <div v-if="errorMessage" class="text-red-500 text-xs mt-1">{{ errorMessage }}</div>
    </div>
</template>
