<script setup lang="ts">
import { ref, computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
  faStar,
  faChevronLeft,
  faChevronRight,
} from "@fortawesome/free-solid-svg-icons"

const reviews = [
  {
    id: 1,
    name: "I**",
    rating: 5,
    message:
      "Lovely choice of products, great prices and wonderful service ❤️ couldn't ask for more. Thank you 😊",
    date: "Posted 1 week ago",
  },
  {
    id: 2,
    name: "Jessica Ma****",
    rating: 5,
    message: "Amazing product, Excellent service, 100% recommend",
    date: "Posted 1 week ago",
  },
  {
    id: 3,
    name: "Luna M***",
    rating: 5,
    message: "Loved how easy it was to order and pick up",
    date: "Posted 1 week ago",
  },
  {
    id: 4,
    name: "David P***",
    rating: 5,
    message: "Very fast delivery and customer support was fantastic.",
    date: "Posted 2 weeks ago",
  },
  {
    id: 5,
    name: "Sarah J***",
    rating: 5,
    message: "Will definitely buy again. Great quality!",
    date: "Posted 2 weeks ago",
  },
]

const current = ref(0)

const visibleReviews = computed(() =>
  reviews.slice(current.value, current.value + 3)
)

function next() {
  if (current.value < reviews.length - 3) current.value++
}

function prev() {
  if (current.value > 0) current.value--
}
</script>


<template>
  <div class="relative rounded-lg border bg-white p-6 shadow-sm">

    <!-- Left Arrow -->
    <button
      @click="prev"
      class="absolute left-3 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white shadow transition hover:bg-gray-100"
    >
      <FontAwesomeIcon :icon="faChevronLeft" />
    </button>

    <!-- Right Arrow -->
    <button
      @click="next"
      class="absolute right-3 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white shadow transition hover:bg-gray-100"
    >
      <FontAwesomeIcon :icon="faChevronRight" />
    </button>

    <div class="flex flex-col gap-8 lg:flex-row">

      <!-- Summary -->
      <div class="w-full border-b pb-6 lg:w-64 lg:border-b-0 lg:border-r lg:pb-0 lg:pr-8">
        <h2 class="text-4xl font-light">
          EXCELLENT
        </h2>

        <div class="mt-3 flex gap-1 text-orange-500">
          <FontAwesomeIcon
            v-for="i in 5"
            :key="i"
            :icon="faStar"
            class="text-3xl"
          />
        </div>

        <div class="mt-4 text-3xl font-semibold">
          4.72
          <span class="text-base font-normal text-gray-500">
            Average
          </span>
        </div>

        <div class="text-gray-500">
          2915 Reviews
        </div>

        <div
          class="mt-5 inline-flex items-center gap-2 rounded-md border px-3 py-1 font-semibold"
        >
          <FontAwesomeIcon
            :icon="faStar"
            class="text-yellow-500"
          />
          REVIEWS.io
        </div>
      </div>

      <!-- Reviews -->
      <div class="grid flex-1 gap-8 md:grid-cols-2 xl:grid-cols-3">
        <div
          v-for="review in visibleReviews"
          :key="review.id"
          class="flex flex-col"
        >
          <div class="flex items-center gap-2">
            <span class="font-semibold">
              {{ review.name }}
            </span>

            <div class="flex gap-1 text-orange-500">
              <FontAwesomeIcon
                v-for="i in review.rating"
                :key="i"
                :icon="faStar"
                class="text-sm"
              />
            </div>
          </div>

          <p class="mt-3 flex-1 text-sm leading-6 text-gray-700">
            {{ review.message }}
          </p>

          <span class="mt-8 text-sm text-gray-400">
            {{ review.date }}
          </span>
        </div>
      </div>

    </div>
  </div>
</template>