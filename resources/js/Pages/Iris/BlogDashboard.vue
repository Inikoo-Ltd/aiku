<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { useFormatTime } from "@/Composables/useFormatTime"
import Image from "@/Components/Image.vue";

const props = defineProps<{
    blogs: any
}>()

console.log(props.blogs)
</script>

<template>
    <section class="bg-gray-50 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14">
                <h2 class="text-4xl sm:text-5xl font-bold text-gray-900 tracking-tight">
                    {{ trans('Our Blog') }}
                </h2>
            </div>

            <div class="grid gap-8 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                <article v-for="post in blogs" :key="post.id"
                    class="rounded-2xl bg-white h-fit shadow-md transition duration-300 overflow-hidden border border-gray-200">
                    <Image :src="post?.published_layout?.web_blocks[0]?.web_block?.layout?.data?.fieldValue?.image?.source"
                        :alt="post?.published_layout?.web_blocks[0]?.web_block?.layout?.data?.fieldValue?.image?.alt"
                        class="w-full h-56" :imageCover="true"/>
                    <div class="p-6 flex flex-col h-full justify-between">
                        <div class="text-sm text-gray-500 mb-2">
                            <time :datetime="post.published_at">{{ useFormatTime(post.published_at) }}</time>
                        </div>
                        <span class="text-sm font-semibold text-gray-800 mb-3 line-clamp-2">
                            <a :href="post.href" class="block">
                                {{ post.title }}
                            </a>
                        </span>

                        <div class="mt-auto">
                            <a :href="post.href" class="inline-flex items-center text-sm font-medium text-blue-600">
                                Read more →
                            </a>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>
