<script setup lang="ts">
import { trans } from "laravel-vue-i18n"


const props = defineProps<{
    blogs: any
}>()

console.log(props.blogs)
const posts = [
    {
        id: 1,
        title: 'Boost your conversion rate',
        href: '#',
        imageUrl:
            'https://images.unsplash.com/photo-1496128858413-b36217c2ce36?auto=format&fit=crop&w=3603&q=80',
        date: 'Mar 16, 2020',
        datetime: '2020-03-16',
    },
    {
        id: 2,
        title: 'How to use search engine optimization to drive sales',
        href: '#',
        imageUrl:
            'https://images.unsplash.com/photo-1547586696-ea22b4d4235d?auto=format&fit=crop&w=3270&q=80',
        date: 'Mar 10, 2020',
        datetime: '2020-03-10',
    },
    {
        id: 3,
        title: 'Improve your customer experience',
        href: '#',
        imageUrl:
            'https://images.unsplash.com/photo-1492724441997-5dc865305da7?auto=format&fit=crop&w=3270&q=80',
        date: 'Feb 12, 2020',
        datetime: '2020-02-12',
    },
]
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
                <article v-for="post in posts" :key="post.id"
                    class="rounded-2xl bg-white h-[350px] shadow-md transition duration-300 overflow-hidden border border-gray-200">
                    <img :src="post.imageUrl" alt="Thumbnail for {{ post.title }}" class="w-full h-56 object-cover" />
                    <div class="p-6 flex flex-col h-full justify-between">
                        <div class="text-sm text-gray-500 mb-2">
                            <time :datetime="post.datetime">{{ post.date }}</time>
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
