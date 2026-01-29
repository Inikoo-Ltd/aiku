<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { Popover } from 'primevue'
import { ref } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { OfferResource } from '@/types/Catalogue/Offers'
library.add(faInfoCircle)

const props = defineProps<{
    offer: OfferResource
}>()

const _popoverInfoCircle = ref<InstanceType<any>[] | null>(null)
</script>

<template>
    <section
        class="relative flex justify-between w-full md:w-fit overflow-hidden rounded-lg px-px py-px shadow-md mb-2 bg-[#ff862f]"
        aria-label="Volume Discount Offer Label">

        <!-- Content -->
        <div class="w-full relative flex items-center text-white font-bold px-7 text-4xl">
            <span v-if="offer?.max_percentage_discount">
                {{ Number(offer?.max_percentage_discount) * 100 }}%
            </span>
            <span v-else>{{ offer.allowances?.[0]?.label }}</span>
        </div>
        
        <div class="bg-white rounded-md px-2 py-1 flex items-center gap-x-4">
            <div>
                <!-- <div class="whitespace-nowrap capitalize">{{ offer.allowances?.[0].class }}</div> -->
                <div class="whitespace-nowrap capitalize">
                    {{ trans("Volume Discount") }}
                </div>
                <div class="text-xs whitespace-nowrap opacity-70">
                    {{ offer.triggers_labels?.join('/') }}
                </div>
            </div>
            <span @click="() => (_popoverInfoCircle?.toggle())"
                @mouseenter="_popoverInfoCircle?.show" @mouseleave="_popoverInfoCircle?.hide"
                class="opacity-60 hover:opacity-100 cursor-pointer">
                <FontAwesomeIcon icon="fal fa-info-circle" class="" fixed-width aria-hidden="true" />
            </span>
        </div>

        <!-- Popover: Question circle discount -->
        <Popover ref="_popoverInfoCircle" :style="{ width: '300px' }" class="py-1 px-2">
            <div class="text-xs">
                <p class="font-bold mb-4">{{ trans("VOLUME DISCOUNT") }}</p>
                <p class="inline-block mb-4 text-justify">
                    {{ trans("You don't need Gold Reward status to access the lower price") }}.
                </p>
                <p class="mb-4 text-justify">
                    {{ trans("Order the listed volume and the member price applies automatically at checkout") }}. {{
                        trans("The volume can be made up from the whole product family, not just the same item") }}.
                </p>
            </div>
        </Popover>
    </section>
</template>