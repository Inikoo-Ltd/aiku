<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
    faHeart,
    faHashtag,
    faStore,
    faBullhorn,
    faBell
} from '@fal'
import { ctrans } from '@/Composables/useTrans'
import { library } from '@fortawesome/fontawesome-svg-core'
import { computed } from 'vue'
import { getStyles } from "@/Composables/styles"

const props = defineProps<{
    fieldValue: any
    screenType: "mobile" | "tablet" | "desktop"
}>()

library.add(faHeart,
    faHashtag,
    faStore,
    faBell,
    faBullhorn
)

const containerStyle = computed(() => (getStyles(props.fieldValue?.marketing?.container?.properties)))

console.log(props.fieldValue)
</script>

<template>
    <section class="w-full px-6 py-12 md:px-10 lg:px-16" :style="containerStyle">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-10 lg:flex-row">
            <!-- Left Content -->
            <div class="flex-1">
                <h2 class="max-w-xl text-3xl font-light leading-tight text-[#13294B] md:text-4xl">
                    {{ ctrans("Marketing materials for your online") }}
                    <br />
                    {{ ctrans("store & social media") }}
                </h2>

              
                    
                    <button
                         :style="{
                    ...getStyles(fieldValue?.marketing?.button?.container?.properties)
                }"
                        class="mt-8 inline-flex items-center gap-3 rounded-lg border border-[#1f2937] bg-white px-6 py-3 text-base font-medium text-[#13294B] transition hover:bg-gray-50">
                         <span v-if="fieldValue?.marketing?.button?.text">{{ fieldValue?.marketing?.button?.text }}</span>
                        <span v-else>{{ ctrans("Download Marketing Pack") }}</span>
                        <span class="text-lg">
                            ↓
                        </span>
                    </button>


            </div>

            <!-- Right Content -->
            <div class="flex flex-col items-center">
                <div class="relative w-[280px] h-[220px]">
                    <!-- Background Glow -->
                    <div
                        class="absolute inset-0 bg-gradient-to-br from-pink-100 via-purple-100 to-blue-100 rounded-3xl blur-xl opacity-70" />

                    <!-- Floating Like -->
                    <div
                        class="absolute left-2 top-4 z-20 flex h-14 w-14 items-center justify-center rounded-2xl bg-white shadow-xl">
                        <FontAwesomeIcon icon="fal fa-heart" class="text-2xl text-purple-500" />
                    </div>

                    <!-- Floating Hashtag -->
                    <div
                        class="absolute right-2 top-10 z-20 flex h-14 w-14 items-center justify-center rounded-2xl bg-white shadow-xl">
                        <FontAwesomeIcon icon="fal fa-hashtag" class="text-2xl text-pink-500" />
                    </div>

                    <!-- Floating Notification -->
                    <div
                        class="absolute bottom-5 left-4 z-20 flex h-14 w-14 items-center justify-center rounded-2xl bg-white shadow-xl">
                        <FontAwesomeIcon icon="fal fa-bell" class="text-2xl text-blue-500" />
                    </div>

                    <!-- Phone -->
                    <div
                        class="absolute left-1/2 top-1/2 z-10 h-[170px] w-[90px] -translate-x-1/2 -translate-y-1/2 rotate-12 rounded-[28px] bg-black p-2 shadow-2xl">
                        <div class="relative h-full w-full overflow-hidden rounded-[22px]">
                            <!-- Screen -->
                            <div class="absolute inset-0 bg-gradient-to-br from-pink-300 via-purple-300 to-blue-300" />

                            <!-- Content -->
                            <div class="absolute inset-0 p-2">
                                <div class="flex gap-1">
                                    <div class="h-8 flex-1 rounded-lg bg-white/70" />
                                    <div class="h-8 w-8 rounded-lg bg-white/70" />
                                </div>

                                <div class="mt-2 h-16 rounded-xl bg-white/60" />

                                <div class="mt-2 flex gap-2">
                                    <div class="h-8 flex-1 rounded-lg bg-white/60" />
                                    <div class="h-8 flex-1 rounded-lg bg-white/60" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <h3 class="mt-6 text-center text-[36px] font-bold leading-none text-[#13294B]">
                    {{ ctrans("Promote, Engage, Sell") }}
                </h3>
            </div>
        </div>
    </section>
</template>