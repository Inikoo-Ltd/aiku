<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { GridProducts } from "@/Components/Product"
import BlogCardIris from "@/Iris/Components/IrisBlocks/BlogCardIris.vue"
import type { BlogPost } from "@/types/Iris/Blog"

const props = withDefaults(defineProps<{
    data: any
    title?: string
    subtitle?: string
    blockConfig?: any
}>(), {
    title: undefined,
    subtitle: undefined,
    blockConfig: undefined
})

const heading = computed(() => props.blockConfig?.title || props.title || trans('Our Blog'))
const showPublishedDate = computed(() => props.blockConfig?.show_published_date !== false)
const showCta = computed(() => props.blockConfig?.show_cta !== false)
const cardProperties = computed(() => props.blockConfig?.card?.container?.properties)
</script>

<template>
    <div class="min-h-screen overflow-x-hidden ">
        <section class=" bg-white">
            <div class="mx-auto max-w-7xl px-4 pt-12 text-center sm:px-6 sm:pt-12 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl lg:text-5xl">
                    {{ heading }}
                </h1>
                <div class="mx-auto mt-6 h-1 w-16 rounded-full background-primary"></div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
            <GridProducts :resource="data" name="blogs" :label="trans('blog')" :preserve-scroll="true"
                :gridClass="'grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4'">
                <template #card="{ item: post }">
                    <BlogCardIris
                        :post="(post as unknown as BlogPost)"
                        :cardProperties="cardProperties"
                        :showPublishedDate="showPublishedDate"
                        :showCta="showCta"
                        :ctaLabel="blockConfig?.cta_label" />
                </template>
            </GridProducts>
        </section>
    </div>
</template>
