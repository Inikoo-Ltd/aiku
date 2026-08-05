<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import Image from "@common/Components/Image.vue";
import { GridProducts } from "@/Components/Product"

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
                    <article
                        class="group relative flex h-full flex-col overflow-hidden rounded-2xl bg-white ring-1 ring-gray-200 transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:ring-gray-300">
                        <div class="relative aspect-[16/10] w-full overflow-hidden bg-gray-100">
                            <Image v-if="post.image_src"
                                :src="post.image_src"
                                :alt="post.image_alt"
                                class="block h-full w-full transition duration-500 group-hover:scale-105"
                                :imageCover="true"
                            />
                            <img v-else-if="post.third_party_image_preview"
                                :alt="post.image_alt ? post.image_alt : post.title"
                                :src="post.third_party_image_preview"
                                class="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
                            <div v-else
                                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200">
                                <span class="text-3xl font-semibold text-gray-300">{{ post.title?.charAt(0) }}</span>
                            </div>

                            <time v-if="post.published_at" :datetime="post.published_at"
                                class="absolute left-3 top-3 rounded-full bg-white/90 px-3 py-1 text-xs font-medium text-gray-700 shadow-sm backdrop-blur">
                                {{ post.published_at }}
                            </time>
                        </div>

                        <div class="flex flex-1 flex-col gap-3 p-5">
                            <h2
                                class="line-clamp-2 !text-base font-semibold leading-snug text-gray-900 transition-colors duration-200 group-hover:text-blue-600">
                                {{ post.title }}
                            </h2>

                            <span
                                class="mt-auto inline-flex items-center gap-1.5 text-sm font-medium text-blue-600">
                                {{ trans("Read more") }}
                                <span aria-hidden="true"
                                    class="transition-transform duration-300 group-hover:translate-x-1">→</span>
                            </span>
                        </div>

                        <a :href="post.url ? post.url : '#'" class="absolute inset-0 rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2">
                            <span class="sr-only">{{ post.title }}</span>
                        </a>
                    </article>
                </template>
            </GridProducts>
        </section>
    </div>
</template>
