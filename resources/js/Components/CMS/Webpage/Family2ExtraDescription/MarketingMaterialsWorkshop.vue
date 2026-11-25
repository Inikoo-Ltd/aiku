<script setup lang="ts">
import { ctrans } from '@/Composables/useTrans'
import { computed } from 'vue'
import { getStyles } from "@/Composables/styles"
import MarketingProductPhotos from "@/Iris/Components/BlocksUtils/FamilyExtraDescription2/MarketingProductPhotos.vue"
import MarketingBackdrop from "@/Iris/Components/BlocksUtils/FamilyExtraDescription2/MarketingBackdrop.vue"

const props = defineProps<{
    fieldValue: any
    screenType: "mobile" | "tablet" | "desktop"
}>()

const containerStyle = computed(() => (getStyles(props.fieldValue?.marketing?.container?.properties)))

const titleStyle = computed(() => getStyles(props.fieldValue?.marketing?.title?.container?.properties, props.screenType))

const taglineStyle = computed(() => getStyles(props.fieldValue?.marketing?.tagline?.container?.properties, props.screenType))

const showsBackdrop = computed(() => props.fieldValue?.marketing?.show_backdrop !== false)

</script>

<template>
    <section class="relative w-full overflow-hidden px-6 py-6 md:px-10 lg:px-16" :style="containerStyle">
        <MarketingBackdrop v-if="showsBackdrop" />

        <div class="relative z-10 mx-auto flex max-w-7xl flex-col items-center justify-between gap-5 lg:flex-row">
            <div class="flex-1">
                <h2 class="max-w-xl text-xl font-light leading-tight text-[#13294B] md:text-2xl" :style="titleStyle">
                    {{ ctrans("Marketing materials for your online") }}
                    <br />
                    {{ ctrans("store & social media") }}
                </h2>

                <button
                    :style="getStyles(fieldValue?.marketing?.button?.container?.properties)"
                    class="mt-4 inline-flex items-center gap-2 rounded-md border border-[#1f2937] bg-white px-4 py-1.5 text-sm font-medium text-[#13294B] transition hover:bg-gray-50">
                    <span v-if="fieldValue?.marketing?.button?.text">{{ fieldValue?.marketing?.button?.text }}</span>
                    <span v-else>{{ ctrans("Download Marketing Pack") }}</span>
                    <span>↓</span>
                </button>
            </div>

            <div class="flex flex-col items-center">
                <MarketingProductPhotos :images="fieldValue?.family?.marketing_images" :alt="fieldValue?.family?.name" />

                <h3 class="mt-2 text-center text-lg font-bold leading-none text-[#13294B]" :style="taglineStyle">
                    {{ ctrans("Promote, Engage, Sell") }}
                </h3>
            </div>
        </div>
    </section>
</template>