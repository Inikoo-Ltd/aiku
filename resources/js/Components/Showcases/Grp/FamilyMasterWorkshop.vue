<script setup lang="ts">
const props = defineProps<{
  data: any
}>()

const product = props.data.data
</script>

<template>
  <div class="max-w-6xl p-4">
    <div
      class="bg-white rounded-xl shadow-md p-6 grid grid-cols-1 lg:grid-cols-5 gap-6"
    >
      <!-- Left: Product Image -->
      <div class="flex justify-center items-start">
        <img
          src="https://tailwindcss.com/plus-assets/img/ecommerce-images/product-page-04-featured-product-shot.jpg"
          alt="Product Image"
          class="w-full max-w-xs h-64 object-cover rounded-md"
        />
      </div>

      <!-- Right: Details -->
      <div class="space-y-6 col-span-1 lg:col-span-4">
        <!-- Stock + Status -->
        <div class="grid grid-cols-2 gap-4 text-sm text-gray-700">
          <div>
            <p class="font-medium">Status:</p>
             Active
          </div>
          <div>
            <p class="font-medium">Online:</p>
            <p>Yes</p>
          </div>
          <div>
            <p class="font-medium">Stock:</p>
            <p>50 units</p>
          </div>
        </div>

        <!-- Pricing -->
       <!--  <div class="bg-gray-50 rounded-md p-4 border text-sm">
          <p><span class="font-medium">Cost:</span> €10.05 (€1.01/bag)</p>
          <p><span class="font-medium">Price:</span> €30.50 (€3.05/bag) margin 67.0%</p>
          <p><span class="font-medium">RRP:</span> €7.35/bag margin 58.5%</p>
        </div> -->

        <!-- Webpage -->
        <div class="text-sm">
          <p class="font-medium">Webpage ID:</p>
          <p class="text-gray-600">jsb-03</p>
        </div>
      </div>
    </div>

    <!-- Sales Table (Optional) -->
    <div class="mt-6 overflow-auto text-sm">
      <p class="mb-2 font-semibold text-gray-700">
        Sales Overview
        <span class="text-xs font-normal text-gray-500">(since Thu 30 Mar 2023)</span>
      </p>
      <table class="min-w-full border border-gray-200 text-center text-gray-600">
        <tbody>
          <tr>
            <td colspan="3" class="border px-2 py-1">€0.00</td>
            <td class="border px-2 py-1">0</td>
            <td class="border px-2 py-1">0 (NA)</td>
          </tr>
          <tr>
            <td class="border px-2 py-1">2023</td>
            <td class="border px-2 py-1">2022</td>
            <td class="border px-2 py-1">2021</td>
            <td class="border px-2 py-1">2020</td>
            <td class="border px-2 py-1">2019</td>
          </tr>
          <tr>
            <td class="border px-2 py-1">€0</td>
            <td class="border px-2 py-1">€0</td>
            <td class="border px-2 py-1">€0</td>
            <td class="border px-2 py-1">€0</td>
            <td class="border px-2 py-1">€0</td>
          </tr>
          <tr>
            <td class="border px-2 py-1">0</td>
            <td class="border px-2 py-1">0</td>
            <td class="border px-2 py-1">0</td>
            <td class="border px-2 py-1">0</td>
            <td class="border px-2 py-1">0</td>
          </tr>
          <tr>
            <td class="border px-2 py-1">2Q 23</td>
            <td class="border px-2 py-1">1Q 23</td>
            <td class="border px-2 py-1">4Q 22</td>
            <td class="border px-2 py-1">3Q 22</td>
            <td class="border px-2 py-1">2Q 22</td>
          </tr>
          <tr>
            <td class="border px-2 py-1">€0</td>
            <td class="border px-2 py-1">€0</td>
            <td class="border px-2 py-1">€0</td>
            <td class="border px-2 py-1">€0</td>
            <td class="border px-2 py-1">€0</td>
          </tr>
          <tr>
            <td class="border px-2 py-1">0</td>
            <td class="border px-2 py-1">0</td>
            <td class="border px-2 py-1">0</td>
            <td class="border px-2 py-1">0</td>
            <td class="border px-2 py-1">0</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
