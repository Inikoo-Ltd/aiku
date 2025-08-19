<script setup lang="ts">
import { ref, computed, onMounted, nextTick, inject } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
  faCube,
  faLink,
  faImage,
  faEnvelope
} from "@fortawesome/free-solid-svg-icons"
import {
  faFacebook,
  faTwitter,
  faLinkedin,
  faXTwitter,
  faInstagram
} from "@fortawesome/free-brands-svg-icons"

import Image from "@/Components/Image.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { getStyles } from "@/Composables/styles"

library.add(faCube, faLink, faImage, faEnvelope, faFacebook, faTwitter, faLinkedin)

const props = defineProps<{
  fieldValue: {
    title: string
    published_date?: string
    image?: {
      source?: string
      alt?: string
    }
    content: string
  }
}>()

const layout: any = inject("layout", {})

const primaryColor = computed(() => {
  return layout?.iris?.theme?.color?.[4] || "#3b82f6"
})

const displayDate = computed(() => {
  return props.fieldValue.published_date
    ? new Date(props.fieldValue.published_date)
    : new Date()
})

const contentRef = ref<HTMLElement | null>(null)
const headings = ref<{ id: string; text: string; level: number }[]>([])
const currentHeadingId = ref<string | null>(null)

onMounted(async () => {
  await nextTick()
  if (!contentRef.value) return

  headings.value = []

  const headingElements = contentRef.value.querySelectorAll("h1, h2, h3")

  headingElements.forEach((el, index) => {
    const id = `heading-${index}`
    el.setAttribute("id", id)
    headings.value.push({
      id,
      text: el.textContent || `Section ${index + 1}`,
      level: parseInt(el.tagName.replace("H", ""))
    })
  })

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          currentHeadingId.value = entry.target.id
        }
      })
    },
    {
      rootMargin: "0px 0px -70% 0px",
      threshold: 0.1
    }
  )

  headingElements.forEach((el) => observer.observe(el))
})

const shareUrl = encodeURIComponent(window.location.href)

console.log(props.fieldValue)
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-12 max-w-7xl mx-auto px-4 py-10 text-gray-800">
    <!-- Sidebar -->
    <aside class="lg:sticky lg:top-10 max-h-[80vh] overflow-y-auto hidden lg:block border-r border-gray-100 pr-6">
      <div class="text-sm font-semibold text-gray-500 mb-4 uppercase tracking-wider">
        Content
      </div>

      <ul class="text-sm no-bullets" v-if="headings.length">
        <li v-for="heading in headings" :key="heading.id">
          <a :href="`#${heading.id}`" :style="currentHeadingId === heading.id ? {
            color: primaryColor,
            borderLeftColor: primaryColor,
            backgroundColor: `${primaryColor}15`
          } : {}" :class="[
            'block px-3 py-1.5 border-l-2 rounded-sm transition-all duration-150',
            heading.level === 2 ? 'ml-3' : '',
            heading.level === 3 ? 'ml-6 text-[0.95rem]' : '',
            currentHeadingId === heading.id
              ? 'font-semibold'
              : 'text-gray-600 hover:bg-gray-50'
          ]">
            {{ heading.text }}
          </a>
        </li>
      </ul>

      <div class="mt-10">
        <div class="text-sm font-semibold text-gray-500 mb-4 uppercase tracking-wider">Latest Posts</div>
        <div class="space-y-3">
          <a v-for="post in fieldValue.latest_blogs" :key="post.id" :href="post.url"
            class="flex items-center gap-3 group hover:bg-gray-50 p-2 rounded-md transition">
           <!--  <img :src="post.image" :alt="post.title"
              class="w-16 h-14 object-cover rounded-md border border-gray-200 shadow-sm" /> -->
               <Image :src="post?.published_layout?.web_blocks[0]?.web_block?.layout?.data?.fieldValue?.image?.source"
                        :alt="post?.published_layout?.web_blocks[0]?.web_block?.layout?.data?.fieldValue?.image?.alt"
                        class="w-16 h-14 object-cover rounded-md border border-gray-200 shadow-sm" :imageCover="true"/>
            <div class="text-sm font-medium text-gray-700 group-hover:text-gray-900">
              {{ post.title }}
            </div>
          </a>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <article class="max-w-3xl mx-auto">
      <!-- <h1 class="text-4xl font-bold tracking-tight mb-3 leading-snug text-gray-900">
        {{ fieldValue.title }}
      </h1> -->

      <div v-html="fieldValue.title" :style="getStyles(fieldValue.properties, screenType)" class="text-4xl font-bold tracking-tight mb-3 leading-snug text-gray-900"/>

      <div class="text-sm text-gray-500 mb-6">
        {{ useFormatTime(displayDate) }}
      </div>

      <div
        class="w-full mb-8 rounded-xl overflow-hidden aspect-[2/1] bg-gray-100 flex items-center justify-center shadow-sm">
        <Image v-if="fieldValue.image?.source" :src="fieldValue.image.source" :alt="fieldValue.image.alt"
          :imageCover="true" class="w-full h-full object-cover" />
        <FontAwesomeIcon v-else :icon="['fas', 'image']" class="text-gray-300 text-6xl" />
      </div>

      <!-- Article Content -->
      <div :style="getStyles(fieldValue.properties, screenType)">
        <div class="prose prose-blue max-w-none scroll-smooth mb-10" ref="contentRef" v-html="fieldValue.content" />
      </div>


      <!-- Share Buttons Section -->

      <div class="text-sm font-semibold text-gray-500 mb-4 uppercase tracking-wider">Share : </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
        <a :href="`https://www.facebook.com/sharer/sharer.php?u=${shareUrl}`" target="_blank" rel="noopener"
          class="flex items-center justify-center gap-2 w-full py-2 px-4 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
          <FontAwesomeIcon :icon="['fab', 'facebook']" />
          Facebook
        </a>

        <a :href="`https://twitter.com/intent/tweet?url=${shareUrl}&text=${encodeURIComponent(fieldValue.title)}`"
          target="_blank" rel="noopener"
          class="flex items-center justify-center gap-2 w-full py-2 px-4 bg-gray-800 text-white rounded-md hover:bg-sky-600 transition">
          <FontAwesomeIcon :icon="faXTwitter" />
          Twitter
        </a>

        <a :href="`https://www.linkedin.com/shareArticle?url=${shareUrl}&title=${encodeURIComponent(fieldValue.title)}`"
          target="_blank" rel="noopener"
          class="flex items-center justify-center gap-2 w-full py-2 px-4 bg-blue-800 text-white rounded-md hover:bg-blue-900 transition">
          <FontAwesomeIcon :icon="['fab', 'linkedin']" />
          LinkedIn
        </a>

        <a :href="`https://www.instagram.com/YOUR_INSTAGRAM_USERNAME/`" target="_blank" rel="noopener"
          class="flex items-center justify-center gap-2 w-full py-3 px-4 bg-pink-500 text-white rounded-md hover:bg-pink-600 transition">
          <FontAwesomeIcon :icon="faInstagram" />
          Instagram
        </a>

        <a :href="`mailto:?subject=${encodeURIComponent(fieldValue.title)}&body=${shareUrl}`"
          class="flex items-center justify-center gap-2 w-full py-2 px-4 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition">
          <FontAwesomeIcon :icon="['fas', 'envelope']" />
          Email
        </a>
      </div>
    </article>
  </div>
</template>

<style scoped>
.prose img {
  border-radius: 0.5rem;
}

html {
  scroll-behavior: smooth;
}

.no-bullets {
  list-style: none !important;
  padding-left: 0 !important;
  margin-left: 0 !important;
}
</style>
