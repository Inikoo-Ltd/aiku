<script setup lang="ts">
import BackgroundBox from '@/Components/BackgroundBox.vue'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import Icon from '@/Components/Icon.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import CountUp from 'vue-countup-v3'

import { faArrowRight } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Link } from '@inertiajs/vue3'
import { capitalize, inject } from 'vue'
import { ChannelLogo } from '@/Composables/Icon/ChannelLogoSvg'
import StatsBox from '@/Components/Stats/StatsBox.vue'
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'
library.add(faArrowRight)

const props = defineProps<{
}>()

const locale = inject('locale', aikuLocaleStructure)

</script>

<template>
    <div class="relative isolate overflow-hidden">
        <!-- <pre>{{ data }}</pre> -->
        <!-- <svg class="absolute inset-0 -z-10 size-full stroke-gray-200 [mask-image:radial-gradient(100%_100%_at_top_right,white,transparent)]"
            aria-hidden="true">
            <defs>
                <pattern id="0787a7c5-978c-4f66-83c7-11c213f99cb7" width="200" height="200" x="50%" y="-1"
                    patternUnits="userSpaceOnUse">
                    <path d="M.5 200V.5H200" fill="none" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" stroke-width="0" fill="url(#0787a7c5-978c-4f66-83c7-11c213f99cb7)" />
        </svg> -->
        
        <div class="mx-auto px-12 pb-12 pt-10 lg:flex lg:px-14 ">
           

            <div xv-else class="xmx-auto max-w-2xl lg:mx-0 lg:shrink-0 lg:pt-8">
                <!-- <div class="">
                    <a href="#" class="inline-flex space-x-6">
                        <span class="rounded-full bg-indigo-600/10 px-3 py-1 text-sm/6 font-semibold text-indigo-600 ring-1 ring-inset ring-indigo-600/10">
                            What's new?
                        </span>
                    </a>
                </div> -->

                <h1 class="mt-10 text-pretty text-5xl font-semibold tracking-tight sm:text-7xl">
                    {{ trans("Manage your API Tokens") }}
                </h1>
                <p class="mt-8 text-pretty text-lg font-medium text-gray-500 sm:text-xl/8">
                    {{ trans("API Tokens are used to authenticate requests to the API. You can create, view, and manage your API tokens here. With API Tokens, you can create order, add products, add client, etc by using Web API.") }}
                </p>
                
                <!-- Section: See Documentation -->
                <div class="mt-10 flex xitems-center flex-col justify-center gap-x-6">
                    <div class="mb-2 text-gray-500">{{ trans("Wanna learn more about API Tokens?") }}</div>
                    <a href="https://documenter.getpostman.com/view/28816137/2sB34Zrjrp" target="_blank" rel="noopener noreferrer" class="w-fit">
                        <Button
                            :label="trans('See documentation')"
                            iconRight="fal fa-external-link"
                        >
                        </Button>
                    </a>
                </div>

                <!-- Section: Sandbox -->
                <div class="mt-10 flex xitems-center flex-col justify-center gap-x-6">
                    <div class="mb-2 text-gray-500">
                        {{ trans("Wanna test the API Tokens in safe mode? You can test it in our staging. Enter the same credential as your current account.") }}
                    </div>
                    <a href="https://canary.aw-dropship.com/app/dropshipping/sale-channels" target="_blank" rel="noopener noreferrer" class="w-fit">
                        <Button
                            :label="trans('Test in staging')"
                            iconRight="fal fa-external-link"
                            type="rainbow"
                        >
                        </Button>
                    </a>
                </div>
            </div>
            
        </div>
    </div>
</template>
