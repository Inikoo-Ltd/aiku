<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { youtubeUrl } from "@/Composables/googleAdsCriteria"

/**
 * The parts of one ad, whatever its format: the text a Search ad is made of, the images and logo of
 * a Display or Demand Gen ad, the videos of a Video ad, the cards of a carousel. Lists that are empty
 * for this format render nothing, so a Search ad still looks like a Search ad.
 */
defineProps<{
    ad: {
        headlines: string[]
        long_headlines?: string[]
        descriptions: string[]
        business_name?: string | null
        call_to_action?: string | null
        images?: { url: string | null; name: string | null; width: number | null; height: number | null }[]
        logos?: { url: string | null; name: string | null }[]
        videos?: { video_id: string | null; video_title: string | null; name: string | null }[]
        carousel_cards?: { headline: string | null; description: string | null; image: { url: string | null } | null }[]
        final_urls: string[]
    }
}>()
</script>

<template>
    <div class="text-xs">
        <div v-if="ad.headlines.length" class="mt-1 flex flex-wrap gap-1">
            <span v-for="(headline, i) in ad.headlines" :key="i" class="rounded bg-gray-100 px-2 py-0.5 text-gray-700">
                {{ headline }}
            </span>
        </div>

        <p v-for="(headline, i) in ad.long_headlines ?? []" :key="'long' + i" class="mt-1 font-medium text-gray-700">
            {{ headline }}
        </p>

        <p v-for="(description, i) in ad.descriptions" :key="'desc' + i" class="mt-1 text-gray-600">
            {{ description }}
        </p>

        <p v-if="ad.business_name || ad.call_to_action" class="mt-1 text-gray-500">
            <span v-if="ad.business_name">{{ ad.business_name }}</span>
            <span v-if="ad.business_name && ad.call_to_action"> · </span>
            <span v-if="ad.call_to_action">{{ trans("Button") }}: {{ ad.call_to_action }}</span>
        </p>

        <div v-if="ad.images?.length" class="mt-2 flex flex-wrap gap-2">
            <a
                v-for="(image, i) in ad.images"
                :key="'img' + i"
                :href="image.url ?? undefined"
                target="_blank"
                rel="noopener noreferrer"
                class="block rounded ring-1 ring-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                :title="image.name ?? ''">
                <img
                    v-if="image.url"
                    :src="image.url"
                    :alt="image.name ?? trans('Ad image')"
                    loading="lazy"
                    class="h-20 w-auto max-w-[12rem] rounded object-contain" />
                <span v-else class="block px-2 py-1 text-gray-500">{{ image.name ?? trans("Image") }}</span>
            </a>
        </div>

        <div v-if="ad.logos?.length" class="mt-2 flex flex-wrap items-center gap-2">
            <span class="text-gray-500">{{ trans("Logo") }}</span>
            <img
                v-for="(logo, i) in ad.logos"
                :key="'logo' + i"
                :src="logo.url ?? undefined"
                :alt="logo.name ?? trans('Logo')"
                loading="lazy"
                class="h-8 w-auto rounded ring-1 ring-gray-100" />
        </div>

        <ul v-if="ad.videos?.length" class="mt-2 space-y-0.5">
            <li v-for="(video, i) in ad.videos" :key="'video' + i">
                <a
                    v-if="video.video_id"
                    :href="youtubeUrl(video.video_id)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="primaryLink">
                    {{ video.video_title ?? video.name ?? video.video_id }}
                </a>
                <span v-else class="text-gray-500">{{ video.name ?? trans("Video") }}</span>
            </li>
        </ul>

        <div v-if="ad.carousel_cards?.length" class="mt-2 flex flex-wrap gap-2">
            <div v-for="(card, i) in ad.carousel_cards" :key="'card' + i" class="w-40 rounded ring-1 ring-gray-100">
                <img
                    v-if="card.image?.url"
                    :src="card.image.url"
                    :alt="card.headline ?? trans('Carousel card')"
                    loading="lazy"
                    class="h-24 w-full rounded-t object-cover" />
                <div class="p-2">
                    <p class="font-medium text-gray-700">{{ card.headline }}</p>
                    <p v-if="card.description" class="text-gray-500">{{ card.description }}</p>
                </div>
            </div>
        </div>

        <a
            v-for="(url, i) in ad.final_urls"
            :key="'url' + i"
            :href="url"
            target="_blank"
            rel="noopener noreferrer"
            class="primaryLink mt-1 block truncate">
            {{ url }}
        </a>
    </div>
</template>
