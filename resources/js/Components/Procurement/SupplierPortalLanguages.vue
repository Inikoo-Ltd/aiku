<script setup lang="ts">
import { onMounted, ref } from "vue"
import { loadLanguageAsync } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"

const props = withDefaults(
	defineProps<{
		language: string
		dark?: boolean
	}>(),
	{ dark: false }
)

const languages = [
	{ code: "en", label: "English" },
	{ code: "zh-Hans", label: "中文" },
	{ code: "id", label: "Bahasa Indonesia" },
	{ code: "hi", label: "हिन्दी" },
	{ code: "ne", label: "नेपाली" },
	{ code: "sk", label: "Slovenčina" },
	{ code: "cs", label: "Čeština" },
	{ code: "es", label: "Español" },
]

const current = ref(props.language)

const choose = (code: string) => {
	current.value = code
	loadLanguageAsync(code)
	document.cookie = `supplier_portal_language=${encodeURIComponent(code)}; path=/; max-age=31536000; SameSite=Lax`
	router.reload({ preserveScroll: true })
}

onMounted(() => loadLanguageAsync(props.language))
</script>

<template>
	<nav :class="dark ? 'flex items-center gap-x-3 text-xs' : 'flex flex-wrap justify-center gap-x-4 gap-y-1 text-sm'" :aria-label="ctrans('Language')">
		<button
			v-for="language in languages"
			:key="language.code"
			type="button"
			:lang="language.code"
			class="rounded px-1 py-0.5 transition"
			:class="dark
				? (current === language.code ? 'whitespace-nowrap font-semibold text-white' : 'whitespace-nowrap text-slate-400 hover:text-white')
				: (current === language.code ? 'font-semibold text-gray-900' : 'text-gray-600 hover:text-gray-900')"
			@click="choose(language.code)">
			{{ language.label }}
		</button>
	</nav>
</template>
