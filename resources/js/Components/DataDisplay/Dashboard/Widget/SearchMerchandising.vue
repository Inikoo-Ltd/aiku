<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { ref, watch } from "vue"
import axios from "axios"
import { debounce } from "lodash-es"
import { trans } from "laravel-vue-i18n"
import Modal from "@/Components/Utils/Modal.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faRocket, faRandom, faCheck, faSparkles } from "@fal"

library.add(faTimes, faRocket, faRandom, faCheck, faSparkles)

interface RouteRef {
    name: string
    parameters: Record<string, any>
}

interface BoostItem {
    type: 'product' | 'product_category' | 'collection'
    id: number
    code: string
    name: string | null
}

interface SynonymEntry {
    id: string
    synonyms: string[]
}

const props = defineProps<{
    merchandising: {
        search_boosts: BoostItem[]
        synonym_language: string
        routes: {
            boost_candidates: RouteRef
            boosts_update: RouteRef
            synonyms_index: RouteRef
            synonyms_store: RouteRef
            synonyms_delete: RouteRef
            suggestions_index: RouteRef
            suggestions_decide: RouteRef
        }
    }
    topZeroQueries?: { query: string }[]
}>()

const boostTypeLabels: Record<BoostItem['type'], string> = {
    product: 'Product',
    product_category: 'Category',
    collection: 'Collection',
}

// Boosts
const isBoostModalOpen = ref(false)
const boostSelection = ref<BoostItem[]>([...props.merchandising.search_boosts])
const boostQuery = ref("")
const boostCandidates = ref<BoostItem[]>([])
const isSearchingCandidates = ref(false)
const isSavingBoosts = ref(false)

const searchCandidates = debounce(async (q: string) => {
    if (q.length < 2) {
        boostCandidates.value = []
        return
    }
    isSearchingCandidates.value = true
    try {
        const { data } = await axios.get(route(props.merchandising.routes.boost_candidates.name, props.merchandising.routes.boost_candidates.parameters), { params: { q } })
        boostCandidates.value = data
    } finally {
        isSearchingCandidates.value = false
    }
}, 300)

watch(boostQuery, (q) => searchCandidates(q))

const isSelected = (item: BoostItem) =>
    boostSelection.value.some(boost => boost.type === item.type && boost.id === item.id)

const addBoost = (item: BoostItem) => {
    if (boostSelection.value.length >= 3 || isSelected(item)) return
    boostSelection.value.push(item)
}

const removeBoost = (item: BoostItem) => {
    boostSelection.value = boostSelection.value.filter(boost => !(boost.type === item.type && boost.id === item.id))
}

const saveBoosts = () => {
    isSavingBoosts.value = true
    router.post(
        route(props.merchandising.routes.boosts_update.name, props.merchandising.routes.boosts_update.parameters),
        { boosts: boostSelection.value.map(({ type, id }) => ({ type, id })) },
        {
            preserveScroll: true,
            onFinish: () => {
                isSavingBoosts.value = false
                isBoostModalOpen.value = false
            },
        }
    )
}

const openBoostModal = () => {
    boostSelection.value = [...props.merchandising.search_boosts]
    boostQuery.value = ""
    boostCandidates.value = []
    isBoostModalOpen.value = true
}

// Synonyms
const isSynonymModalOpen = ref(false)
const synonyms = ref<SynonymEntry[]>([])
const synonymWords = ref("")
const isLoadingSynonyms = ref(false)
const isSavingSynonym = ref(false)
const synonymError = ref("")

const loadSynonyms = async () => {
    isLoadingSynonyms.value = true
    try {
        const { data } = await axios.get(route(props.merchandising.routes.synonyms_index.name, props.merchandising.routes.synonyms_index.parameters))
        synonyms.value = data
    } finally {
        isLoadingSynonyms.value = false
    }
}

const openSynonymModal = () => {
    synonymWords.value = ""
    synonymError.value = ""
    isSynonymModalOpen.value = true
    loadSynonyms()
    loadSuggestions()
}

const prefillSynonym = (query: string) => {
    const words = synonymWords.value.split(",").map(word => word.trim()).filter(Boolean)
    if (!words.includes(query)) {
        words.push(query)
    }
    synonymWords.value = words.join(", ")
}

const saveSynonym = async () => {
    const words = synonymWords.value.split(",").map(word => word.trim()).filter(Boolean)
    if (words.length < 2) {
        synonymError.value = trans("Enter at least 2 comma-separated words")
        return
    }
    isSavingSynonym.value = true
    synonymError.value = ""
    try {
        await axios.post(route(props.merchandising.routes.synonyms_store.name, props.merchandising.routes.synonyms_store.parameters), { words })
        synonymWords.value = ""
        await loadSynonyms()
    } catch (error: any) {
        synonymError.value = error?.response?.data?.message ?? trans("Could not save synonym")
    } finally {
        isSavingSynonym.value = false
    }
}

const deleteSynonym = async (entry: SynonymEntry) => {
    await axios.delete(route(props.merchandising.routes.synonyms_delete.name, { ...props.merchandising.routes.synonyms_delete.parameters, synonym: entry.id }))
    await loadSynonyms()
}

// AI suggestions
interface Suggestion {
    id: string
    query: string
    words: string[]
    sessions: number
}

const suggestions = ref<Suggestion[]>([])
const decidingSuggestionId = ref<string | null>(null)

const loadSuggestions = async () => {
    try {
        const { data } = await axios.get(route(props.merchandising.routes.suggestions_index.name, props.merchandising.routes.suggestions_index.parameters))
        suggestions.value = data
    } catch {
        suggestions.value = []
    }
}

const decideSuggestion = async (suggestion: Suggestion, decision: 'approve' | 'dismiss') => {
    decidingSuggestionId.value = suggestion.id
    try {
        await axios.post(route(props.merchandising.routes.suggestions_decide.name, {
            ...props.merchandising.routes.suggestions_decide.parameters,
            suggestion: suggestion.id,
            decision,
        }))
        suggestions.value = suggestions.value.filter(item => item.id !== suggestion.id)
        if (decision === 'approve') {
            await loadSynonyms()
        }
    } finally {
        decidingSuggestionId.value = null
    }
}
</script>

<template>
    <div>
    <div class="flex items-center gap-2 flex-wrap">
        <button
            type="button"
            class="px-3 py-1.5 rounded-md text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200 transition"
            v-tooltip="ctrans('Boost up to 3 items to the top of matching storefront searches')"
            @click="openBoostModal"
        >
            <FontAwesomeIcon icon="fal fa-rocket" fixed-width aria-hidden="true" />
            {{ ctrans("Boosted items") }}
        </button>
        <button
            type="button"
            class="px-3 py-1.5 rounded-md text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200 transition"
            v-tooltip="ctrans('Map failed searches and alternative words to what customers meant')"
            @click="openSynonymModal"
        >
            <FontAwesomeIcon icon="fal fa-random" fixed-width aria-hidden="true" />
            {{ ctrans("Synonyms") }}
        </button>
        <span
            v-for="boost in merchandising.search_boosts"
            :key="`${boost.type}-${boost.id}`"
            class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700"
        >
            {{ boost.code }} <span class="text-indigo-400">· {{ ctrans(boostTypeLabels[boost.type]) }}</span>
        </span>
    </div>

    <Modal :isOpen="isBoostModalOpen" width="w-full max-w-lg" @onClose="isBoostModalOpen = false">
        <div class="p-2">
            <h2 class="text-lg font-medium mb-1">
                <FontAwesomeIcon icon="fal fa-rocket" fixed-width aria-hidden="true" />
                {{ ctrans("Boosted items") }}
            </h2>
            <p class="text-sm text-gray-500 mb-4">
                {{ ctrans("Boosted items appear first in storefront search results when they match the search. Maximum 3 items.") }}
            </p>

            <div v-if="boostSelection.length" class="flex flex-wrap gap-2 mb-4">
                <span
                    v-for="boost in boostSelection"
                    :key="`${boost.type}-${boost.id}`"
                    class="inline-flex items-center gap-1.5 text-sm px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700"
                >
                    {{ boost.code }}
                    <span class="text-indigo-400 text-xs">{{ ctrans(boostTypeLabels[boost.type]) }}</span>
                    <button type="button" @click="removeBoost(boost)" :aria-label="ctrans('Remove')">
                        <FontAwesomeIcon icon="fal fa-times" aria-hidden="true" />
                    </button>
                </span>
            </div>
            <p v-else class="text-sm text-gray-400 mb-4">{{ ctrans("No boosted items yet") }}</p>

            <input
                v-model="boostQuery"
                type="text"
                :placeholder="ctrans('Search products, categories or collections...')"
                class="w-full rounded-md border-gray-300 text-sm mb-2"
                :disabled="boostSelection.length >= 3"
            />
            <p v-if="boostSelection.length >= 3" class="text-xs text-amber-600 mb-2">{{ ctrans("Limit of 3 reached, remove one to add another") }}</p>

            <div class="max-h-56 overflow-y-auto divide-y divide-gray-100">
                <div v-if="isSearchingCandidates" class="py-3 text-sm text-gray-400">{{ ctrans("Searching...") }}</div>
                <template v-else>
                <button
                    v-for="candidate in boostCandidates"
                    :key="`${candidate.type}-${candidate.id}`"
                    type="button"
                    class="w-full flex items-center justify-between gap-2 py-2 px-1 text-left text-sm hover:bg-slate-50 disabled:opacity-40"
                    :disabled="isSelected(candidate) || boostSelection.length >= 3"
                    @click="addBoost(candidate)"
                >
                    <span class="truncate">
                        <span class="font-medium">{{ candidate.code }}</span>
                        <span class="text-gray-500"> {{ candidate.name }}</span>
                    </span>
                    <span class="text-xs text-gray-400 shrink-0">{{ ctrans(boostTypeLabels[candidate.type]) }}</span>
                </button>
                </template>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <button
                    type="button"
                    class="px-4 py-2 rounded-md text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200"
                    @click="isBoostModalOpen = false"
                >
                    {{ ctrans("Cancel") }}
                </button>
                <button
                    type="button"
                    class="px-4 py-2 rounded-md text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="isSavingBoosts"
                    @click="saveBoosts"
                >
                    {{ ctrans("Save") }}
                </button>
            </div>
        </div>
    </Modal>

    <Modal :isOpen="isSynonymModalOpen" width="w-full max-w-lg" @onClose="isSynonymModalOpen = false">
        <div class="p-2">
            <h2 class="text-lg font-medium mb-1">
                <FontAwesomeIcon icon="fal fa-random" fixed-width aria-hidden="true" />
                {{ ctrans("Search synonyms") }}
                <span class="text-sm font-normal text-gray-400">· {{ merchandising.synonym_language }}</span>
            </h2>
            <p class="text-sm text-gray-500 mb-4">
                {{ ctrans("Words in a group are treated as equivalent when customers search. Shared by every :language shop, so a fix here helps them all.", { language: merchandising.synonym_language }) }}
            </p>

            <div v-if="topZeroQueries?.length" class="mb-4">
                <p class="text-xs text-gray-400 mb-1">{{ ctrans("Recent searches without results, click to use:") }}</p>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="zero in topZeroQueries"
                        :key="zero.query"
                        type="button"
                        class="text-xs px-2 py-0.5 rounded-full bg-red-50 text-red-700 hover:bg-red-100"
                        @click="prefillSynonym(zero.query)"
                    >
                        {{ zero.query }}
                    </button>
                </div>
            </div>

            <div v-if="suggestions.length" class="mb-4">
                <p class="text-xs text-gray-400 mb-1">
                    <FontAwesomeIcon icon="fal fa-sparkles" aria-hidden="true" />
                    {{ ctrans("AI suggestions from failed searches, approve or dismiss:") }}
                </p>
                <div class="divide-y divide-gray-100 rounded-md border border-gray-200">
                    <div
                        v-for="suggestion in suggestions"
                        :key="suggestion.id"
                        class="flex items-center justify-between gap-2 py-1.5 px-2 text-sm"
                    >
                        <span class="truncate">
                            {{ suggestion.words.join(" · ") }}
                            <span class="text-xs text-gray-400">({{ suggestion.sessions }} {{ ctrans("searches") }})</span>
                        </span>
                        <span class="flex gap-1 shrink-0">
                            <button
                                type="button"
                                class="px-2 py-0.5 rounded text-green-700 bg-green-50 hover:bg-green-100 disabled:opacity-40"
                                :disabled="decidingSuggestionId === suggestion.id"
                                :aria-label="ctrans('Approve')"
                                @click="decideSuggestion(suggestion, 'approve')"
                            >
                                <FontAwesomeIcon icon="fal fa-check" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                class="px-2 py-0.5 rounded text-gray-500 bg-gray-50 hover:bg-gray-100 disabled:opacity-40"
                                :disabled="decidingSuggestionId === suggestion.id"
                                :aria-label="ctrans('Dismiss')"
                                @click="decideSuggestion(suggestion, 'dismiss')"
                            >
                                <FontAwesomeIcon icon="fal fa-times" aria-hidden="true" />
                            </button>
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 mb-1">
                <input
                    v-model="synonymWords"
                    type="text"
                    :placeholder="ctrans('aromcandles, aroma candles')"
                    class="flex-1 rounded-md border-gray-300 text-sm"
                    @keyup.enter="saveSynonym"
                />
                <button
                    type="button"
                    class="px-4 py-2 rounded-md text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="isSavingSynonym"
                    @click="saveSynonym"
                >
                    {{ ctrans("Add") }}
                </button>
            </div>
            <p v-if="synonymError" class="text-xs text-red-600 mb-2">{{ synonymError }}</p>
            <p v-else class="text-xs text-gray-400 mb-2">{{ ctrans("Comma-separated, 2 to 10 words per group") }}</p>

            <div class="max-h-64 overflow-y-auto divide-y divide-gray-100 mt-2">
                <div v-if="isLoadingSynonyms" class="py-3 text-sm text-gray-400">{{ ctrans("Loading...") }}</div>
                <p v-else-if="!synonyms.length" class="py-3 text-sm text-gray-400">{{ ctrans("No synonyms yet") }}</p>
                <template v-else>
                    <div
                        v-for="entry in synonyms"
                        :key="entry.id"
                        class="flex items-center justify-between gap-2 py-2 px-1 text-sm"
                    >
                        <span class="truncate">{{ entry.synonyms.join(" · ") }}</span>
                        <button
                            type="button"
                            class="text-gray-300 hover:text-red-500 shrink-0"
                            :aria-label="ctrans('Delete')"
                            @click="deleteSynonym(entry)"
                        >
                            <FontAwesomeIcon icon="fal fa-times" aria-hidden="true" />
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </Modal>
    </div>
</template>
