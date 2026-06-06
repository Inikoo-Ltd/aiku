<script setup lang="ts">
const products = [
  {
    name: "Magic Spell Candles",
    image: "https://picsum.photos/400?random=1",
  },
  {
    name: "Hop Hare Crystal Magic Flower Candles",
    image: "https://picsum.photos/400?random=2",
  },
  {
    name: "Aromatherapy Soy Wax Candles",
    image: "https://picsum.photos/400?random=3",
  },
  {
    name: "Chakra Crystal Candles",
    image: "https://picsum.photos/400?random=4",
  },
  {
    name: "Zodiac Crystal Candles with Gemstone Bracelet",
    image: "https://picsum.photos/400?random=5",
  },
  {
    name: "Luxury Scented Candles",
    image: "https://picsum.photos/400?random=6",
  },
  {
    name: "Natural Soy Candles",
    image: "https://picsum.photos/400?random=7",
  },
  {
    name: "Handmade Crystal Candles",
    image: "https://picsum.photos/400?random=8",
  },
]
</script>

<template>
  <section class="mx-auto w-full max-w-[1700px] bg-white px-4 py-4 sm:px-8 lg:px-14 2xl:max-w-[1900px] 2xl:px-14">
    <!-- Header -->
    <div class="mb-10">
      <h1 class="mb-4 text-4xl font-bold text-slate-900">
        All Candles:
      </h1>

      <div
        class="flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-20"
      >
        <div class="text-2xl text-slate-700">
          1365 Products Found
        </div>

        <div class="flex items-center gap-4">
          <span class="text-xl text-slate-700">
            Filter By Category:
          </span>

          <select
            class="h-11 min-w-[180px] rounded-md border border-slate-400 bg-white px-4"
          >
            <option>All</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Product Grid -->
    <div
      class="grid grid-cols-2 gap-x-8 gap-y-10 md:grid-cols-3 lg:grid-cols-5"
    >
      <a
        v-for="product in products"
        :key="product.name"
        href="#"
        class="group block"
      >
        <div class="overflow-hidden bg-gray-100">
          <img
            :src="product.image"
            :alt="product.name"
            class="aspect-square w-full object-cover transition duration-300 group-hover:scale-105"
          />
        </div>

        <h3
          class="mt-3 text-[18px] leading-tight text-slate-900 line-clamp-2"
        >
          {{ product.name }}
        </h3>
      </a>
    </div>
  </section>
</template>