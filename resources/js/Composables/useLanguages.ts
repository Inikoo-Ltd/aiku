import { ref, computed } from "vue"
import axios from "axios"
export interface LanguageOption {
	id: number
	name: string
	code: string
	flag: string
	native_name: string
}

const languages = ref<LanguageOption[]>([])
const isLoadingLanguages = ref(false)
const isLoaded = ref(false)
export function useChatLanguages(baseUrl: string) {
	const fetchLanguages = async () => {
		if (isLoaded.value || isLoadingLanguages.value) return

		isLoadingLanguages.value = true

		try {
			const { data } = await axios.get(`${baseUrl}/app/api/chats/languages`)
			languages.value = data?.data ?? data ?? []
			isLoaded.value = true
		} catch (e) {
			console.error("Failed to fetch languages", e)
		} finally {
			isLoadingLanguages.value = false
		}
	}

	const getLanguageIdByCode = (code: string) => {
		return languages.value.find((l) => l.code === code)?.id
	}

	return {
		languages,
		fetchLanguages,
		isLoadingLanguages,
		getLanguageIdByCode,
	}
}
