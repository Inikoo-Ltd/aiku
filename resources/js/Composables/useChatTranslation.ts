import { ref } from "vue"
import axios from "axios"

export const chatAutoTranslate = ref(true)
export const chatMyLanguages = ref<string[]>([])
export const chatOwnLanguage = ref<string | null>(null)

export const loadChatTranslation = (user: any) => {
    chatAutoTranslate.value = user?.settings?.chat_auto_translate ?? true
    chatMyLanguages.value = user?.settings?.chat_languages ?? []
    chatOwnLanguage.value = user?.chat_language_code ?? null
}

export const readsLanguage = (code: string | null | undefined) =>
    !!code && (code === chatOwnLanguage.value || chatMyLanguages.value.includes(code))

export const saveChatTranslation = () => axios.patch(route("grp.models.profile.update"), {
    chat_auto_translate: chatAutoTranslate.value,
    chat_languages: chatMyLanguages.value,
})
