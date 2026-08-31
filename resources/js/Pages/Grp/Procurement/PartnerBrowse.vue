<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 29 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { ref, watch } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@common/Components/Image.vue"
import { capitalize } from "@/Composables/capitalize"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"
import { PageHeadingTypes } from "@/types/PageHeading"

type CategoryCard = { id: number, slug: string, code: string, name: string, image: object | null, number_current_products?: number, type?: string }
type ProductCard = { id: number, slug: string, code: string, name: string, image: object | null, price: number | null, available_quantity: number, units: number, org_stock_slug: string | null, our_stock: number | null, our_quarterly_usage: number | null, our_days_of_cover: number | null }
import PartnerMiniShoppingList from "@/Components/Procurement/PartnerMiniShoppingList.vue"

type MiniCartItem = { id: number, quantity: number, org_stock_code: string | null, org_stock_name: string | null, family_name: string | null }
type MiniCart = { partner_name: string, count: number, total: number, currency: string, items: MiniCartItem[], listRoute: { name: string, parameters: (string | number)[] } }

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    orgPartner: { id: number, slug: string, currency: string }
    addRoute: { name: string, parameters: (string | number)[] }
    miniCart: MiniCart
    filters: { q?: string, department?: string, sub_department?: string, family?: string, collection?: string }
    filterNames: Record<string, string>
    level: "root" | "department" | "sub_department" | "family" | "collection" | "search"
    categories: CategoryCard[]
    collections: CategoryCard[]
    products: { data: ProductCard[], links: object, meta: object } | null
    browseStats: { products: number, in_stock: number, departments: number, collections: number }
}>()

const searchTerm = ref(props.filters.q ?? "")
let searchTimeout: ReturnType<typeof setTimeout> | null = null

watch(searchTerm, (value) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }

    searchTimeout = setTimeout(() => {
        router.get(route(route().current() as string, route().params), { q: value || undefined }, { only: ["level", "categories", "collections", "products", "filters", "filterNames", "miniCart"], preserveState: true, replace: true })
    }, 350)
})

function drillInto(category: { slug: string, type?: string }) {
    if (category.type === "department") {
        return goTo({ department: category.slug })
    }
    if (category.type === "family") {
        return goTo({
            ...(props.filters.department ? { department: props.filters.department } : {}),
            ...(props.filters.sub_department ? { sub_department: props.filters.sub_department } : {}),
            family: category.slug,
        })
    }
    return goTo({
        ...(props.filters.department ? { department: props.filters.department } : {}),
        sub_department: category.slug,
    })
}

function goTo(params: Record<string, string | number>) {
    router.get(route(route().current() as string, route().params), params, { only: ["level", "categories", "collections", "products", "filters", "filterNames", "miniCart"], preserveState: true })
}

const browseTab = ref<"categories" | "collections">("categories")

const quantities = ref<Record<number, number>>({})
const justAdded = ref<Record<number, boolean>>({})

function quantityFor(productId: number): number {
    return quantities.value[productId] ?? 1
}

function addToShoppingList(product: ProductCard) {
    if (!product.org_stock_slug) {
        return
    }

    router.post(
        route(props.addRoute.name, [...props.addRoute.parameters, product.org_stock_slug]),
        { quantity: quantityFor(product.id) },
        {
            preserveScroll: true,
            preserveState: true,
            only: ["miniCart"],
            onSuccess: () => {
                justAdded.value[product.id] = true
                setTimeout(() => { justAdded.value[product.id] = false }, 1500)
            },
        }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="flex items-start gap-6 p-6">
        <div class="min-w-0 flex-1 space-y-6">
        <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-500">
            <span><b class="font-semibold text-gray-800 tabular-nums">{{ useLocaleStore().number(browseStats.products) }}</b> {{ trans("products") }}</span>
            <span><b class="font-semibold text-gray-800 tabular-nums">{{ useLocaleStore().number(browseStats.in_stock) }}</b> {{ trans("in stock") }}</span>
            <span><b class="font-semibold text-gray-800 tabular-nums">{{ browseStats.departments }}</b> {{ trans("departments") }}</span>
            <span><b class="font-semibold text-gray-800 tabular-nums">{{ browseStats.collections }}</b> {{ trans("collections") }}</span>
        </div>
        <div class="max-w-md w-full">
            <input
                v-model="searchTerm"
                type="text"
                :placeholder="trans('Search products')"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
        </div>

        <nav v-if="level !== 'search'" class="flex flex-wrap items-center gap-2 text-sm">
            <button class="rounded-full px-3 py-1" :class="level === 'root' ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'" @click="goTo({})">
                {{ trans("All") }} · {{ useLocaleStore().number(browseStats.products) }}
            </button>
            <template v-if="filters.department">
                <span class="text-gray-300">/</span>
                <button class="rounded-full px-3 py-1" :class="!filters.sub_department && !filters.family ? 'bg-indigo-100 text-indigo-700' : 'text-gray-500 hover:bg-gray-100'" @click="goTo({ department: filters.department })">
                    {{ filterNames.department ?? filters.department }}
                </button>
            </template>
            <template v-if="filters.sub_department">
                <span class="text-gray-300">/</span>
                <button class="rounded-full px-3 py-1 bg-indigo-100 text-indigo-700" @click="goTo({ sub_department: filters.sub_department })">
                    {{ filterNames.sub_department ?? filters.sub_department }}
                </button>
            </template>
            <template v-if="filters.family && level === 'family'">
                <span class="text-gray-300">/</span>
                <span class="rounded-full px-3 py-1 bg-indigo-100 text-indigo-700">{{ filterNames.family ?? filters.family }}</span>
            </template>
            <template v-if="filters.collection && level === 'collection'">
                <span class="text-gray-300">/</span>
                <span class="rounded-full px-3 py-1 bg-indigo-100 text-indigo-700">{{ filterNames.collection ?? filters.collection }}</span>
            </template>
        </nav>

        <div v-if="collections.length && categories.length" class="border-b border-gray-200">
            <nav class="-mb-px flex gap-6 text-sm">
                <button
                    class="border-b-2 px-1 pb-2 font-medium"
                    :class="browseTab === 'categories' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    @click="browseTab = 'categories'"
                >
                    {{ level === "root" ? trans("Departments") : trans("Categories") }}
                </button>
                <button
                    class="border-b-2 px-1 pb-2 font-medium"
                    :class="browseTab === 'collections' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    @click="browseTab = 'collections'"
                >
                    {{ trans("Collections") }}
                </button>
            </nav>
        </div>

        <div v-if="collections.length && (!categories.length || browseTab === 'collections')" class="space-y-3">
            <h3 v-if="!categories.length" class="text-sm font-medium text-gray-500">{{ trans("Collections") }}</h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                <button
                    v-for="collection in collections"
                    :key="'collection-' + collection.id"
                    class="group flex flex-col overflow-hidden rounded-lg border border-gray-200 text-left hover:shadow-md"
                    @click="goTo({ collection: collection.slug })"
                >
                    <div class="aspect-square w-full bg-gray-50">
                        <Image :src="collection.image" imageCover />
                    </div>
                    <div class="p-2">
                        <p class="truncate text-sm font-medium text-gray-800">{{ collection.name }}</p>
                    </div>
                </button>
            </div>
        </div>

        <div v-if="categories.length && (!collections.length || browseTab === 'categories')" class="space-y-3">
            <h3 v-if="!collections.length" class="text-sm font-medium text-gray-500">{{ trans("Categories") }}</h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                <button
                    v-for="category in categories"
                    :key="'category-' + category.id"
                    class="group flex flex-col overflow-hidden rounded-lg border border-gray-200 text-left hover:shadow-md"
                    @click="drillInto(category)"
                >
                    <div class="aspect-square w-full bg-gray-50">
                        <Image :src="category.image" imageCover />
                    </div>
                    <div class="p-2">
                        <p class="truncate text-sm font-medium text-gray-800">{{ category.name }}</p>
                        <p v-if="category.number_current_products !== undefined" class="text-xs text-gray-400">
                            {{ category.number_current_products }} {{ trans("products") }}
                        </p>
                    </div>
                </button>
            </div>
        </div>

        <div v-if="products" class="space-y-3">
            <h3 v-if="level === 'search'" class="text-sm font-medium text-gray-500">{{ trans("Search results") }}</h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                <div v-for="product in products.data" :key="product.id" class="flex flex-col overflow-hidden rounded-lg border border-gray-200">
                    <div class="aspect-square w-full bg-gray-50">
                        <Image :src="product.image" imageCover />
                    </div>
                    <div class="flex flex-1 flex-col gap-1 p-2">
                        <p class="truncate text-sm font-medium text-gray-800">{{ product.name }}</p>
                        <p class="text-xs text-gray-400">{{ product.code }}</p>
                        <p class="text-sm font-semibold text-gray-900">
                            {{ product.price !== null ? useLocaleStore().currencyFormat(orgPartner.currency, product.price) : "-" }}
                        </p>
                        <div class="grid grid-cols-[auto_1fr] gap-x-3 text-xs leading-5">
                            <span class="text-gray-400">{{ trans("Their stock") }}</span>
                            <span class="text-right font-medium tabular-nums">{{ useLocaleStore().number(product.available_quantity) }}</span>
                            <template v-if="product.our_stock !== null">
                                <span class="text-gray-400">{{ trans("Our stock") }}</span>
                                <span class="text-right font-medium tabular-nums">{{ useLocaleStore().number(Math.floor(product.our_stock)) }}</span>
                            </template>
                            <template v-if="product.our_days_of_cover !== null">
                                <span class="text-gray-400">{{ trans("We run out in") }}</span>
                                <span
                                    class="text-right font-medium tabular-nums"
                                    :class="{ 'text-red-600': product.our_days_of_cover <= 14, 'text-amber-600': product.our_days_of_cover > 14 && product.our_days_of_cover <= 30 }"
                                >
                                    {{ product.our_days_of_cover === 0 ? trans("now") : `~${product.our_days_of_cover} ${trans("days")}` }}
                                </span>
                            </template>
                            <template v-if="product.our_quarterly_usage">
                                <span class="text-gray-400">{{ trans("Our sales / quarter") }}</span>
                                <span class="text-right font-medium tabular-nums">~{{ useLocaleStore().number(Math.round(product.our_quarterly_usage)) }}</span>
                            </template>
                        </div>

                        <div v-if="product.org_stock_slug" class="mt-auto flex items-center gap-2 pt-2">
                            <input
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="w-16 rounded-md border-gray-300 text-sm shadow-sm"
                                :value="quantityFor(product.id)"
                                @input="quantities[product.id] = Number(($event.target as HTMLInputElement).value)"
                            />
                            <Button
                                size="xs"
                                :type="justAdded[product.id] ? 'positive' : 'create'"
                                :icon="justAdded[product.id] ? 'fal fa-check' : 'fal fa-cart-plus'"
                                :label="trans('Add')"
                                @click="addToShoppingList(product)"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="!products.data.length" class="py-10 text-center text-sm text-gray-400">
                {{ trans("No products found") }}
            </p>
        </div>
        </div>

        <aside class="sticky top-4 hidden w-80 shrink-0 lg:block xl:w-96">
            <PartnerMiniShoppingList :miniCart="miniCart" />
        </aside>
    </div>
</template>
