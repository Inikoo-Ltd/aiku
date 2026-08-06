<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { GridProducts } from "@/Components/Product"
import BlogCardIris from "@/Iris/Components/IrisBlocks/BlogCardIris.vue"
import type { BlogPost } from "@/types/Iris/Blog"

withDefaults(defineProps<{
    data: any
    title?: string
    subtitle?: string
}>(), {
    title: undefined,
    subtitle: undefined
})
</script>

<template>
    <div class="min-h-screen overflow-x-hidden ">
        <section class=" bg-white">
            <div class="mx-auto max-w-7xl px-4 pt-12 text-center sm:px-6 sm:pt-12 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl lg:text-5xl">
                    {{ title ?? trans('Our Blog') }}
                </h1>
                <div class="mx-auto mt-6 h-1 w-16 rounded-full background-primary"></div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
            <GridProducts :resource="data" name="blogs" :label="trans('blog')" :preserve-scroll="true"
                :gridClass="'grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4'">
                <template #card="{ item: post }">
                    <BlogCardIris :post="(post as unknown as BlogPost)" />
                </template>
            </GridProducts>
        </section>
    </div>
</template>
