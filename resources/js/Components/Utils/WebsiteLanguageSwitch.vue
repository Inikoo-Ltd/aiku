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
    inTopBar?: boolean
}>()

const countryOfLanguage: Record<string, string> = { en: "gb", "en-gb": "gb", cs: "cz", sv: "se", uk: "ua", da: "dk", el: "gr", et: "ee", sl: "si", ja: "jp" }
const flagUrl = (code: string) => `/flags/${countryOfLanguage[code.toLowerCase()] ?? code.toLowerCase()}.png`
const languageLabel = (code: string) => {
    const language = props.languages.find((option) => option.code === code)
    return language?.native_name || language?.name
}

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
            :class="inTopBar ? 'website-language-switch-topbar' : ''"
            @update:modelValue="onChange"
        >
            <template #option="{ option }">
                <span class="flex items-center gap-2">
                    <img :src="flagUrl(option.code)" alt="" class="h-3 w-4 rounded-sm object-cover" />
                    {{ option.native_name || option.name }}
                </span>
            </template>
            <template #value="{ value }">
                <span class="flex items-center gap-2 whitespace-nowrap">
                    <img :src="flagUrl(value)" alt="" class="h-3 w-4 rounded-sm object-cover" />
                    {{ languageLabel(value) }}
                </span>
            </template>
        </Select>
        <div v-if="errorMessage" class="text-red-500 text-xs mt-1">{{ errorMessage }}</div>
    </div>
</template>

<style scoped>
.website-language-switch-topbar {
    background: transparent;
    border-color: transparent;
    box-shadow: none;
}

.website-language-switch-topbar :deep(.p-select-label),
.website-language-switch-topbar :deep(.p-select-dropdown) {
    color: inherit;
}

.website-language-switch-topbar :deep(.p-select-label) {
    flex: none;
    width: auto;
    padding-right: 0.375rem;
    font-size: inherit;
    line-height: inherit;
}

.website-language-switch-topbar :deep(.p-select-dropdown) {
    width: auto;
    padding-right: 0.5rem;
}

.website-language-switch-topbar.p-disabled {
    background: transparent;
    opacity: 1;
}

.website-language-switch-topbar.p-disabled :deep(.p-select-label),
.website-language-switch-topbar.p-disabled :deep(.p-select-dropdown) {
    color: inherit;
}

.website-language-switch-topbar {
    --p-select-hover-border-color: transparent;
    --p-select-focus-border-color: transparent;
}
</style>
