<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 21 Mar 2024 18:29:22 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script>
import Layout from "@/Layouts/Error.vue";
export default { layout: Layout, }
</script>

<script setup>

const props = defineProps({
    status: Number,
    title: String,
    description: String,
});

</script>

<template>
    <div class="bg-white max-h- px-4 py-16 sm:px-6 sm:py-24 md:grid md:place-items-center lg:px-8">
        <div class="max-w-max mx-auto">
            <main class="sm:flex">
                <p class="text-4xl font-extrabold text-indigo-600 sm:text-5xl">{{ status }}</p>
                <div class="sm:ml-6">
                    <div class="sm:border-l sm:border-gray-200 sm:pl-6">
                        <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight sm:text-5xl">{{ title }}</h1>
                        <p class="mt-1 text-base text-gray-500">{{ description }}</p>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>




