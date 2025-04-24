<!--suppress JSUnresolvedReference, JSIncompatibleTypesComparison -->
<script setup lang="ts">
import Pagination from '@/Components/Table/Pagination.vue'
import HeaderCell from '@/Components/Table/HeaderCell.vue'
import TableFilterSearch from '@/Components/Table/TableFilterSearch.vue'
import TableElements from '@/Components/Table/TableElements.vue'
import TablePeriodFilter from '@/Components/Table/TablePeriodFilter.vue'
import TableWrapper from '@/Components/Table/TableWrapper.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import { Link, router, usePage } from "@inertiajs/vue3";
import { trans } from 'laravel-vue-i18n'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

import { computed, getCurrentInstance, onMounted, onUnmounted, ref, Transition, watch, reactive, inject } from 'vue'
import qs from 'qs'
import clone from 'lodash-es/clone'
import filter from 'lodash-es/filter'
import findKey from 'lodash-es/findKey'
import forEach from 'lodash-es/forEach'
import isEqual from 'lodash-es/isEqual'
import map from 'lodash-es/map'
import { set as setLodash, debounce, kebabCase } from 'lodash-es'
import CountUp from 'vue-countup-v3'
import { useFormatTime } from '@/Composables/useFormatTime'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheckSquare, faCheck, faSquare} from '@fal'
import { faCheckSquare as fasCheckSquare, faWatchCalculator} from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import TableBetweenFilter from '@/Components/Table/TableBetweenFilter.vue'
import TableRadioFilter from './TableRadioFilter.vue'
import TableDateInterval from './TableDateInterval.vue'
library.add(faCheckSquare, faCheck, faSquare, fasCheckSquare, faWatchCalculator)

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', layoutStructure)

const props = defineProps(
    {
        changeElements: {
            type: Object,
        },
        inertia: {
            type: Object,
            default: () => {
                return {};
            },
            required: false,
        },

        name: {
            type: String,
            default: 'default',
            required: false,
        },

        // To align the label each row to the top (middle by default)
        rowAlignTop: {
            type: Boolean,
        },

        striped: {
            type: Boolean,
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

        preserveScroll: {
            type: [Boolean, String],
            default: false,
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
        meta: {
            type: Object,
            default: () => {
                return {};
            },
            required: false,
        },

        data: {
            type: Object,
            default: () => {
                return {};
            },
            required: false,
        },

        columnsType: {
            type: Object,
            default: () => {
                return {};
            },
            required: false,
        },
        modelOperations: {
            type: Array,
            default: () => {
                return [];
            },
            required: false,
        },
        emptyState: {
            type: Array,
            default: () => {
                return [];
            },
            required: false,
        },
        selectedRow: {
            type: Object
        },
        exportLinks: {
            type: Object
        },
        isCheckBox: {
            type: Boolean
        },
        checkboxKey : {
            type: String,
            default: 'id'
        },
        useForm : {
            type: Boolean,
            default: false,
            required: false,
        },
        useExpandTable : {
            type: Boolean,
            default: false,
            required: false,
        }
    });

const emits = defineEmits<{
    (e: 'onSelectRow', value: {[key: string]: boolean}): void
    (e: 'onCheked', value: {[key: string]: boolean}, checked : {[key: string]: boolean}): void
    (e: 'onChecked', value: {}): void
}>()

// const app = getCurrentInstance();
// const $inertia = app ? app.appContext.config.globalProperties.$inertia : props.inertia;
const updates = ref(0);

const queryBuilderProps = computed(() => {
    let data = usePage().props.queryBuilderProps
        ? usePage().props.queryBuilderProps[props.name] || {}
        : {};

    data._updates = updates.value;
    return data;
});


const queryBuilderData = ref({...queryBuilderProps.value});  // spread operator to avoid sort on mounted
// queryBuilderData.value.elementFilter = {
//     // 'state': ['left'],
//     // 'type': ['volunteer', 'employee']
// }
// queryBuilderData.value.periodFilter = {
//     // 'type': 'today',
//     // 'date': 202405
// }


const pageName = computed(() => {
    return queryBuilderProps.value.pageName;
});

const forcedVisibleSearchInputs = ref([]);

const tableFieldset = ref(null);

const hasOnlyData = computed(() => {
    if (queryBuilderProps.value.hasToggleableColumns) {
        return false;
    }

    if (queryBuilderProps.value.hasFilters) {
        return false;
    }

    if (queryBuilderProps.value.hasSearchInputs) {
        return false;
    }

    if (queryBuilderProps.value.elements) {
        return false;
    }

    return !queryBuilderProps.value.globalSearch;
});

// Data of list rows table
const compResourceData = computed(() => {
    if (Object.keys(props.resource || {}).length === 0) {
        return props.data;
    }

    if ('data' in props.resource) {
        return props.resource.data;
    }

    return props.resource;
})

// Meta Page (Previous/next link, current page, data per page)
const compResourceMeta = computed(() => {
    if (Object.keys(props.resource || {}).length === 0) {
        return props.meta;
    }

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

//

// function disableSearchInput(key) {
//     forcedVisibleSearchInputs.value = forcedVisibleSearchInputs.value.filter(
//         (search) => search !== key,
//     );

//     changeSearchInputValue(key, null);
// }

// function showSearchInput(key) {
//     forcedVisibleSearchInputs.value.push(key);
// }

// const canBeReset = computed(() => {
//     if (forcedVisibleSearchInputs.value.length > 0) {
//         return true;
//     }

//     const queryStringData = qs.parse(location.search.substring(1));

//     const page = queryStringData[pageName.value];

//     if (page > 1) {
//         return true;
//     }

//     const prefix = props.name === 'default' ? '' : props.name + '_';
//     let dirty = false;

//     forEach(['filter', 'columns', 'cursor', 'sort', 'elementGroups'], (key) => {
//         const value = queryStringData[prefix + key];

//         if (key === 'sort' && value === queryBuilderProps.value.defaultSort) {
//             return;
//         }

//         if (value !== undefined) {
//             dirty = true;
//         }
//     });

//     return dirty;
// });

function resetQuery() {
    forcedVisibleSearchInputs.value = [];

    forEach(queryBuilderData.value.filters, (filter, key) => {
        queryBuilderData.value.filters[key].value = null;
    });

    forEach(queryBuilderData.value.searchInputs, (filter, key) => {
        queryBuilderData.value.searchInputs[key].value = null;
    });

    forEach(queryBuilderData.value.elements, (filter, key) => {
        queryBuilderData.value.elements[key].value = null;
    });

    // forEach(queryBuilderData.value.columns, (column, key) => {
    //     queryBuilderData.value.columns[key].hidden = column.can_be_hidden
    //         ? !queryBuilderProps.value.defaultVisibleToggleableColumns.includes(column.key)
    //         : false;
    // });

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
}, 400)

// function changeFilterValue(key, value) {
//     const intKey = findDataKey('filters', key);

//     queryBuilderData.value.filters[intKey].value = value;
//     queryBuilderData.value.cursor = null;
//     queryBuilderData.value.page = 1;
// }

function onPerPageChange(value) {
    queryBuilderData.value.cursor = null
    queryBuilderData.value.perPage = value
    queryBuilderData.value.page = 1

    // axios.patch(
    //     route("grp.models.user.update", layout.user?.id),
    //     {
    //         settings: {
    //             records_per_page: value,
    //         },
    //     }
    // )
    // .then((response) => {
    //     console.log('success');
    // })
    // .catch((error) => {
    //     console.error('Error:', error);
    // });
}

function findDataKey(dataKey, key) {
    return findKey(queryBuilderData.value[dataKey], (value) => {
        return value.key === key;
    });
}

// function changeColumnStatus(key, visible) {
//     const intKey = findDataKey('columns', key);

//     queryBuilderData.value.columns[intKey].hidden = !visible;
// }

function getFilterForQuery() {
    let filtersWithValue = {};

    forEach(queryBuilderData.value.searchInputs, (searchInput) => {
        if (searchInput.value !== null) {
            filtersWithValue[searchInput.key] = searchInput.value;
        }
    });

    forEach(queryBuilderData.value.filters, (filters) => {
        if (filters.value !== null) {
            filtersWithValue[filters.key] = filters.value;
        }
    });

    return filtersWithValue;
}

function getColumnsForQuery() {
    const columns = queryBuilderData.value.columns;

    let visibleColumns = filter(columns, (column) => {
        return !column.hidden;
    });

    let visibleColumnKeys = map(visibleColumns, (column) => {
        return column.key;
    }).sort();

    if (isEqual(visibleColumnKeys, queryBuilderProps.value.defaultVisibleToggleableColumns)) {
        return {};
    }

    return visibleColumnKeys;
}

// To generate query in url (?period[type]=year&period[date]=2024&sort=slug)
function dataForNewQueryString() {
    const filterForQuery = getFilterForQuery();
    const columnsForQuery = getColumnsForQuery();
    const queryData = {};

    if (Object.keys(filterForQuery).length > 0) {
        queryData.filter = filterForQuery;
    }

    if (Object.keys(columnsForQuery).length > 0) {
        queryData.columns = columnsForQuery;
    }

    const cursor = queryBuilderData.value.cursor
    const page = queryBuilderData.value.page
    const sort = queryBuilderData.value.sort
    const perPage = queryBuilderData.value.perPage;
    const elementFilter = queryBuilderData.value.elementFilter
    const period = queryBuilderData.value.periodFilter
    const radioFilter = queryBuilderData.value.radioFilter
    const dateInterval = queryBuilderData.value.dateInterval


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
    if (elementFilter) {
        queryData.elements = elementFilter // elements[state] = working
    }
    if (period) {
        queryData.period = period // period[type]=year&period[date]=2024
    }
    if (radioFilter) {
        queryData.radioFilter = radioFilter // radioFilter=selected
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
            // } else if (key === 'perPage') {
            // This line make pagination error
            //     queryStringData.perPage[prefix + key] = value;
        } else {
            queryStringData[prefix + key] = value;
        }
    });
    let query = qs.stringify(queryStringData, {
        // filter(prefix, value) {
        //         if (typeof value === 'object' && value !== null) {
        //         return pickBy(value);
        //     }

        //     return value;
        // },
        encodeValuesOnly: true,
        skipNulls: true,
        strictNullHandling: true,
        arrayFormat: 'comma', // for elementGroups
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
            preserveScroll: props.preserveScroll !== false,
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

                if (props.preserveScroll === 'table-top') {
                    const offset = -8;
                    const top =
                        tableFieldset.value.getBoundingClientRect().top +
                        window.pageYOffset +
                        offset;

                    window.scrollTo({top});
                }

                updates.value++;
            },
        },
    );
}

watch(queryBuilderData, async () => {
        // if(queryBuilderData.value.sort !== queryBuilderProps.value.sort) {  // To avoid sort on first load
            try {
                visit(location.pathname + '?' + generateNewQueryString())
            } catch {
                console.error("Can't visit expected path")
            }
        // }
    },
    {deep: true},
);

const inertiaListener = () => {
    updates.value++;
};

onMounted(() => {
    document.addEventListener('inertia:success', inertiaListener);
});

onUnmounted(() => {
    document.removeEventListener('inertia:success', inertiaListener);
});

/* onBeforeMount(() => {
    if ('data' in props.resource) {
        if (props.useForm) props.resource.data.forEach((item) => item.form = useForm({...item}));
    }
}); */


function sortBy(column) {
    if (queryBuilderData.value.sort === column) {
        queryBuilderData.value.sort = `-${column}`;
    } else {
        queryBuilderData.value.sort = column;
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

// const {name} = toRefs(props)

watch(() => props.name, () => {
    // To reset the 'sort' on change Tabs
    queryBuilderData.value.sort = null
    resetQuery()
})

const selectRow: {[key: string]: boolean} = reactive({...props.selectedRow})

// To preserve the object selectRow
if (props.isCheckBox) {
    for(const row in props.resource.data){
        if(props.resource.data[row][props.checkboxKey]) {
            selectRow[props.resource.data[row][props.checkboxKey]] = selectRow[props.resource.data[row][props.checkboxKey]] ? true : false
        }
    }
}

// On select select all
const onClickSelectAll = (state: boolean) => {
    for(const row in props.resource.data){
        selectRow[props.resource.data[row][props.checkboxKey]] = !state
    }
}

const onSelectCheckbox = async (item : Any) => {
    emits('onCheked', item , !selectRow[item[props.checkboxKey]])
    // selectRow[item[props.checkboxKey]] = !selectRow[item[props.checkboxKey]]
    item.is_checked = !item.is_checked
    emits('onChecked', item)

}

watch(selectRow, () => {
    emits('onSelectRow', selectRow)
}, {deep: true})

defineExpose({
    data : props.resource.data,
    queryBuilderData : queryBuilderData,
    selectRow : selectRow
})

const isLoading = ref<string | boolean>(false)

</script>

<template>
    <Transition>
        <!--suppress JSValidateTypes -->
        <slot name='emptyState' :emptyState="queryBuilderProps.emptyState"
            v-if="queryBuilderProps.emptyState?.count === 0 && compResourceMeta.total === 0">
            <EmptyState :data="queryBuilderProps.emptyState">
                <template #button-empty-state>
                    <slot name="button-empty-state" :action="queryBuilderProps.emptyState?.action">
                        <!-- <pre>{{ Object.values(queryBuilderProps.emptyState?.action).length }}</pre> -->
                        <div> <!-- div to replace in case v-if empty  -->
                            <Link v-if="Object.values(queryBuilderProps.emptyState?.action || {}).length"
                                as="div"
                                :href="queryBuilderProps.emptyState?.action?.route?.name ? route(queryBuilderProps.emptyState?.action.route.name, queryBuilderProps.emptyState?.action.route.parameters) : '#'"
                                :method="queryBuilderProps.emptyState?.action?.route?.method"
                                class="mt-4 block"
                                @start="() => isLoading = 'loadingEmptyState'"
                                @finish="() => isLoading = false"
                            >
                                <Button
                                    :style="queryBuilderProps.emptyState?.action.style"
                                    :icon="queryBuilderProps.emptyState?.action.icon"
                                    :label="queryBuilderProps.emptyState?.action.tooltip"
                                    :loading="isLoading === 'loadingEmptyState'"
                                />
                            </Link>
                        </div>
                    </slot>
                </template>
            </EmptyState>

        </slot>

        <!--suppress HtmlUnknownAttribute -->
        <fieldset v-else ref="tableFieldset" :key="`table-${name}`" :dusk="`table-${name}`" class="min-w-0"
            :class="{ 'opacity-75': isVisiting }">
            <div class="py-2 sm:py-0 my-0">
                <!-- Wrapper -->
                <div class="grid grid-flow-col justify-between items-center flex-nowrap px-3 sm:px-4">

                    <!-- Left Section: Records, Model Operations, MO Bulk, Search -->
                    <div class="h-fit flex flex-wrap gap-y-0.5 gap-x-1 items-center my-0.5">
                        <!-- Result Number -->
                        <div class="bg-gray-100 h-fit flex items-center border border-gray-300 overflow-hidden rounded">
                            <div class="grid justify-end items-center text-base font-normal text-gray-700">
                                <div class="px-2 py-[1px] whitespace-nowrap flex gap-x-1.5 flex-nowrap">
                                    <span class="font-semibold tabular-nums">
                                        <CountUp :endVal="compResourceMeta?.total || 0" :duration="1.2" :scrollSpyOnce="true"
                                            :options="{
                                            formattingFn: (number) => locale.number(number)
                                        }" />
                                    </span>

                                    <span class="font-light">
                                        {{
                                            compResourceMeta.total > 1
                                            ? queryBuilderProps.labelRecord?.[1] || queryBuilderProps.labelRecord?.[0] || trans('records')
                                            : queryBuilderProps.labelRecord?.[0] || trans('record')
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- Button: Model Operations Bulk -->
                            <div v-if="queryBuilderProps.modelOperations?.bulk" class="flex">
                                <slot v-for="(linkButton, btnIndex) in queryBuilderProps.modelOperations?.bulk"
                                    :name="`button${linkButton.label}`" :linkButton="linkButton">
                                    <Link v-if="linkButton?.route?.name" as="div"
                                        :href="route(linkButton?.route?.name, linkButton?.route?.parameters)"
                                        :method="linkButton.route?.method || 'get'" v-tooltip="linkButton.tooltip"
                                        :data="selectRow"
                                        :class="[queryBuilderProps.modelOperations?.bulk.length > 1 ? 'first:rounded-l last:rounded-r' : '']">
                                    <Button
                                        :style="Object.values(selectRow).some(value => value) ? linkButton.style : 'disabled'"
                                        :icon="linkButton.icon" :label="linkButton.label" size="l"
                                        class="h-full border-none rounded-none"
                                        :class="{'rounded-l-md': btnIndex === 0, 'rounded-r-md ': btnIndex === queryBuilderProps.modelOperations?.bulk.length - 1}" />
                                    </Link>
                                </slot>
                            </div>
                        </div>

                        <!-- Button: Model Operations -->
                        <div v-if="queryBuilderProps.modelOperations?.createLink" class="flex">
                                <slot v-for="(linkButton, btnIndex) in queryBuilderProps.modelOperations?.createLink"
                                    :name="`button-${kebabCase(linkButton.label)}`"
                                    :linkButton="{...linkButton, btnIndex: btnIndex }">
                                    <!-- {{ linkButton?.route?.name }} -->
                                    <component v-if="linkButton?.route?.name" :is="linkButton.target ? 'a' : Link"
                                        as="div" :target="linkButton.target || undefined"
                                        :href="route(linkButton?.route?.name, linkButton?.route?.parameters)"
                                        :method="linkButton.route?.method || 'get'" v-tooltip="linkButton.tooltip"
                                        :class="[queryBuilderProps.modelOperations?.createLink.length > 1 ? 'first:rounded-l last:rounded-r' : '']">
                                        <Button
                                            :style="linkButton.style"
                                            :type="linkButton.type"
                                            :icon="linkButton.icon"
                                            :label="linkButton.label"
                                            size="xs"
                                            key="1"
                                            class="h-full"
                                        />
                                    </component>
                                </slot>
                            </div>

                        <!-- Search Input Button -->
                        <div v-if="queryBuilderProps.globalSearch" class="flex flex-row">
                            <slot name="tableFilterSearch" :has-global-search="queryBuilderProps.globalSearch"
                                :label="queryBuilderProps.globalSearch ? queryBuilderProps.globalSearch.label : null"
                                :value="queryBuilderProps.globalSearch ? queryBuilderProps.globalSearch.value : null"
                                :on-change="changeGlobalSearchValue">
                                <TableFilterSearch
                                    v-if="queryBuilderProps.globalSearch"
                                    class=""
                                    @resetSearch="() => resetQuery()"
                                    :label="queryBuilderProps.globalSearch.label"
                                    :value="queryBuilderProps.globalSearch.value"
                                    :on-change="changeGlobalSearchValue"
                                    :isVisiting
                                />
                            </slot>
                        </div>
                    </div>

                    <!-- Filter Group -->
                    <div class="flex flex-row justify-end items-center flex-nowrap gap-x-2">
                        <!-- <div class="order-2 sm:order-1 mr-2 sm:mr-4" v-if="queryBuilderProps.hasFilters">
                            <slot name="tableAdvancedFilter" :has-filters="queryBuilderProps.hasFilters"
                                :has-enabled-filters="queryBuilderProps.hasEnabledFilters" :filters="queryBuilderProps.filters"
                                :on-filter-change="changeFilterValue">
                                <TableAdvancedFilter :has-enabled-filters="queryBuilderProps.hasEnabledFilters"
                                    :filters="queryBuilderProps.filters" :on-filter-change="changeFilterValue" />
                            </slot>
                        </div> -->

                        <!-- Filter: date between -->
                        <div  v-if="queryBuilderProps?.betweenDates?.length" class="w-fit flex gap-x-2">
                            <TableBetweenFilter
                                :optionsList="queryBuilderProps?.betweenDates"
                                :tableName="props.name"
                            />
                        </div>

                        <div v-if="queryBuilderProps.dateInterval" class="w-fit flex gap-x-2">
                             <TableDateInterval :dateInterval="queryBuilderProps.dateInterval"/>
                        </div>

                        <!-- Filter: Period -->
                        <div v-if="queryBuilderProps?.period_filter?.length" class="w-fit flex gap-x-2">
                        <!-- <pre>{{ queryBuilderProps?.period_filter }}</pre> -->
                            <TablePeriodFilter
                                :periodList="queryBuilderProps.period_filter"
                                @periodChanged="(data) => queryBuilderData.periodFilter = data"
                                :tableName="props.name"
                            />
                        </div>

                        <!-- Filter: Checkbox element -->
                        <div v-if="Object.keys(queryBuilderProps?.elementGroups || [])?.length" class="w-fit">
                            <TableElements
                                :elements="queryBuilderProps.elementGroups"
                                @checkboxChanged="(data) => queryBuilderData.elementFilter = data"
                                :tableName="props.name" />
                        </div>

                        <!-- Filter: Radio element -->
                        <div v-if="queryBuilderProps.radioFilter?.radio" class="w-fit">
                            <TableRadioFilter
                                :value="queryBuilderProps.radioFilter.radio?.value"
                                :options="queryBuilderProps.radioFilter?.radio?.options"
                                @onSelectRadio="(value: string) => setLodash(queryBuilderData, ['radioFilter'], value)"
                                :tableName="props.name"
                                :isVisiting
                            />
                        </div>


                        <slot name="add-on-button">
                        </slot>

                        <!-- Button: Reset -->
                        <!--suppress HtmlUnknownAttribute -->
                        <!-- <slot name="searchReset" can-be-reset="canBeReset" @resetSearch="() => resetQuery()">
                            <div v-if="canBeReset" class="order-3">
                                <SearchReset @resetSearch="() => resetQuery()" />
                            </div>
                        </slot> -->

                        <!-- Button: Filter table -->
                        <!-- <slot name="tableFilterColumn" :has-search-inputs="queryBuilderProps.hasSearchInputs"
                            :has-search-inputs-without-value="queryBuilderProps.hasSearchInputsWithoutValue"
                            :search-inputs="queryBuilderProps.searchInputsWithoutGlobal" :on-add="showSearchInput">
                            <TableFilterColumn v-if="queryBuilderProps.hasSearchInputs" class="order-4"
                                :search-inputs="queryBuilderProps.searchInputsWithoutGlobal" :has-search-inputs-without-value="queryBuilderProps.hasSearchInputsWithoutValue
                                    " :on-add="showSearchInput" />
                        </slot> -->


                        <!-- Button: Switch toggle search the column of table -->
                        <!-- <slot name="tableColumns" :has-columns="queryBuilderProps.hasToggleableColumns"
                            :columns="queryBuilderProps.columns" :has-hidden-columns="queryBuilderProps.hasHiddenColumns"
                            :on-change="changeColumnStatus">
                            <TableColumns v-if="queryBuilderProps.hasToggleableColumns" class="order-4 mr-4 sm:mr-0 sm:order-5"
                                :columns="queryBuilderProps.columns" :has-hidden-columns="queryBuilderProps.hasHiddenColumns"
                                :on-change="changeColumnStatus" />
                        </slot> -->
                    </div>
                </div>

                <!-- Field: search by column of table-->
                <!-- <slot name="tableSearchRows" :has-search-rows-with-value="queryBuilderProps.hasSearchInputsWithValue"
                    :search-inputs="queryBuilderProps.searchInputsWithoutGlobal"
                    :forced-visible-search-inputs="forcedVisibleSearchInputs" :on-change="changeSearchInputValue">
                    <TableSearchRows v-if="queryBuilderProps.hasSearchInputsWithValue ||
                        forcedVisibleSearchInputs.length > 0
                        " :search-inputs="queryBuilderProps.searchInputsWithoutGlobal"
                        :forced-visible-search-inputs="forcedVisibleSearchInputs" :on-change="changeSearchInputValue"
                        :on-remove="disableSearchInput" />
                </slot> -->

            </div>

            <!-- <pre>{{ compResourceData }}</pre> -->

            <!-- The Main Table -->
            <slot name="tableWrapper" :meta="compResourceMeta">
                <TableWrapper :result="compResourceMeta.total === 0" :class="{ 'mt-0': !hasOnlyData }">
                    <slot name="table">
                        <table class="divide-y divide-gray-200 bg-white w-full">
                            <thead class="bg-gray-50">
                                <tr class="border-t border-gray-200 divide-x divide-gray-200">
                                    <div v-if="isCheckBox"
                                        @xxclick="() => onClickSelectAll(Object.values(selectRow).every((value) => value === true))"
                                        class="py-1.5 cursor-pointer">
                                        <!-- <FontAwesomeIcon
                                            v-if="Object.values(selectRow).every((value) => value === true)"
                                            icon='fal fa-check-square' class='mx-auto block h-5 my-auto' fixed-width
                                            aria-hidden='true' />
                                        <FontAwesomeIcon v-else icon='fal fa-square' class='mx-auto block h-5 my-auto'
                                            fixed-width aria-hidden='true' /> -->
                                    </div>

                                    <slot v-for="column in queryBuilderProps.columns" :name="`header(${column.key})`" :header="column">
                                        <HeaderCell
                                            :key="`table-${name}-header-${column.key}`" :cell="header(column.key)"
                                            :type="columnsType[column.key]" :column="column" :resource="compResourceData">
                                        </HeaderCell>
                                    </slot>
                                </tr>
                            </thead>

                            <tbody class="bg-white divide-y divide-gray-200">
                                <slot name="body" :show="show">
                                    <template v-for="(item, key) in compResourceData"
                                        :key="`table-${name}-row-${key}-${item[checkboxKey]}-${item.id}-${item.slug}`"
                                    >
                                        <tr class=""
                                            :class="[
                                                {
                                                    'bg-gray-50': striped && key % 2,
                                                },
                                                selectRow[item[checkboxKey]] || item.is_checked
                                                    ? 'bg-green-100/70'
                                                    : striped ? 'bg-gray-200 hover:bg-gray-300' : 'hover:bg-gray-50'
                                            ]"
                                        >
                                            <!-- Column: Check box -->
                                            <td v-if="isCheckBox" key="checkbox" class="">
                                                <!--
                                                <div v-if="selectRow[item[checkboxKey]]"
                                                    class="absolute inset-0 bg-lime-500/10 -z-10" />
                                                -->
                                                <FontAwesomeIcon v-show="item.is_checked || selectRow[item[checkboxKey]]"
                                                    @click="async () => (setLodash(item, ['is_checked'], !item.is_checked), emits('onChecked', item))"
                                                    icon='fas fa-check-square' class='text-green-500 p-2 cursor-pointer text-lg mx-auto block' fixed-width
                                                    aria-hidden='true' />
                                                <FontAwesomeIcon v-show="!item.is_checked && !selectRow[item[checkboxKey]]"
                                                    @click="async () => (setLodash(item, ['is_checked'], !item.is_checked), emits('onChecked', item))"
                                                    icon='fal fa-square' class='text-gray-400 hover:text-gray-700 p-2 cursor-pointer text-lg mx-auto block' fixed-width
                                                    aria-hidden='true' />
                                            </td>

                                            <td v-for="(column, index) in queryBuilderProps.columns"
                                                v-show="show(column.key)"
                                                :key="`table-${name}-row-${key}-column-${column.key}`"
                                                class="text-sm py-2 text-gray-600 whitespace-normal h-full" :class="[
                                                    column.type === 'avatar' || column.type === 'icon'
                                                        ? 'text-center min-w-fit px-3'  // if type = icon
                                                        : typeof item[column.key] == 'number' || column.type === 'number' || column.type === 'currency' || column.type === 'date' || column.type === 'date_hm' || column.type === 'date_hms' || column.align === 'right'
                                                            ? 'text-right pl-3 pr-9 tabular-nums'  // if the value is number
                                                            : 'px-6',
                                                    props.rowAlignTop ? 'align-top' : '',
                                                    { 'first:border-l-4 first:border-gray-700 bg-gray-200/75': selectedRow?.[name]?.includes(item[checkboxKey]) },
                                                    column.className
                                                ]">
                                                <slot :name="`cell(${column.key})`"
                                                    :item="{ ...item, index: index, rowIndex : key, editingIndicator: { loading: false, isSucces: false, isFailed: false, editMode: false }, data : item }"
                                                    :proxyItem="item"
                                                    :tabName="name" class="">
                                                    <template v-if="typeof item[column.key] == 'number' || column.type === 'number'">
                                                        {{  locale.number(item[column.key]) }}
                                                    </template>
                                                    <template v-else-if="column.type === 'currency'">
                                                        {{  locale.currencyFormat(item.currency_code || 'usd', item[column.key]) }}
                                                    </template>
                                                    <template v-else-if="column.type === 'date'">
                                                        <span class="whitespace-nowrap">{{  useFormatTime(item[column.key]) }}</span>
                                                    </template>
                                                    <template v-else-if="column.type === 'date_hm'">
                                                        <span class="whitespace-nowrap">{{  useFormatTime(item[column.key], { formatTime: 'hm' }) }}</span>
                                                    </template>
                                                    <template v-else-if="column.type === 'date_hms'">
                                                        <span class="whitespace-nowrap">{{  useFormatTime(item[column.key], { formatTime: 'hms' }) }}</span>
                                                    </template>
                                                    <template v-else>
                                                        {{ item[column.key] }}
                                                    </template>
                                                </slot>
                                            </td>
                                        </tr>

                                        <tr v-if="useExpandTable">
                                            <td :colspan="queryBuilderProps.columns?.length + (isCheckBox ? 1 : 0)" style="padding: 0;">
                                                <slot name="expandRow" :item="{ rowIndex: key }">

                                                </slot>
                                            </td>
                                        </tr>


                                    </template>
                                </slot>

                                <!-- Section: FooterRows -->
                                <slot name="footerRows" :show="show">
                                    <template v-for="(item, key) in queryBuilderProps.footerRows?.data" :key="`footerRows-rows-${key}`">
                                        <tr class="bg-gray-100">
                                            <!-- Column: Check box -->
                                            <td v-if="isCheckBox" key="checkbox" class="h-full flex justify-center">
                                                <!-- <div v-if="selectRow[item[checkboxKey]]" class="absolute inset-0 bg-lime-500/10 -z-10" />
                                                <FontAwesomeIcon v-if="selectRow[item[checkboxKey]] === true" @click="onSelectCheckbox(item)" icon='fal fa-check-square' class='p-2 cursor-pointer' fixed-width aria-hidden='true' />
                                                <FontAwesomeIcon v-else @click="onSelectCheckbox(item)" icon='fal fa-square' class='p-2 cursor-pointer' fixed-width aria-hidden='true' /> -->
                                            </td>

                                            <td v-for="(column, index) in queryBuilderProps.columns"
                                                v-show="show(column.key)"
                                                :key="`footerRows-rows-${key}-column-${column.key}`"
                                                class="text-sm py-2 text-gray-500 whitespace-normal h-full" :class="[
                                                    column.type === 'avatar' || column.type === 'icon'
                                                        ? 'text-center min-w-fit px-3'  // if type = icon
                                                        : typeof item[column.key] == 'number' || column.type === 'number' || column.type === 'currency' || column.align === 'right'
                                                            ? 'text-right pl-3 pr-9 tabular-nums'  // if the value is number
                                                            : 'px-6',
                                                    { 'first:border-l-4 first:border-gray-700 bg-gray-200/75': selectedRow?.[name]?.includes(item[checkboxKey]) },
                                                    column.className
                                                ]"
                                            >
                                                <slot :name="`footerRows-cell(${column.key})`"
                                                    :item="{ ...item, index: index, rowIndex : key, editingIndicator: { loading: false, isSucces: false, isFailed: false, editMode: false }, data : item }"
                                                    :tabName="name" class=""
                                                >
                                                    <template v-if="typeof item[column.key] == 'number' || column.type === 'number'">
                                                        {{  locale.number(item[column.key]) }}
                                                    </template>
                                                    <template v-else-if="column.type === 'currency'">
                                                        {{  locale.currencyFormat(item.currency_code || 'usd', item[column.key]) }}
                                                    </template>
                                                    <template v-else>
                                                        {{ item[column.key] }}
                                                    </template>
                                                </slot>
                                            </td>
                                        </tr>

                                        <tr v-if="useExpandTable">
                                            <td :colspan="queryBuilderProps.columns?.length + (isCheckBox ? 1 : 0)" style="padding: 0;">
                                                <slot name="expandRow" :item="{ rowIndex: key }">

                                                </slot>
                                            </td>
                                        </tr>


                                    </template>
                                </slot>
                            </tbody>
                        </table>
                    </slot>

                    <!-- Pagination -->
                    <slot name="pagination" :on-click="visit" :has-data="hasData" :meta="compResourceMeta"
                        :per-page-options="queryBuilderProps.perPageOptions" :on-per-page-change="onPerPageChange">
                        <Pagination :on-click="visit" :has-data="hasData" :meta="compResourceMeta"
                            :exportLinks="queryBuilderProps.exportLinks"
                            :per-page-options="queryBuilderProps.perPageOptions"
                            :on-per-page-change="onPerPageChange" />
                    </slot>
                </TableWrapper>
            </slot>
        </fieldset>
    </Transition>
</template>

<!--suppress HtmlUnknownAttribute -->
<style scope>
fieldset {
    margin-top: 0 !important;
}
</style>
