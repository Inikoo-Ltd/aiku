import { ref, computed } from "vue"
import axios from "axios"
export interface LanguageOption {
	id: number
	name: string
	code: string
	flag: string
	native_name: string
	status?: boolean
}

const languages = ref<LanguageOption[]>([])
const isLoadingLanguages = ref(false)
const isLoaded = ref(false)

const resolveUrl = (baseUrl: string) => {
	try {
		if (route().has('grp.chat.languages.index')) {
			return route('grp.chat.languages.index')
		}
	} catch (e) {
		// Ziggy is not loaded on the storefront bundle
	}

	return `${baseUrl}/app/api/chats/languages`
}

export function useChatLanguages(baseUrl: string) {
	const fetchLanguages = async () => {
		if (isLoaded.value || isLoadingLanguages.value) return

		isLoadingLanguages.value = true

		try {
			const { data } = await axios.get(resolveUrl(baseUrl))
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
