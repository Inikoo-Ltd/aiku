<script setup lang="ts">
import { computed, inject, onBeforeUnmount, onMounted, ref, watch } from "vue"
import axios from "axios"
import Tabs from "primevue/tabs"
import TabList from "primevue/tablist"
import Tab from "primevue/tab"
import TabPanels from "primevue/tabpanels"
import TabPanel from "primevue/tabpanel"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { ctrans } from "@/Composables/useTrans"
import Image from "@common/Components/Image.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import { ProductResource } from "@/types/Iris/Products"

interface KeepExploringFamily {
    id: number
    name: string
    url: string
    image: any
}

interface KeepExploringGroup {
    key: "best_sellers" | "new_arrivals" | "forgotten"
    similar_shops?: boolean
    items: ProductResource[]
}

interface KeepExploring {
    did_you_mean: string | null
    searches: { kind: "related" | "popular", items: string[] }
    families: KeepExploringFamily[]
    products: KeepExploringGroup[]
}

const props = defineProps<{
    query: string
    familyIds: number[]
    isResultsLoading: boolean
    hasNoResults: boolean
    searchLogUlid: string | null
}>()

const emit = defineEmits<{
    (e: "suggestionClick", url: string, suggestion: string, target: "link" | "button"): void
    (e: "suggestionsViewed", ulid: string): void
}>()

defineSlots<{
    product(props: { product: ProductResource }): any
}>()

const layout: any = inject("layout", retinaLayoutStructure)

const isMounted = ref(false)
const isLoading = ref(false)
const suggestions = ref<KeepExploring | null>(null)
const searchHadNoResults = ref(false)
let fetchedQuery: string | null = null
let abortController: AbortController | null = null
let unseenUlid: string | null = null
let viewObserver: IntersectionObserver | null = null
const sectionElement = ref<HTMLElement | null>(null)

const isEnabled = computed(() => isMounted.value && !!layout?.iris?.search_keep_exploring)

const fetchSuggestions = async () => {
    const query = props.query.trim()
    if (!isEnabled.value || props.isResultsLoading || !query || fetchedQuery === query) {
        return
    }
    fetchedQuery = query
    const ulid = props.searchLogUlid
    searchHadNoResults.value = props.hasNoResults
    abortController?.abort()
    abortController = new AbortController()
    const signal = abortController.signal
    suggestions.value = null
    isLoading.value = true
    try {
        const { data } = await axios.get(route("iris.json.search.keep_exploring"), {
            params: { q: query, families: props.familyIds.length ? props.familyIds : undefined, search_log: ulid || undefined },
            signal,
        })
        if (!signal.aborted) {
            unseenUlid = ulid
            suggestions.value = data
        }
    } catch {
        if (!signal.aborted) {
            suggestions.value = null
        }
    } finally {
        if (!signal.aborted) {
            isLoading.value = false
        }
    }
}

const stopWatchingView = () => {
    viewObserver?.disconnect()
    viewObserver = null
}

watch(sectionElement, (element) => {
    stopWatchingView()
    if (!element || !unseenUlid || typeof IntersectionObserver === "undefined") {
        return
    }
    viewObserver = new IntersectionObserver((entries) => {
        if (!unseenUlid || !entries.some((entry) => entry.isIntersecting)) {
            return
        }
        emit("suggestionsViewed", unseenUlid)
        unseenUlid = null
        stopWatchingView()
    })
    viewObserver.observe(element)
})

watch(() => props.query, () => {
    stopWatchingView()
    unseenUlid = null
    abortController?.abort()
    fetchedQuery = null
    suggestions.value = null
    isLoading.value = false
})

watch(() => [props.query, props.isResultsLoading, isEnabled.value], fetchSuggestions)

onMounted(() => {
    isMounted.value = true
})

onBeforeUnmount(stopWatchingView)

const searchUrl = (term: string) => `/search?q=${encodeURIComponent(term)}`

const groupTitle = (group: KeepExploringGroup) => {
    if (group.key === "best_sellers") {
        return group.similar_shops ? ctrans("Best sellers for shops like yours") : ctrans("Best sellers")
    }
    return group.key === "new_arrivals" ? ctrans("New arrivals") : ctrans("Your forgotten favourites")
}

const onSearchClick = (term: string, suggestion: string) => emit("suggestionClick", searchUrl(term), suggestion, "link")

const groups = computed(() => (suggestions.value?.products ?? [])
    .filter((group) => group.items?.length)
    .map((group) => ({ ...group, title: groupTitle(group) })))

const didYouMean = computed(() => searchHadNoResults.value ? suggestions.value?.did_you_mean ?? null : null)
const searchItems = computed(() => (suggestions.value?.searches?.items ?? []).filter((term) => term !== didYouMean.value))
const searchKind = computed(() => suggestions.value?.searches?.kind === "popular" ? "popular_search" : "related_search")
const families = computed(() => suggestions.value?.families ?? [])
const hasContent = computed(() => !!(didYouMean.value || searchItems.value.length || families.value.length || groups.value.length))

const onProductClick = (event: MouseEvent, product: ProductResource, suggestion: string) => {
    const target = event.target as HTMLElement | null
    const clickTarget = target?.closest("button") ? "button" : target?.closest("a") ? "link" : null
    if (!clickTarget || !product.url) {
        return
    }
    emit("suggestionClick", product.url, suggestion, clickTarget)
}
</script>

<template>
    <div v-if="isEnabled && isLoading" class="keep-exploring skeleton keep-exploring-skeleton rounded" />
    <section v-else-if="isEnabled && hasContent" ref="sectionElement" class="keep-exploring">
        <p v-if="didYouMean" class="keep-exploring-did-you-mean">
            {{ ctrans("Did you mean") }}
            <LinkIris :href="searchUrl(didYouMean)" class="font-bold underline text-[var(--theme-color-0)]"
                @click="() => onSearchClick(didYouMean as string, 'did_you_mean')">{{ didYouMean }}</LinkIris>?
        </p>

        <template v-if="searchItems.length || families.length || groups.length">
            <h2 class="keep-exploring-heading">{{ ctrans("Keep exploring") }}</h2>

            <div v-if="searchItems.length" class="keep-exploring-part">
                <p class="keep-exploring-label">
                    {{ searchKind === "popular_search" ? ctrans("Popular searches") : ctrans("Related searches") }}
                </p>
                <div class="keep-exploring-chips">
                    <LinkIris v-for="term in searchItems" :key="term" :href="searchUrl(term)" class="keep-exploring-chip"
                        @click="() => onSearchClick(term, searchKind)">{{ term }}</LinkIris>
                </div>
            </div>

            <div v-if="families.length" class="keep-exploring-part">
                <p class="keep-exploring-label">{{ ctrans("Sells well with") }}</p>
                <div class="keep-exploring-families">
                    <LinkIris v-for="family in families" :key="family.id" :href="family.url" class="keep-exploring-family"
                        @click="() => emit('suggestionClick', family.url, 'family', 'link')">
                        <div class="keep-exploring-family-image">
                            <Image v-if="family.image" :src="family.image" class="absolute inset-0 h-full w-full object-cover" />
                        </div>
                        <div class="keep-exploring-family-name">
                            <span class="line-clamp-2">{{ family.name }}</span>
                        </div>
                    </LinkIris>
                </div>
            </div>

            <div v-if="groups.length" class="keep-exploring-part">
                <Tabs :value="groups[0].key">
                    <TabList v-if="groups.length > 1">
                        <Tab v-for="group in groups" :key="group.key" :value="group.key">{{ group.title }}</Tab>
                    </TabList>
                    <p v-else class="keep-exploring-label">{{ groups[0].title }}</p>
                    <TabPanels>
                        <TabPanel v-for="group in groups" :key="group.key" :value="group.key">
                            <div class="keep-exploring-grid">
                                <div v-for="product in group.items" :key="product.id"
                                    @click.capture="(event) => onProductClick(event, product, group.key)">
                                    <slot name="product" :product="product" />
                                </div>
                            </div>
                        </TabPanel>
                    </TabPanels>
                </Tabs>
            </div>
        </template>
    </section>
</template>

<style scoped>
.keep-exploring {
    margin-top: 32px;
}

.keep-exploring-skeleton {
    height: 160px;
}

.keep-exploring-did-you-mean {
    margin-bottom: 20px;
    font-size: 16px;
}

.keep-exploring-heading {
    margin-bottom: 16px;
    font-size: 22px;
    font-weight: 700;
}

.keep-exploring-part {
    margin-bottom: 24px;
}

.keep-exploring-label {
    margin-bottom: 10px;
    font-size: 16px;
    font-weight: 700;
    color: var(--theme-color-0);
}

.keep-exploring-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.keep-exploring-chip {
    min-height: 34px;
    display: inline-flex;
    align-items: center;
    padding: 0 14px;
    border-radius: 999px;
    background: #F4F1EE;
    font-size: 13px;
    font-weight: 600;
}

.keep-exploring-chip:hover {
    background: color-mix(in srgb, var(--iris-color-0) 15%, #F4F1EE);
}

.keep-exploring-families {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding-bottom: 8px;
}

.keep-exploring-family {
    display: block;
    flex: 0 0 120px;
    width: 120px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
}

.keep-exploring-family-image {
    position: relative;
    width: 100%;
    aspect-ratio: 1 / 1;
    background: #f3f4f6;
}

.keep-exploring-family-name {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 44px;
    padding: 0 8px;
    background: #f3f4f6;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.3;
    text-align: center;
}

.keep-exploring-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 40px 24px;
    padding: 16px;
    border-radius: 6px;
    background: #f3f4f6;
}

@media (min-width: 1024px) {
    .keep-exploring-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
</style>
