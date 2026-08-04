<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import Image from "@common/Components/Image.vue";
import { GridProducts } from "@/Components/Product"

defineProps<{
    data: any
}>()

</script>

<template>
    <section class="bg-gray-50 pt-6 pb-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-7">
                <h1 class="text-4xl sm:text-5xl font-bold text-gray-900 tracking-tight">
                    {{ trans('Our Blog') }}
                </h1>
            </div>

            <GridProducts :resource="data" name="blogs" :label="trans('blog')" :preserve-scroll="true"
                :gridClass="'grid gap-8 grid-cols-1 sm:grid-cols-2 lg:grid-cols-5'">
                <template #card="{ item: post }">
                    <article
                        class="flex h-full flex-col rounded-2xl bg-white shadow-md transition duration-300 overflow-hidden border border-gray-200">
                        <a :href="post.url ? post.url : '#' " class="block h-56 w-full shrink-0 bg-gray-100">
                            <Image v-if="post.image_src"
                                :src="post.image_src"
                                :alt="post.image_alt"
                                class="h-full w-full cursor-pointer"
                                :imageCover="true"
                            />
                            <img v-else-if="post.third_party_image_preview" :alt="post.image_alt ? post.image_alt : post.title" :src="post.third_party_image_preview" class="h-full w-full object-cover cursor-pointer" />
                        </a>

                        <div class="flex flex-1 flex-col p-6">
                            <div class="text-sm text-gray-500 mb-2">
                                <time :datetime="post.published_at">{{ post.published_at }}</time>
                            </div>
                            <span class="text-sm font-semibold text-gray-800 mb-3 line-clamp-2">
                                <a :href="post.url ? post.url : '#' " class="block">
                                    {{ post.title }}
                                </a>
                            </span>

                            <div class="mt-auto pt-2">
                                <a :href="post.url ? post.url : '#' " :aria-label="trans('Read more') + ': ' + post.title" class="inline-flex items-center text-sm font-medium text-blue-600">
                                    {{ trans("Read more") }} →
                                </a>
                            </div>
                        </div>
                    </article>
                </template>
            </GridProducts>
        </div>
    </section>
</template>
