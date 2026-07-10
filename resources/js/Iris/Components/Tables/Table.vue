<!--suppress JSUnresolvedReference, JSIncompatibleTypesComparison -->
<script setup lang="ts">
import Pagination from './Pagination.vue'
import HeaderCell from './HeaderCell.vue'
import TableFilterSearch from './TableFilterSearch.vue'
import TableWrapper from './TableWrapper.vue'
import { router, usePage } from "@inertiajs/vue3"
import { trans } from 'laravel-vue-i18n'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { computed, onMounted, onUnmounted, ref, watch, inject } from 'vue'
import qs from 'qs'
import clone from 'lodash-es/clone'
import findKey from 'lodash-es/findKey'
import forEach from 'lodash-es/forEach'
import debounce from 'lodash-es/debounce'

const locale = inject('locale', aikuLocaleStructure)

const props = defineProps({
    name: {
        type: String,
        default: 'default',
        required: false,
    },

    preventOverlappingRequests: {
        type: Boolean,
        default: true,
        required: false,
    },

    // The main source of data
    resource: {
        type: Object,
        default: () => {
            return {};
        },
        required: false,
    },
});

const updates = ref(0);

const queryBuilderProps = computed(() => {
    let data = usePage().props.queryBuilderProps
        ? usePage().props.queryBuilderProps[props.name] || {}
        : {};

    data._updates = updates.value;
    return data;
});


const queryBuilderData = ref({...queryBuilderProps.value});  // spread operator to avoid sort on mounted


const pageName = computed(() => {
    return queryBuilderProps.value.pageName;
});

const tableFieldset = ref(null);

// Data of list rows table
const compResourceData = computed(() => {
    if ('data' in props.resource) {
        return props.resource.data;
    }

    return props.resource;
})

// Meta Page (Previous/next link, current page, data per page)
const compResourceMeta = computed(() => {
    if ('links' in props.resource && 'meta' in props.resource) {
        if (
            Object.keys(props.resource.links).length === 4 &&
            'next' in props.resource.links &&
            'prev' in props.resource.links
        ) {
            return {
                ...props.resource.meta,
                next_page_url: props.resource.links.next,
                prev_page_url: props.resource.links.prev,
            };
        }
    }

    if ('meta' in props.resource) {
        return props.resource.meta;
    }

    return props.resource;
});

const hasData = computed(() => {
    if (compResourceData.value.length > 0) {
        return true;
    }

    return compResourceMeta.value.total > 0;
});


function resetQuery() {
    forEach(queryBuilderData.value.searchInputs, (searchInput, key) => {
        queryBuilderData.value.searchInputs[key].value = null;
    });

    queryBuilderData.value.sort = null;
    queryBuilderData.value.cursor = null;
    queryBuilderData.value.page = 1;
}


function changeSearchInputValue(key, value) {
    if (visitCancelToken.value && props.preventOverlappingRequests) {
        visitCancelToken.value.cancel();
    }

    const intKey = findDataKey('searchInputs', key);
    queryBuilderData.value.searchInputs[intKey].value = value;
    queryBuilderData.value.cursor = null;
    queryBuilderData.value.page = 1;
}

const changeGlobalSearchValue = debounce((value?: string) => {
    changeSearchInputValue('global', value);
}, 100)

const immediateSearch = (value: string) => {
    changeGlobalSearchValue.cancel()
    changeSearchInputValue('global', value)
    debouncedFilter.cancel()
    visit(location.pathname + '?' + generateNewQueryString())
}

const cancelVisitIfInProgress = () => {
    if (visitCancelToken.value && isVisiting.value) {
        visitCancelToken.value.cancel()
    }
}

function onPerPageChange(value) {
    queryBuilderData.value.cursor = null
    queryBuilderData.value.perPage = value
    queryBuilderData.value.page = 1
}

function findDataKey(dataKey, key) {
    return findKey(queryBuilderProps.value[dataKey], (value) => {
        return value.key === key;
    });
}


function getFilterForQuery() {
    let filtersWithValue = {};

    forEach(queryBuilderData.value.searchInputs, (searchInput) => {
        if (searchInput.value !== null) {
            filtersWithValue[searchInput.key] = searchInput.value;
        }
    });

    return filtersWithValue;
}

// To generate query in url (?filter[global]=abc&sort=slug)
function dataForNewQueryString() {
    const filterForQuery = getFilterForQuery();
    const queryData = {};

    if (Object.keys(filterForQuery).length > 0) {
        queryData.filter = filterForQuery;
    }

    const cursor = queryBuilderData.value.cursor
    const page = queryBuilderData.value.page
    const sort = queryBuilderData.value.sort
    const perPage = queryBuilderData.value.perPage;

    if (cursor) {
        queryData.cursor = cursor;
    }

    if (page > 1) {
        queryData.page = page;
    }

    if (perPage > 1) {
        queryData.perPage = perPage;
    }

    if (sort) {
        queryData.sort = sort;
    }

    return queryData;
}

function generateNewQueryString() {
    // Get data from URL
    const queryStringData = qs.parse(location.search.substring(1))
    const prefix = props.name === 'default' ? '' : props.name + '_'

    // To exclude 'filter', 'columns', 'cursor', and 'sort' that received from the URL
    forEach(['filter', 'columns', 'cursor', 'sort'], (key) => {
        delete queryStringData[prefix + key];
    });

    // To exclude page number from pagination
    delete queryStringData[pageName.value];

    forEach(dataForNewQueryString(), (value, key) => {
        if (key === 'page') {
            queryStringData[pageName.value] = value;
        } else {
            queryStringData[prefix + key] = value;
        }
    });
    let query = qs.stringify(queryStringData, {
        encodeValuesOnly: true,
        skipNulls: true,
        strictNullHandling: true,
        arrayFormat: 'comma',
    });

    if (!query || query === pageName.value + '=1') {
        query = '';
    }

    return query;
}

const isVisiting = ref(false);
const visitCancelToken = ref<{ cancel: Function } | null>(null);

const visit = (url?: string) => {
    // Visit new generate URL, run on watch queryBuilderData

    if (!url) {
        return;
    }

    router.get(
        url,
        {},
        {
            replace: true,
            preserveState: true,
            preserveScroll: true,
            onBefore() {
                isVisiting.value = true;
            },
            onCancelToken(cancelToken) {
                visitCancelToken.value = cancelToken;
            },
            onFinish() {
                isVisiting.value = false;
            },
            onSuccess() {
                if ('queryBuilderProps' in usePage().props) {
                    queryBuilderData.value.cursor = queryBuilderProps.value.cursor;
                    queryBuilderData.value.page = queryBuilderProps.value.page;
                }

                updates.value++;
            },
        },
    );
}

const debouncedFilter = debounce(() => {
    try {
        visit(location.pathname + '?' + generateNewQueryString())
    } catch {
        console.error("Can't visit expected path")
    }
}, 600, {
    leading: false,
    trailing: true,
});

let isMounted = false;

watch(queryBuilderData, async () => {
        if (!isMounted) return;
        debouncedFilter();
    },
    {deep: true},
);

const inertiaListener = () => {
    updates.value++;
};

onMounted(() => {
    isMounted = true;
    document.addEventListener('inertia:success', inertiaListener);
});

onUnmounted(() => {
    document.removeEventListener('inertia:success', inertiaListener);
});


function sortBy(column) {
    if (queryBuilderData.value.sort === `-${column}`) {
        queryBuilderData.value.sort = column;
    } else {
        queryBuilderData.value.sort = `-${column}`
    }

    queryBuilderData.value.cursor = null;
    queryBuilderData.value.page = 1;
}

function show(key) {
    const intKey = findDataKey('columns', key);

    return !queryBuilderData?.value?.columns?.[intKey]?.hidden;
}

function header(key) {
    const intKey = findDataKey('columns', key);
    const columnData = clone(queryBuilderProps.value.columns[intKey]);

    if (columnData) {
        columnData.onSort = sortBy;
    }

    return columnData;
}

watch(() => props.name, () => {
    // To reset the 'sort' on change Tabs
    queryBuilderData.value.sort = null
    resetQuery()
})
</script>

<template>
    <!--suppress HtmlUnknownAttribute -->
    <fieldset ref="tableFieldset" :key="`table-${name}`" :dusk="`table-${name}`" class="min-w-0 iris-data-table"
        :class="{ 'opacity-75': isVisiting }">
        <div class="py-2 sm:py-0 my-0">
            <div class="grid grid-flow-col justify-between items-center flex-nowrap table-query-builder">
                <!-- Left Section: Records, Search -->
                <div class="h-fit flex flex-wrap gap-y-0.5 gap-x-1 items-center my-0.5">
                    <!-- Result Number -->
                    <div class="bg-gray-100 h-fit flex items-center border border-gray-300 overflow-hidden rounded">
                        <div class="grid justify-end items-center text-base font-normal text-gray-700">
                            <div class="px-2 py-[1px] whitespace-nowrap flex gap-x-1.5 flex-nowrap">
                                <span class="font-semibold tabular-nums">
                                    {{ locale.number(compResourceMeta?.total || 0) }}
                                </span>

                                <span class="font-light">
                                    {{
                                    compResourceMeta.total > 1
                                    ? queryBuilderProps.labelRecord?.[1] || queryBuilderProps.labelRecord?.[0] ||
                                    trans('records')
                                    : queryBuilderProps.labelRecord?.[0] || trans('record')
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Search Input Button -->
                    <div v-if="queryBuilderProps.globalSearch" class="flex flex-row">
                        <TableFilterSearch
                            @resetSearch="() => resetQuery()" :label="queryBuilderProps.globalSearch.label"
                            :value="queryBuilderProps.globalSearch.value" :on-change="changeGlobalSearchValue"
                            :on-enter="immediateSearch" :on-start-typing="cancelVisitIfInProgress" :isVisiting />
                    </div>
                </div>
            </div>
        </div>

        <!-- The Main Table -->
        <TableWrapper :result="compResourceMeta.total === 0" class="mt-0">
            <table class="divide-y divide-gray-200 bg-white w-full">
                <thead class="bg-gray-50">
                    <tr class="border-t border-gray-200 divide-x divide-gray-200">
                        <HeaderCell v-for="column in queryBuilderProps.columns"
                            :key="`table-${name}-header-${column.key}`"
                            :cell="header(column.key)" :column="column"
                            :resource="compResourceData">
                        </HeaderCell>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="(item, key) in compResourceData"
                        :key="`table-${name}-row-${key}-${item.id}-${item.slug}`"
                        class="hover:bg-gray-50">
                        <td v-for="(column, index) in queryBuilderProps.columns"
                            v-show="show(column.key)"
                            :key="`table-${name}-row-${key}-column-${column.key}`"
                            class="text-sm py-2 text-gray-600 whitespace-normal h-full" :class="[
                                typeof item[column.key] == 'number' || column.type === 'number' || column.align === 'right'
                                    ? 'text-right pl-3 pr-9 tabular-nums'
                                    : column.align === 'center'
                                        ? 'text-center px-3'
                                        : 'px-6'
                            ]">
                            <slot :name="`cell(${column.key})`"
                                :item="{ ...item, index: index, rowIndex : key, data : item }"
                                :proxyItem="item" :tabName="name">
                                <template
                                    v-if="typeof item[column.key] == 'number' || column.type === 'number'">
                                    {{ locale.number(item[column.key]) }}
                                </template>
                                <template v-else>
                                    {{ item[column.key] }}
                                </template>
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <Pagination :on-click="visit" :has-data="hasData" :meta="compResourceMeta"
                :per-page-options="queryBuilderProps.perPageOptions"
                :on-per-page-change="onPerPageChange" />
        </TableWrapper>
    </fieldset>
</template>

<!--suppress HtmlUnknownAttribute -->
<style scope>
fieldset {
    margin-top: 0 !important;
}

.iris-data-table table,
.editor-class .iris-data-table table {
    border: 0;
    margin: 0;
}

.iris-data-table table th,
.iris-data-table table td,
.editor-class .iris-data-table table th,
.editor-class .iris-data-table table td {
    border: 0;
    background-color: transparent;
    font-weight: inherit;
}

.iris-data-table thead,
.editor-class .iris-data-table thead {
    background-color: rgb(249 250 251);
}
</style>
