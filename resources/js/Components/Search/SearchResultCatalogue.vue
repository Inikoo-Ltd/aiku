<script setup lang="ts">
import Skeleton from 'primevue/skeleton'
import Image from '@/Components/Image.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps<{
    results: {
        products: {
            id: number
            code: string
            name: string
            image: Record<string, any>
            route?: { name: string; parameters?: Record<string, any> }
        }[]
        product_categories: {
            id: number
            code: string
            name: string
            type?: string
            image: Record<string, any>
            route?: { name: string; parameters?: Record<string, any> }
        }[]
        collections: {
            id: number
            code: string
            name: string
            image: Record<string, any>
            route?: { name: string; parameters?: Record<string, any> }
        }[]
    } | null
    isLoading: boolean
    query: string
}>()
</script>

<template>
    <div class="grid grid-cols-5 h-full min-h-0 w-full col-span-12">
        <!-- Column 1: Categories & Collections -->
        <div class="col-span-2 border-r flex flex-col min-h-0">
            <div class="px-4 pt-4 pb-2 border-b">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Categories & Collections</p>
            </div>
            <div class="flex-1 overflow-y-auto p-3 space-y-1">
                <template v-if="isLoading">
                    <div v-for="i in 6" :key="i" class="flex items-center gap-3 p-2">
                        <Skeleton width="2rem" height="2rem" borderRadius="0.375rem" />
                        <div class="flex-1">
                            <Skeleton width="60%" height="0.75rem" class="mb-1" />
                            <Skeleton width="40%" height="0.625rem" />
                        </div>
                    </div>
                </template>
                <template v-else>
                    <template v-if="results?.product_categories?.length">
                        <p class="text-[10px] uppercase tracking-widest text-gray-400 px-2 pt-1 pb-0.5">Departments & Families</p>
                        <Link
                            v-for="category in results.product_categories"
                            :key="category.id"
                            :href="route('grp.helpers.redirect_product_category', { productCategory: category.slug })"
                            class="flex items-center gap-3 px-2 py-1.5 rounded-lg hover:bg-slate-100 transition cursor-pointer group"
                        >
                            <div class="w-8 h-8 rounded-md overflow-hidden bg-gray-100 flex-shrink-0 flex items-center justify-center">
                                <Image v-if="category.image" :src="category.image" class="w-full h-full object-cover" />
                                <span v-else class="text-[10px] text-gray-400 font-bold uppercase">{{ category.code?.slice(0, 2) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-800 truncate group-hover:text-slate-900">{{ category.name }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ category.type ?? category.code }}</p>
                            </div>
                        </Link>
                    </template>

                    <template v-if="results?.collections?.length">
                        <p class="text-[10px] uppercase tracking-widest text-gray-400 px-2 pt-3 pb-0.5">Collections</p>
                        <Link
                            v-for="collection in results.collections"
                            :key="collection.id"
                            :href="route('grp.helpers.redirect_product_category', { productCategory: collection.slug })"
                            class="flex items-center gap-3 px-2 py-1.5 rounded-lg hover:bg-slate-100 transition cursor-pointer group"
                        >
                            <div class="w-8 h-8 rounded-md overflow-hidden bg-gray-100 flex-shrink-0 flex items-center justify-center">
                                <Image v-if="collection.image" :src="collection.image" class="w-full h-full object-cover" />
                                <span v-else class="text-[10px] text-gray-400 font-bold uppercase">{{ collection.code?.slice(0, 2) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-800 truncate group-hover:text-slate-900">{{ collection.name }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ collection.code }}</p>
                            </div>
                        </Link>
                    </template>

                    <div
                        v-if="!results?.product_categories?.length && !results?.collections?.length"
                        class="flex h-32 items-center justify-center text-gray-400 text-sm"
                    >
                        No categories found
                    </div>
                </template>
            </div>
        </div>

        <!-- Column 2: Products (max 9) -->
        <div class="col-span-3 flex flex-col min-h-0">
            <div class="px-4 pt-4 pb-2 border-b">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Products</p>
            </div>
            <div class="flex-1 overflow-y-auto p-3">
                <template v-if="isLoading">
                    <div class="grid grid-cols-4 gap-2">
                        <div v-for="i in 9" :key="i" class="flex flex-col gap-1">
                            <Skeleton height="4rem" borderRadius="0.5rem" />
                            <Skeleton width="80%" height="0.625rem" />
                            <Skeleton width="55%" height="0.5rem" />
                        </div>
                    </div>
                </template>

                <template v-else-if="results?.products?.length">
                    <div class="grid grid-cols-4 gap-2">
                        <a
                            v-for="product in results.products.slice(0, 9)"
                            :key="product.id"
                            :href="product.route?.name ? route(product.route.name, product.route.parameters) : '#'"
                            class="group flex flex-col rounded-lg overflow-hidden border border-transparent hover:border-slate-200 hover:shadow-sm transition cursor-pointer"
                        >
                            <div class="w-full aspect-square bg-gray-100 overflow-hidden flex items-center justify-center">
                                <Image v-if="product.image" :src="product.image" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200" />
                                <span v-else class="text-xs text-gray-300 font-bold uppercase">{{ product.code?.slice(0, 3) }}</span>
                            </div>
                            <div class="p-1.5">
                                <p class="text-xs font-medium text-slate-800 truncate leading-tight">{{ product.name }}</p>
                                <p class="text-[10px] text-gray-400 truncate">{{ product.code }}</p>
                            </div>
                        </a>
                    </div>
                </template>
                
                <div v-else class="flex h-32 items-center justify-center text-gray-400 text-sm">
                    No products found
                </div>
            </div>
        </div>
    </div>
</template>