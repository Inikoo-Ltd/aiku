<script setup>
const product = {
  name: 'Everyday Ruck Snack',
  description:
    "This lightweight and spacious bag keeps all your favorite snacks secure. Perfect for travel or everyday use.",
  image: {
    src: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/product-page-04-featured-product-shot.jpg',
    alt: 'Light green canvas bag with black straps and drawstring top.',
  },
}
</script>

<template>
  <div class="max-w-sm  p-4">
    <div class="rounded-lg overflow-hidden shadow-md border bg-white">
      <img
        :src="product.image.src"
        :alt="product.image.alt"
        class="w-full h-64 object-cover"
      />
      <div class="p-4">
        <h2 class="text-lg font-semibold text-gray-900">{{ product.name }}</h2>
        <p class="mt-2 text-sm text-gray-600">{{ product.description }}</p>
      </div>
    </div>
  </div>
</template>
