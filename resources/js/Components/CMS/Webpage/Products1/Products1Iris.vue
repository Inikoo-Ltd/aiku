<script setup lang="ts">
import { faFilter } from "@fas";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { getStyles } from "@/Composables/styles";
import { ref, onMounted, watch, computed, toRaw, inject } from "vue";
import axios from "axios";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { notify } from "@kyvg/vue3-notification";
import { routeType } from "@/types/route";
import ProductRender from "./ProductRender.vue";
import FilterProducts from "./FilterProduct.vue";
import Drawer from "primevue/drawer";
import Skeleton from "primevue/skeleton";
import { debounce } from "lodash-es";
import LoadingText from "@/Components/Utils/LoadingText.vue";
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure";
import PureInput from "@/Components/Pure/PureInput.vue";
import { useConfirm } from "primevue/useconfirm";
import { faSearch } from "@fal";
import { faExclamationTriangle, faLayerGroup } from "@far";
import ConfirmDialog from "primevue/confirmdialog";
import { trans } from "laravel-vue-i18n"


const props = defineProps<{
    fieldValue: {
        card_product : { properties : object }
        products_route: {
            iris: {
                route_products: routeType,
                route_out_of_stock_products: routeType
            }
            workshop: routeType
        }
        products: {
            data: object,
            links: object,
            meta: {
                current_page: Number,
                last_page: Number,
                total: Number
            }
        }
        container?: any
        model_type: string
        model_id: Number
    }
    webpageData?: any
    blockData?: Object
    screenType: "mobile" | "tablet" | "desktop"
}>();
console.log(props.fieldValue)
const layout = inject("layout", retinaLayoutStructure);
const products = ref<any[]>(toRaw(props.fieldValue.products.data || []));
const loadingInitial = ref(false);
const loadingMore = ref(false);
const q = ref("");
const orderBy = ref("");
const page = ref(toRaw(props.fieldValue.products.meta.current_page));
const lastPage = ref(toRaw(props.fieldValue.products.meta.last_page));
const filter = ref({ data: {} });
const showFilters = ref(false);
const showAside = ref(false);
const totalProducts = ref(props.fieldValue.products.meta.total);
const settingPortfolio = ref(false);
const isFetchingOutOfStock = ref(false);
const confirm = useConfirm();

const getRoutes = () => {
    if (props.fieldValue.model_type === "ProductCategory") {
        return {
            iris: {
                route_products: {
                    name: "iris.json.product_category.products.index",
                    parameters: { productCategory: props.fieldValue.model_id }
                },
                route_out_of_stock_products: {
                    name: "iris.json.product_category.out_of_stock_products.index",
                    parameters: { productCategory: props.fieldValue.model_id }
                }
            }
        };
    } else if (props.fieldValue.model_type === "Collection") {
        return {
            iris: {
                route_products: {
                    name: "iris.json.collection.products.index",
                    parameters: { collection: props.fieldValue.model_id }
                },
                route_out_of_stock_products: {
                    name: "iris.json.collection.out_of_stock_products.index",
                    parameters: { collection: props.fieldValue.model_id }
                }
            }
        };
    }

    return { iris: { route_products: null, route_out_of_stock_products: null } };
};

function buildFilters(): Record<string, any> {
    const filters: Record<string, any> = {};
    const raw = filter.value.data || {};

    for (const [key, val] of Object.entries(raw)) {
        if (val === null || val === undefined || val === "") continue;
        if (typeof val === "object" && !Array.isArray(val)) {
            for (const [subKey, subVal] of Object.entries(val)) {
                if (subVal === null || subVal === undefined || subVal === "") continue;
                filters[subKey] = subVal;
            }
        } else {
            filters[key] = val;
        }
    }

    return filters;
}

const fetchProducts = async (isLoadMore = false, ignoreOutOfStockFallback = false) => {
    if (isLoadMore) {
        loadingMore.value = true;
    } else {
        loadingInitial.value = true;
    }

    const filters = buildFilters();
    const routes = getRoutes();
    const useOutOfStock = isFetchingOutOfStock.value;

    const currentRoute = useOutOfStock
        ? routes.iris.route_out_of_stock_products
        : routes.iris.route_products;

    try {
        const response = await axios.get(route(currentRoute.name, {
            ...currentRoute.parameters,
            ...filters,
            "filter[global]": q.value,
            sort: orderBy.value,
            index_perPage: 25,
            page: page.value
        }));

        const data = response.data;

        lastPage.value = data?.meta?.last_page ?? data?.last_page ?? 1;
        totalProducts.value = data?.meta?.total ?? data?.total ?? 0;

        if (isLoadMore) {
            products.value = [...products.value, ...(data?.data ?? [])];
        } else {
            products.value = data?.data ?? [];
        }

       if (!ignoreOutOfStockFallback && !useOutOfStock && page.value >= lastPage.value) {
            isFetchingOutOfStock.value = true;
            page.value = 1;
            await fetchProducts(true, true);
        }

    } catch (error) {
        console.log(error);
        notify({ title: "Error", text: "Failed to load products.", type: "error" });
    } finally {
        loadingInitial.value = false;
        loadingMore.value = false;
    }
};


const debFetchProducts = debounce(fetchProducts, 300);

const handleSearch = () => {
    page.value = 1;
    isFetchingOutOfStock.value = false;
    debFetchProducts(false, true);
};

watch([q, orderBy], () => {
    page.value = 1;
    isFetchingOutOfStock.value = false;
    debFetchProducts(false, true);
}, { deep: true });

watch(filter, () => {
    page.value = 1;
    isFetchingOutOfStock.value = false;
    debFetchProducts(false, true);
}, { deep: true });

const loadMore = () => {
    if (page.value < lastPage.value && !loadingMore.value) {
        page.value += 1;
        debFetchProducts(true);
    }
};

const sortOptions = computed(() => {
    const baseOptions = [
        { label: "Latest Arrivals", value: "created_at" },
        { label: "Product Code", value: "code" },
        { label: "Name", value: "name" }
    ];
    if (layout?.iris?.is_logged_in) {
        baseOptions.splice(1, 0, { label: "Price", value: "price" });
    }
    return baseOptions;
});

const sortKey = ref("created_at");
const isAscending = ref(true);


const getArrow = (key: typeof sortKey.value) => {
    if (sortKey.value !== key) return "";
    return isAscending.value ? "↑" : "↓";
};


const isMobile = computed(() => props.screenType === "mobile");

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const sortParam = urlParams.get("order_by");

    if (sortParam) {
        orderBy.value = sortParam;
        const key = sortParam.replace("-", "");
        sortKey.value = key as typeof sortKey.value;
        isAscending.value = !sortParam.startsWith("-");
    }

    if (layout?.iris?.is_logged_in)
        fetchProductHasPortfolio();


    /* debFetchProducts() */
});

const updateQueryParams = () => {
    const url = new URL(window.location.href);
    url.searchParams.set("order_by", orderBy.value);
    window.history.replaceState({}, "", url.toString());
};

const toggleSort = (key: string) => {
    if (sortKey.value === key) {
        isAscending.value = !isAscending.value;
    } else {
        sortKey.value = key;
        isAscending.value = true;
    }

    orderBy.value = isAscending.value ? key : `-${key}`;
    updateQueryParams();
    handleSearch();
};


const productHasPortfolio = ref({
    isLoading: false,
    list: []
});


const getRouteForProductPortfolio = () => {
    const { model_type, model_id } = props.fieldValue;
    if (model_type == "ProductCategory") {
        return route("iris.json.product_category.portfolio_data", {
            productCategory: model_id
        });
    } else if (model_type == "Collection") {
        return route("iris.json.collection.portfolio_data", {
            collection: model_id
        });
    }
};

const fetchProductHasPortfolio = async () => {
    productHasPortfolio.value.isLoading = true;
    try {
        const apiUrl = getRouteForProductPortfolio();

        if (!apiUrl) {
            throw new Error("Invalid model_type or missing route configuration");
        }

        const response = await axios.get(apiUrl);
        productHasPortfolio.value.list = response.data || [];
    } catch (error) {
        console.error(error);
        notify({
            title: "Error",
            text: "Failed to load product portfolio.",
            type: "error"
        });
    } finally {
        productHasPortfolio.value.isLoading = false;
    }
};


const responsiveGridClass = computed(() => {
    const perRow = props.fieldValue?.settings?.per_row ?? {};

    const columnCount = {
        desktop: perRow.desktop ?? 4,
        tablet: perRow.tablet ?? 4,
        mobile: perRow.mobile ?? 2
    };

    const count = columnCount[props.screenType] ?? 1;
    return `grid-cols-${count}`;
});

const handleSetAllToPortfolio = () => {
    confirm.require({
        message: "Are you sure you want to set all products to portfolio?",
        header: "Confirmation",
        icon: "pi pi-exclamation-triangle",
        acceptLabel: "Yes",
        rejectLabel: "Cancel",
        acceptClass: "p-button-danger",
        accept: async () => {
            /* settingPortfolio.value = true
            try {
              const apiUrl = route('iris.json.portfolio.set_all', {
                model_type: props.fieldValue.model_type,
                model_id: props.fieldValue.model_id,
              })

              await axios.post(apiUrl)

              notify({
                title: 'Success',
                text: 'All products have been added to the portfolio.',
                type: 'success',
              })

              fetchProductHasPortfolio()
            } catch (error) {
              console.error(error)
              notify({
                title: 'Error',
                text: 'Failed to update portfolio status.',
                type: 'error',
              })
            } finally {
              settingPortfolio.value = false
            } */
        }
    });
};

</script>

<template>
    <ConfirmDialog>
        <template #icon>
            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
        </template>
    </ConfirmDialog>
    <div class="flex flex-col lg:flex-row" :style="getStyles(fieldValue?.container?.properties, screenType)">

        <!-- Sidebar Filters for Desktop -->
        <transition name="slide-fade">
            <aside v-show="!isMobile && showAside" class="w-68 p-4 transition-all duration-300 ease-in-out">
                <FilterProducts v-model="filter" />
            </aside>
        </transition>

        <!-- Main Content -->
        <main class="flex-1">
            <!-- Search & Sort -->
            <div class="px-4 pt-4 pb-2 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex items-center w-full md:w-1/3 gap-2">
                    <Button v-if="isMobile" :icon="faFilter" @click="showFilters = true" class="!p-3 !w-auto"
                            aria-label="Open Filters" />

                    <!-- Sidebar Toggle for Desktop -->
                    <div v-else class="py-4">
                        <Button :icon="faFilter" @click="showAside = !showAside" class="!p-3 !w-auto"
                                aria-label="Open Filters" />
                    </div>
                    <PureInput
                        v-model="q"
                        @keyup.enter="handleSearch"
                        type="text"
                        placeholder="Search products..."
                        :clear="true"
                        :isLoading="loadingInitial"
                        :prefix="{icon: faSearch, label :''}"
                    />
                </div>

                <!-- Sort Tabs -->
                <div class="flex space-x-6 overflow-x-auto mt-2 md:mt-0 border-b border-gray-300">
                    <button v-for="option in sortOptions" :key="option.value" @click="toggleSort(option.value)"
                            class="pb-2 text-sm font-medium whitespace-nowrap flex items-center gap-1" :class="[
                        'pb-2 text-sm font-medium whitespace-nowrap flex items-center gap-1',
                        sortKey === option.value
                            ? `border-b-2 text-[${layout?.app?.theme?.[0] || '#1F2937'}] border-[${layout?.app?.theme?.[0] || '#1F2937'}]`
                            : `text-gray-600 hover:text-[${layout?.app?.theme?.[0] || '#1F2937'}]`
                        ]" :disabled="loadingInitial || loadingMore">
                        {{ option.label }} {{ getArrow(option.value) }}
                    </button>
                </div>
            </div>
            <div class="px-4 pb-2 flex justify-between items-center text-sm text-gray-600">
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-md border border-gray-200 shadow-sm text-sm">
                    <span class="text-gray-700 font-medium">
                        Showing
                        <span :class="['font-semibold', `text-[${layout?.app?.theme?.[0] || '#1F2937'}]`]">{{
                                products.length }}</span>
                        of
                        <span :class="['font-semibold', `text-[${layout?.app?.theme?.[0] || '#1F2937'}]`]">{{
                                totalProducts }}</span>
                        {{ products.length === 1 ? "product" : "products" }}
                    </span>
                </div>
                <div>
                    <Button
                        v-if="layout?.iris?.is_logged_in"
                        :icon="faLayerGroup"
                        :label="settingPortfolio ? 'Processing...' : 'Set All Products to Portfolio'"
                        class="!p-3 !w-auto"
                        type="secondary"
                        :disabled="settingPortfolio"
                        @click="handleSetAllToPortfolio"
                        aria-label="Set all products to portfolio"
                    />
                </div>
            </div>

            <!-- Product Grid -->
            <div :class="responsiveGridClass" class="grid gap-6 p-4"
                 :style="getStyles(fieldValue?.container?.properties, screenType)">
                <template v-if="loadingInitial">
                    <div v-for="n in 10" :key="n" class="border p-3 rounded shadow-sm bg-white">
                        <Skeleton height="200px" class="mb-3" />
                        <Skeleton width="80%" class="mb-2" />
                        <Skeleton width="60%" class="mb-2" />
                        <Skeleton width="100%" />
                    </div>
                </template>

                <template v-else-if="products.length">
                    <div v-for="(product, index) in products" :key="index"
                         :style="getStyles(fieldValue?.card_product?.properties, screenType)"
                         class="border p-3 relative rounded bg-white">
                        <ProductRender :product="product" :key="index" 
                                       :productHasPortfolio="productHasPortfolio.list[product.id]" />
                    </div>
                </template>

                <template v-else>
                    <div class="col-span-full text-center py-10 text-gray-500">
                    </div>
                </template>
            </div>

            <!-- Load More -->
            <!--  {{ page   }}{{ lastPage }} -->
            <div v-if="page < lastPage && !loadingInitial" class="flex justify-center my-4">
                <Button @click="loadMore" type="tertiary" :disabled="loadingMore">
                    <template v-if="loadingMore">
                        <LoadingText />
                    </template>
                    <template v-else>{{ trans("Load More") }}</template>
                </Button>
            </div>
        </main>

        <!-- Mobile Filters Drawer -->
        <Drawer v-model:visible="showFilters" position="left" :modal="true" :dismissable="true" :closeOnEscape="true" :showCloseIcon="false"
                class="w-80 transition-transform duration-300 ease-in-out">
            <div class="p-4">
                <FilterProducts v-model="filter" />
            </div>
        </Drawer>
    </div>
</template>


<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
    transition: all 0.3s ease;
}

.slide-fade-enter-from,
.slide-fade-leave-to {
    opacity: 0;
    transform: translateX(-10px);
}

aside {
    transition: all 0.3s ease;
}
</style>
