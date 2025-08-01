<script setup lang="ts">
import { faCube, faLink, faImage } from "@fortawesome/free-solid-svg-icons"
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed } from "vue"

library.add(faCube, faLink, faImage)

const props = defineProps<{
  modelValue?: any
  webpageData?: any
  blockData?: Object
  indexBlock: number
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()

// Dummy data fallback
const post = computed(() => props.modelValue ?? {
  title: "Mengenal Web3: Masa Depan Internet yang Terdesentralisasi",
  date: "2025-07-25",
  category: "Teknologi",
  coverImage: "https://source.unsplash.com/1200x600/?blockchain,technology",
  content: `
    <p>Web3 adalah evolusi dari internet saat ini, di mana teknologi blockchain memungkinkan interaksi tanpa perantara. Dengan Web3, pengguna memiliki kendali lebih atas data pribadi mereka dan dapat berpartisipasi dalam ekosistem digital secara langsung.</p>
    
    <h2>Apa Itu Web3?</h2>
    <p>Web3 mengandalkan smart contract, token digital, dan protokol terbuka untuk menciptakan dunia digital yang lebih adil. Ini berbeda dengan Web2 yang berpusat pada platform besar seperti Google atau Facebook.</p>
    
    <blockquote>
      "Web3 is not just a technology shift. It’s a change in how we think about ownership and trust on the internet." – Vitalik Buterin
    </blockquote>
    
    <p>Contoh aplikasi Web3 termasuk wallet crypto, NFT marketplace, dan DAO (Decentralized Autonomous Organization).</p>
  `,
  tags: ["Web3", "Blockchain", "Internet", "Teknologi", "MasaDepan"]
})
</script>

<template>
  <article class="max-w-3xl mx-auto px-4 py-8 text-gray-800">
    <!-- SEO Optimized Title -->
    <h1 class="text-4xl font-extrabold leading-tight mb-2">
      {{ post.title }}
    </h1>

    <!-- Date & Category -->
    <div class="text-sm text-gray-500 mb-6">
      <time :datetime="post.date">{{ new Date(post.date).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' }) }}</time>
    </div>

    <!-- Hero Image -->
    <img
      v-if="post.coverImage"
      :src="post.coverImage"
      alt="Gambar sampul artikel"
      class="rounded-xl shadow-md w-full mb-8"
    />

    <!-- Article Content -->
    <div
      class="prose prose-blue max-w-none"
      v-html="post.content"
    />

  </article>
</template>

<style scoped>
.prose img {
  border-radius: 0.5rem;
}
</style>
