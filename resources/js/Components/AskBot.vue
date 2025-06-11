<script setup lang="ts">
import Modal from "@/Components/Utils/Modal.vue"
import { throttle } from 'lodash-es'
import { ref } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLampDesk } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import { marked } from 'marked'
import LoadingIcon from "./Utils/LoadingIcon.vue"
import LoadingText from "./Utils/LoadingText.vue"

library.add(faLampDesk)

const aiOriResponse = ref(``)
const aiResponse = ref(``)
const isLoadingSearch = ref(false)
const searchValue = ref("")
const errorSearch = ref("")
const lastQuery = ref("")

const isOpen = defineModel<boolean>()

const fetchApi = async (query: string) => {
	if (query.trim() !== "") {
		searchValue.value = ""
		aiResponse.value = ""
		isLoadingSearch.value = true
		errorSearch.value = ""
        aiOriResponse.value = ""
		try {
			// const response = await fetch(route('grp.ask-bot.index', {q: query}))

			const eventSource = new EventSource(route('grp.ask-bot.index', {q: query}));
			eventSource.onmessage = async (event) => {
				isLoadingSearch.value = false
				// console.log('event source', event)
				// console.log('event source data', event.data)
				try {
					const res = JSON.parse(event.data);
					// console.log('-response parse content:', res.choices[0].delta.content)
                    aiOriResponse.value += res.choices[0].delta.content;
                    
                    convertOriResponseToMarkdown()
					// aiResponse.value += await marked.parse(res.choices[0].delta.content)
					// await nextTick()
				} catch (e) {
					isLoadingSearch.value = false
				}
			};

			eventSource.onerror = (e) => {
				isLoadingSearch.value = false
				// console.error('error event source', e)
				eventSource.close();
			};


			// if (!response.ok) {
			// 	const errorData = await response.json()

			// 	throw new Error(errorData.error || `HTTP error! status: ${response.status}`)
			// }

			// const data = await response.json()
			// aiResponse.value = await marked.parse(data.data?.response)
		} catch (error) {
			errorSearch.value = error.message || trans("An error occurred while fetching search results.")
		} finally {
			// isLoadingSearch.value = false
		}
	}
}
const convertOriResponseToMarkdown = throttle(async () => {
	aiResponse.value = await marked.parse(aiOriResponse.value)
}, 300)

const handleKeyDown = (event: KeyboardEvent) => {
	if (event.key === 'Enter' && !event.shiftKey) {
		event.preventDefault()
		lastQuery.value = searchValue.value
		fetchApi(searchValue.value)
		event.target?.blur()
	}
}
</script>

<template>
	<Modal :isOpen="isOpen" @onClose="() => (isOpen = false)" width="w-3/4" height="h-[500px]"
		:dialogStyle="{
			xbackground: 'linear-gradient(to right top, #fff1f2, #faf5ff)'
		}"
	>
		<!-- <div class="animate-linear bg-gradient-to-r from-teal-300 via-pink-600 to-red-400 bg-[length:200%_auto] bg-clip-text font-bold text-transparent text-2xl mb-4 text-center">
			<FontAwesomeIcon icon="fas fa-sparkles" class="text-pink-600" fixed-width aria-hidden="true" />
			{{ trans('Ask AI anything..')}}
		</div> -->
		<div class="font-bold text-pink-600 text-2xl mb-4 text-center">
			<FontAwesomeIcon icon="fas fa-sparkles" class="" fixed-width aria-hidden="true" />
			{{ trans('Ask AI anything..')}}
		</div>

		<div class="relative">
			<textarea
				v-model="searchValue"
				@keydown="(e) => handleKeyDown(e)"
				type="text"
				class="h-48 w-full border-none xborder xborder-dashed xborder-gray-300 bg-black/10 rounded-lg px-4 placeholder:text-gray-500 focus:ring focus:ring-pink-500 focus:outline-none sm:text-sm"
				:placeholder="trans('Ask Anything...')"
			/>

			<!-- Text: Press enter to submit -->
			<div class="mt-2 text-sm text-gray-500">
				{{ trans("Press") }} <span class="border border-gray-400 bg-gray-100 text-gray-700 overflow-hidden px-2 py-0.5 text-xs rounded">Enter</span> {{ trans("to submit") }},
				<span class="border border-gray-400 bg-gray-100 text-gray-700 overflow-hidden px-2 py-0.5 text-xs rounded">Shift</span>+<span class="border border-gray-400 bg-gray-100 text-gray-700 overflow-hidden px-2 py-0.5 text-xs rounded">Enter</span>
				{{ trans("to add new line") }}
			</div>
			<!-- <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
				<FontAwesomeIcon fixed-width icon="fal fa-lamp-desk" aria-hidden="true" />
			</div> -->
		</div>

		

		<Transition name="slide-to-left">
			<div v-if="lastQuery" class="bg-pink-500/5 mt-4 border border-white/40 rounded-lg shadow-md overflow-hidden">
				<div class="text-white ">
					<div v-if="lastQuery" class="bg-pink-600 xtext-gray-700 border-b border-white/40 p-4 font-light">
						{{ trans("Results") }}: <span class="font-semibold">{{ lastQuery }}</span>
						<LoadingIcon v-if="isLoadingSearch" class="ml-1" />
					</div>
			
					<div v-if="isLoadingSearch" class="bg-black/40 h-24 flex items-center justify-center">
						<!-- <div
							v-for="n in 1"
							:key="n"
							class="h-40 skeleton w-full rounded animate-pulse"></div> -->
						<LoadingText />
					</div>

					<!-- Response result -->
					<div v-else-if="aiResponse"
						class=" text-gray-700 mt-2 max-h-[500px] overflow-auto markdown-container pt-2 pb-4 mb-2 px-6"
						v-html="aiResponse"
					/>

					<div v-else-if="!isLoadingSearch && lastQuery" class="p-4">
						<p class="text-center">{{ trans("No results found.") }}</p>
					</div>
				</div>
			</div>
		</Transition>

		<div v-if="!isLoadingSearch && errorSearch" class="mt-4">
			<p class="text-center text-red-500">{{ errorSearch }}</p>
		</div>

		
	</Modal>
</template>

<style lang="scss">
/* Styling paragraphs (p) */
.markdown-container p {
	@apply mb-4 tracking-wide;
}

/* Styling strong elements (bold text) */
.markdown-container strong {
	@apply font-bold;
}

/* Styling ordered lists (ol) */
.markdown-container ol {
	@apply ml-4 pl-2 list-decimal mb-4;
}

/* Styling list items (li) within ordered lists */
.markdown-container ol li {
	@apply mb-2;
}

.markdown-container li p {
	@apply mb-0;
}

/* Styling unordered lists (ul) */
.markdown-container ul {
	@apply ml-4 pl-2 list-disc mb-4;
}

.markdown-container li ul {
	list-style-type: circle
}

/* Styling list items (li) within unordered lists */
.markdown-container ul li {
	@apply mb-2;
}

/* Styling blockquote */
.markdown-container blockquote {
	@apply border-l-4 border-gray-300 pl-4 italic mb-4 text-lg;
}

/* Styling code blocks (pre) */
.markdown-container pre {
	@apply bg-gray-800 text-gray-100 p-4 rounded overflow-x-auto font-mono mb-4;
}

/* Styling inline code (code) */
.markdown-container code {
	@apply py-0.5 px-1.5 text-base font-mono bg-gray-800 text-gray-200 rounded;
}

/* Styling horizontal dividers (hr) */
.markdown-container hr {
	@apply border-t border-gray-300 my-6;
}

</style>
