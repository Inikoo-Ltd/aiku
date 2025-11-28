<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Mon, 17 Nov 2025 14:28:48 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
    import { inject } from "vue";
    import { faCheckCircle } from "@fal";
    import { library } from "@fortawesome/fontawesome-svg-core";
    import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";

    library.add(faCheckCircle);

    const steps = inject("steps", []);
</script>

<template>
    <nav aria-label="Progress">
        <ol role="list" class="flex items-center">
            <li v-for="(step, stepIdx) in steps" :key="step.name" :class="[stepIdx !== steps.length - 1 ? 'w-full pr-8 sm:pr-20' : '', 'relative']">
                <template v-if="step.status === 'complete'">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="h-0.5 w-full bg-black"></div>
                    </div>
                    <button type="button" class="relative flex size-8 items-center justify-center rounded-full bg-black hover:bg-black/90">
                        <FontAwesomeIcon icon="fa fa-check-circle" class="text-white size-7" />
                        <span class="sr-only">{{ step.name }}</span>
                    </button>
                </template>
                <template v-else-if="step.status === 'current'">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="h-0.5 w-full bg-gray-200"></div>
                    </div>
                    <button type="button" class="relative flex size-8 items-center justify-center rounded-full border-2 border-black bg-white" aria-current="step">
                        <span class="size-2.5 rounded-full bg-black" aria-hidden="true"></span>
                        <span class="sr-only">{{ step.name }}</span>
                    </button>
                </template>
                <template v-else>
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="h-0.5 w-full bg-gray-200"></div>
                    </div>
                    <button type="button" class="group relative flex size-8 items-center justify-center rounded-full border-2 border-gray-300 bg-white hover:border-gray-400">
                        <span class="size-2.5 rounded-full bg-transparent group-hover:bg-gray-300" aria-hidden="true"></span>
                        <span class="sr-only">{{ step.name }}</span>
                    </button>
                </template>
            </li>
        </ol>
    </nav>
</template>
