<script setup lang='ts'>

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faDollarSign, faImage } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { Link } from '@inertiajs/vue3'
import Image from "@/Components/Image.vue";
import CountUp from 'vue-countup-v3'
import { trans } from 'laravel-vue-i18n'
library.add(faDollarSign, faImage)

const props = defineProps<{
    data: {
        description: string
        stats: {
            label: string
            icon: string
            value: number
            meta: {
                value: number
                label: string
            }
        }[]
    }
}>()

const locale = inject('locale', aikuLocaleStructure)
</script>

<template>
    <div class=" p-4">
        <div class="grid grid-cols-1 lg:grid-cols-[30%_1fr] gap-6 mt-4 mb-10">   
            <div>
                <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">   
                    <div class="flex items-center justify-between mb-6">
                            <div class="flex-1 mx-4">
                                <div class="bg-white rounded-lg shadow hover:shadow-md transition duration-300">
                                    <Image v-if="data.image" :src="data.image" :imageCover="true"
                                            class="w-full h-40 object-cover rounded-t-lg" />
                                    <div v-else class="flex flex-col justify-center items-center bg-gray-100 w-full h-48">
                                        <FontAwesomeIcon :icon="faImage" class="w-8 h-8 text-gray-400" />
                                        <div class="text-gray-500 text-sm">
                                            {{ trans("No image") }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                    </div>
                    <div class="border-t pt-4 space-y-4 text-sm text-gray-700">
                        <div class="text-sm font-medium">
                            <span>{{ data.name || "No label" }}</span>
                        </div>
                        <div class="text-md">
                            <span class="text-gray-400">{{ data.description || "No description" }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-l-4 border-l-indigo-500 border border-gray-300 max-w-lg px-2 py-2.5 mb-10">
            <div class="text-sm text-gray-400 block">Parent</div>
            <Link :href="data?.parent?.route?.name ? route(data.parent.route.name, data.parent.route.parameters) : '#'" class="primaryLink"> 
                    {{ data.parent.name }}
            </Link>
        </div>

        
        <div class="flex gap-x-3 gap-y-4 flex-wrap">
            <div v-for="fake in data.stats" class="bg-gray-50 min-w-64 border border-gray-300 rounded-md p-6">
                <div class="flex justify-between items-center mb-1">
                    <div class="">{{ fake.label }}</div>
                    <FontAwesomeIcon :icon='fake.icon' class=' text-xl text-gray-400' fixed-width aria-hidden='true' />
                </div>
                <div class="mb-1 text-2xl font-semibold">
                    <CountUp
                        :endVal="fake.value"
                        :duration="1.5"
                        :scrollSpyOnce="true"
                        :options="{
                            formattingFn: (value: number) => locale.number(value)
                        }"
                    />
                </div>
                <!-- <div class="text-sm text-gray-400">{{ fake.meta.value }} {{ fake.meta.label }}</div> -->
            </div>
        </div>
    </div>
</template>