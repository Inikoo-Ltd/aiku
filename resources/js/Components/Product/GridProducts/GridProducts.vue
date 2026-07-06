<script setup lang="ts">
/**
 * GridProducts Component
 *
 * A reusable product grid component with search, pagination, and favorite functionality.
 * Works with Laravel's Inertia.js query builder for server-side filtering and pagination.
 */

// ============================================================================
// IMPORTS
// ============================================================================
import { ref, computed, watch } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import { debounce, forEach, findKey } from "lodash-es"
import qs from "qs"
import { trans } from "laravel-vue-i18n"
import { Popover, PopoverButton, PopoverPanel } from "@headlessui/vue"
import TableElements from "@/Components/Table/TableElements.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

// Table Components
import Pagination from "@/Components/Table/Pagination.vue"
import TableFilterSearch from "@/Components/Table/TableFilterSearch.vue"
import TableWrapper from "@/Components/Table/TableWrapper.vue"
import Image from "@common/Components/Image.vue"

// Product Components
import RecordCounter from "./RecordCounter.vue"
import EmptyState from "./EmptyState.vue"
import ProductRenderEcom from "@/Iris/Components/IrisBlocks/Products/Ecom/ProductCard/ProductCardEcom1.vue"

// Types
import type { Product, QueryBuilderData } from "./types"
import ListItem from "@tiptap/extension-list-item"

// ============================================================================
// PROPS
// ============================================================================
const props = defineProps({
	name: {
		type: String,
		default: "default",
		required: false,
	},
	resource: {
		type: Object,
		default: () => ({}),
		required: false,
	},
	meta: {
		type: Object,
		default: () => ({}),
		required: false,
	},
	data: {
		type: Object,
		default: () => ({}),
		required: false,
	},
	preserveScroll: {
		type: [Boolean, String],
		default: false,
		required: false,
	},
	preventOverlappingRequests: {
		type: Boolean,
		default: true,
		required: false,
	},
	inputDebounceMs: {
		type: Number,
		default: 350,
		required: false,
	},
	isParentLoading: {
		type: Boolean,
		default: false,
		required: false,
	},
	gridClass: {
		type: String,
		default: "lg:grid-cols-3 xl:grid-cols-4 grid grid-cols-2",
		required: false,
	},
	columnsType: {
		type: Object,
		default: () => {
			return {}
		},
		required: false,
	},
	basketTransactions: {
		type: Object,
		default: () => ({}),
		required: false,
	},
	showHeader: {
		type: Boolean,
		default: true,
		required: false,
	},
	label: {
		type: String,
		default: true,
		required: false,
	},
})

// console.log(props);

// ============================================================================
// STATE MANAGEMENT
// ============================================================================
const updates = ref(0)
const isVisiting = ref(false)
const visitCancelToken = ref<{ cancel: Function } | null>(null)
const isLoading = ref(false)

// ============================================================================
// QUERY BUILDER SETUP
// ============================================================================
const queryBuilderProps = computed(() => {
	const data = usePage().props.queryBuilderProps?.[props.name] || {}
	data._updates = updates.value
	return data
})

const queryBuilderData = ref<QueryBuilderData>({ ...queryBuilderProps.value })

// Initialize search inputs if not present
if (!queryBuilderData.value.searchInputs) {
	queryBuilderData.value.searchInputs = []
}

// Ensure global search input exists
const globalSearchInput = queryBuilderData.value.searchInputs.find(
	(input) => input?.key === "global"
)
if (!globalSearchInput) {
	queryBuilderData.value.searchInputs.push({
		key: "global",
		value: null,
	})
}

// ============================================================================
// COMPUTED PROPERTIES
// ============================================================================
const pageName = computed(() => queryBuilderProps.value.pageName)

/**
 * Extract and normalize resource data from various prop formats
 */
const compResourceData = computed<Product[]>(() => {
	if (props.resource?.data?.length > 0) {
		return props.resource.data
	}

	if (props.data?.length > 0) {
		return props.data
	}

	if (Object.keys(props.resource || {}).length > 0 && !("data" in props.resource)) {
		return props.resource
	}

	return []
})

/**
 * Extract and normalize metadata for pagination
 */
const compResourceMeta = computed(() => {
	if (props.meta && Object.keys(props.meta).length > 0) {
		return props.meta
	}

	if ("links" in props.resource && "meta" in props.resource) {
		if (
			Object.keys(props.resource.links).length === 4 &&
			"next" in props.resource.links &&
			"prev" in props.resource.links
		) {
			return {
				...props.resource.meta,
				next_page_url: props.resource.links.next,
				prev_page_url: props.resource.links.prev,
			}
		}
	}

	if ("meta" in props.resource) {
		return props.resource.meta
	}

	return props.resource || {}
})

/**
 * Check if there's data to display
 */
const hasData = computed(() => {
	return compResourceData.value.length > 0 || compResourceMeta.value.total > 0
})

/**
 * Get existing transaction for a product
 */
/* const getExistingTransaction = (product: Product) => {
    if (!props.basketTransactions || !product.id) {
        return null
    }
    return props.basketTransactions[product.id] || null
} */
console.log("basketTransactions", props.basketTransactions)

// ============================================================================
// SEARCH & FILTER FUNCTIONS
// ============================================================================

/**
 * Get the current value of a search input
 */
function getSearchInputValue(key: string): any {
	const intKey = findDataKey("searchInputs", key)
	return intKey !== undefined ? queryBuilderData.value.searchInputs![intKey].value : null
}

/**
 * Update a search input value and trigger a new search
 */
function changeSearchInputValue(key: string, value: any): void {
	if (visitCancelToken.value && props.preventOverlappingRequests) {
		visitCancelToken.value.cancel()
	}

	const intKey = findDataKey("searchInputs", key)
	if (intKey !== undefined && queryBuilderData.value.searchInputs) {
		queryBuilderData.value.searchInputs[intKey].value = value
		queryBuilderData.value.cursor = null
		queryBuilderData.value.page = 1
	}
}

/**
 * Debounced global search handler
 */
const changeGlobalSearchValue = debounce((value?: string) => {
	changeSearchInputValue("global", value)
}, props.inputDebounceMs)

/**
 * Find the index of a data item by key
 */
function findDataKey(dataKey: string, key: string): number | undefined {
	return findKey(queryBuilderData.value[dataKey as keyof QueryBuilderData], (value: any) => {
		return value.key === key
	})
}

/**
 * Reset all filters and search inputs
 */
function resetQuery(): void {
	if (queryBuilderData.value.filters) {
		forEach(queryBuilderData.value.filters, (filter, key) => {
			queryBuilderData.value.filters![key].value = null
		})
	}

	if (queryBuilderData.value.searchInputs) {
		forEach(queryBuilderData.value.searchInputs, (filter, key) => {
			queryBuilderData.value.searchInputs![key].value = null
		})
	}

	queryBuilderData.value.sort = null
	queryBuilderData.value.cursor = null
	queryBuilderData.value.page = 1
}

// ============================================================================
// PAGINATION
// ============================================================================

/**
 * Handle per-page change
 */
function onPerPageChange(value: number): void {
	queryBuilderData.value.cursor = null
	queryBuilderData.value.perPage = value
	queryBuilderData.value.page = 1
}

// ============================================================================
// URL QUERY STRING GENERATION
// ============================================================================

/**
 * Get all active filters for the query string
 */
function getFilterForQuery(): Record<string, any> {
	const filtersWithValue: Record<string, any> = {}

	if (queryBuilderData.value.searchInputs) {
		forEach(queryBuilderData.value.searchInputs, (searchInput) => {
			if (searchInput.value !== null) {
				filtersWithValue[searchInput.key] = searchInput.value
			}
		})
	}

	if (queryBuilderData.value.filters) {
		forEach(queryBuilderData.value.filters, (filter) => {
			if (filter.value !== null) {
				filtersWithValue[filter.key] = filter.value
			}
		})
	}

	return filtersWithValue
}

/**
 * Build data object for the new query string
 */
function dataForNewQueryString(): Record<string, any> {
	const filterForQuery = getFilterForQuery()
	const queryData: Record<string, any> = {}

	if (Object.keys(filterForQuery).length > 0) {
		queryData.filter = filterForQuery
	}

	const { cursor, page, sort, perPage, elementFilter } = queryBuilderData.value

	if (cursor) queryData.cursor = cursor
	if (page && page > 1) queryData.page = page
	if (perPage && perPage > 1) queryData.perPage = perPage
	if (sort) queryData.sort = sort
	if (elementFilter) queryData.elements = elementFilter

	return queryData
}

/**
 * Generate the complete query string for the URL
 */
function generateNewQueryString(): string {
	const queryStringData = qs.parse(location.search.substring(1))
	const prefix = props.name === "default" ? "" : props.name + "_"

	// Remove old query parameters
	forEach(["filter", "columns", "cursor", "sort", "elements"], (key) => {
		delete queryStringData[prefix + key]
	})
	delete queryStringData[pageName.value]

	// Add new query parameters
	forEach(dataForNewQueryString(), (value, key) => {
		if (key === "page") {
			queryStringData[pageName.value] = value
		} else {
			queryStringData[prefix + key] = value
		}
	})

	const query = qs.stringify(queryStringData, {
		encodeValuesOnly: true,
		skipNulls: true,
		strictNullHandling: true,
		arrayFormat: "comma",
	})

	return !query || query === pageName.value + "=1" ? "" : query
}

function onSortChange(value: string): void {
	queryBuilderData.value.sort = value || null
	queryBuilderData.value.cursor = null
	queryBuilderData.value.page = 1
}

// ============================================================================
// NAVIGATION
// ============================================================================

/**
 * Navigate to a new URL with Inertia
 */
const visit = (url?: string): void => {
	if (!url) return

	router.get(
		url,
		{},
		{
			replace: true,
			preserveState: true,
			preserveScroll: props.preserveScroll !== false,
			onBefore() {
				isVisiting.value = true
			},
			onCancelToken(cancelToken) {
				visitCancelToken.value = cancelToken
			},
			onFinish() {
				isVisiting.value = false
			},
			onSuccess() {
				if ("queryBuilderProps" in usePage().props) {
					// Only update cursor and page like Table.vue does
					queryBuilderData.value.cursor = queryBuilderProps.value.cursor
					queryBuilderData.value.page = queryBuilderProps.value.page
				}
				updates.value++
			},
		}
	)
}

// ============================================================================
// WATCHERS
// ============================================================================

/**
 * Watch for query changes and trigger navigation
 */
watch(
	queryBuilderData,
	async () => {
		try {
			visit(location.pathname + "?" + generateNewQueryString())
		} catch (error) {
			console.error("Navigation error:", error)
		}
	},
	{ deep: true }
)
</script>

<template>
	<fieldset
		:key="`grid-${name}`"
		class="min-w-0"
		:class="{ 'opacity-75': isVisiting || isParentLoading }">
		<!-- Header Section -->
		<div class="py-2 sm:py-0 my-0">
			<div
				class="flex flex-wrap justify-between items-center gap-y-2 gap-x-2 px-3 sm:px-4">
				<!-- Left Section: Counter and Search -->
				<div class="h-fit flex flex-wrap gap-y-0.5 gap-x-1 items-center my-0.5">
					<!-- Record Counter -->
					<RecordCounter
						:total="compResourceMeta?.total || 0"
						:labelSingular="
							queryBuilderProps?.labelRecord?.[0] || props.label || trans('product')
						"
						:labelPlural="
							queryBuilderProps?.labelRecord?.[1] ||
							queryBuilderProps?.labelRecord?.[0] ||
							props.label ||
							trans('products')
						" />

					<!-- Search Input -->
					<div class="flex flex-row" v-if="queryBuilderProps.globalSearch">
						<TableFilterSearch
							@resetSearch="resetQuery"
							:label="trans('Search products...')"
							:value="getSearchInputValue('global')"
							:on-change="changeGlobalSearchValue"
							:isVisiting="isVisiting" />
					</div>
				</div>

				<div class="flex flex-wrap items-center gap-2 sm:gap-3">
					<div v-if="showHeader" class="flex items-center gap-2 min-w-0">
						<label
							:for="`grid-${name}-sort`"
							class="hidden sm:inline text-sm text-gray-500 whitespace-nowrap"
							>{{ trans("Sort by") }}</label
						>
						<select
							:id="`grid-${name}-sort`"
							:value="queryBuilderData.sort || ''"
							@change="onSortChange(($event.target as HTMLSelectElement).value)"
							class="min-w-0 max-w-[45vw] sm:max-w-none rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
							<option value="">{{ trans("Default") }}</option>
							<template
								v-for="column in queryBuilderProps.columns.filter(
									(item: any) => item.sortable
								)"
								:key="`sort-option-${column.key}`">
								<option :value="column.key">
									{{ column.label }} ({{ trans("ascending") }})
								</option>
								<option :value="`-${column.key}`">
									{{ column.label }} ({{ trans("descending") }})
								</option>
							</template>
						</select>
					</div>

					<!-- Filter: Checkbox element in dropdown -->
					<Popover
						v-if="Object.keys(queryBuilderProps?.elementGroups || [])?.length"
						class="relative">
						<PopoverButton as="template">
							<div>
								<Button
									:type="'tertiary'"
									:label="trans('Filter')"
									icon="fal fa-filter" />
							</div>
						</PopoverButton>

						<Transition
							enter-active-class="transition duration-100 ease-out"
							enter-from-class="transform scale-95 opacity-0"
							enter-to-class="transform scale-100 opacity-100"
							leave-active-class="transition duration-75 ease-in"
							leave-from-class="transform scale-100 opacity-100"
							leave-to-class="transform scale-95 opacity-0">
							<PopoverPanel
								style="width: max-content"
								class="z-30 absolute right-0 mt-2 min-w-80 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black/5 focus:outline-none p-3">
								<TableElements
									:elements="queryBuilderProps.elementGroups"
									@checkboxChanged="
										(data) => (queryBuilderData.elementFilter = data)
									"
									:inPopover="true"
									:tableName="props.name" />
							</PopoverPanel>
						</Transition>
					</Popover>
				</div>
			</div>
		</div>

		<!-- Products Grid -->
		<TableWrapper :result="compResourceMeta?.total === 0" class="mt-2">
			<div
				v-if="compResourceData.length > 0"
				class="gap-2 p-2 sm:gap-4 sm:p-4"
				:class="gridClass">
				<!-- Product Cards -->
				<div
					v-for="(item, index) in compResourceData"
					:key="`product-${index}`"
					class="h-full min-h-0">
					<slot name="card" :item="item">
						<ProductRenderEcom
							:product="item"
							:key="index"
							:hasInBasket="item"
							:detach-to-favourite-route="{
								name: 'retina.models.product.unfavourite',
							}"
							:attach-to-favourite-route="{ name: 'retina.models.product.favourite' }"
							:add-to-basket-route="{ name: 'retina.models.product.add-to-basket' }"
							:updateBasketQuantityRoute="{
								name: 'retina.models.transaction.update',
								method: 'patch',
							}"
							@after-on-unselect-favourite="() => router.reload()">
							<template #image="{ product }">
								<Image
									:src="product?.image?.source"
									alt="product image"
									:style="{ objectFit: 'contain' }" />
							</template>
						</ProductRenderEcom>
					</slot>
				</div>
			</div>

			<!-- Empty State -->
			<EmptyState
				v-else-if="!isVisiting"
				:message="
					getSearchInputValue('global') ? trans('No result') : trans('Empty') + ' ' + name
				"
				:description="
					getSearchInputValue('global')
						? trans('Try adjusting your search terms')
						: name + ' ' + trans('could not be found')
				" />

			<!-- Pagination -->
			<Pagination
				v-if="hasData"
				:on-click="visit"
				:has-data="hasData"
				:meta="compResourceMeta"
				:exportLinks="queryBuilderProps?.exportLinks"
				:per-page-options="queryBuilderProps?.perPageOptions"
				:on-per-page-change="onPerPageChange" 
				:max-pages="3"
			/>
		</TableWrapper>
	</fieldset>
</template>
