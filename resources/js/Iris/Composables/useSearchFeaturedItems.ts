import { computed, ref } from 'vue'
import axios from 'axios'
import { searchRoute } from '@/Iris/Composables/useSearchRoute'

// GetIrisSearchFeaturedItems.php: what the storefront shows while the search field is still
// empty. State is module level so the desktop bar and the mobile overlay, both mounted on the
// same page, share a single fetch that never repeats between keystrokes.
const featuredResults = ref<any>(null)
const isFeaturedLoading = ref(false)
let hasFetchedFeaturedItems = false

const hasFeaturedResults = computed(() =>
    Boolean(featuredResults.value?.products?.length
        || featuredResults.value?.product_categories?.length
        || featuredResults.value?.collections?.length)
)

let pendingFetch: Promise<void> | null = null

const fetchFeaturedResults = (): Promise<void> => {
    if (hasFetchedFeaturedItems) {
        return pendingFetch ?? Promise.resolve()
    }
    hasFetchedFeaturedItems = true
    isFeaturedLoading.value = true

    pendingFetch = axios.get(route(searchRoute('featured_items')))
        .then(({ data }) => {
            featuredResults.value = data.results ?? null
        })
        .catch(() => {
            hasFetchedFeaturedItems = false
            featuredResults.value = null
        })
        .finally(() => {
            isFeaturedLoading.value = false
        })

    return pendingFetch
}

export const useSearchFeaturedItems = () => ({
    featuredResults,
    isFeaturedLoading,
    hasFeaturedResults,
    fetchFeaturedResults,
})
