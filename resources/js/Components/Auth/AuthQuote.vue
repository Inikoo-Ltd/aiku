<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faLanguage } from "@fal"
import { computed, onMounted, ref } from "vue"
import { authQuoteArticleUrl, randomAuthQuote, type AuthQuote } from "@/Composables/useAuthQuote"

const props = defineProps<{
	publicSiteUrl: string
}>()

const quote = ref<AuthQuote | null>(null)
const showEnglishTranslation = ref(false)
const articleUrl = computed(() => {
	return quote.value?.articleSlug
		? authQuoteArticleUrl(props.publicSiteUrl, quote.value.articleSlug)
		: null
})

onMounted(() => {
	quote.value = randomAuthQuote()
})
</script>

<template>
	<figure
		v-if="quote"
		class="absolute top-9 right-10 hidden max-w-xs select-none text-right sm:block"
		style="font-family: Caveat, &quot;Segoe Print&quot;, &quot;Bradley Hand&quot;, cursive">
		<blockquote
			:lang="quote.languageCode"
			:dir="quote.direction"
			class="text-2xl leading-tight text-[#1c1b22]/70">
			<a
				v-if="articleUrl"
				:href="articleUrl"
				class="rounded-sm transition hover:text-[#1c1b22] focus:outline-none focus:ring-1 focus:ring-[#1c1b22]/30"
				title="Read the related engineering note">
				{{ quote.text }}
			</a>
			<template v-else>{{ quote.text }}</template>
		</blockquote>

		<figcaption class="mt-1 flex items-center justify-end gap-x-1.5 text-xs text-[#1c1b22]/45">
			<span>{{ quote.language }}</span>
			<button
				v-if="quote.languageCode !== 'en'"
				type="button"
				class="rounded p-0.5 transition hover:text-[#1c1b22]/80 focus:outline-none focus:ring-1 focus:ring-[#1c1b22]/30"
				:aria-expanded="showEnglishTranslation"
				:aria-label="
					showEnglishTranslation ? 'Hide English translation' : 'Show English translation'
				"
				:title="
					showEnglishTranslation ? 'Hide English translation' : 'Show English translation'
				"
				@click="showEnglishTranslation = !showEnglishTranslation">
				<FontAwesomeIcon :icon="faLanguage" fixed-width aria-hidden="true" />
			</button>
		</figcaption>

		<p
			v-if="showEnglishTranslation"
			lang="en"
			dir="ltr"
			class="mt-1 text-sm leading-snug text-[#1c1b22]/55">
			{{ quote.englishTranslation }}
		</p>
	</figure>
</template>
