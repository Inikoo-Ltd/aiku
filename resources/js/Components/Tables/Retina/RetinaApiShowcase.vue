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
import StatsBox from '@/Components/Stats/StatsBox.vue'
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'
library.add(faArrowRight)

const props = defineProps<{
}>()

const emit = defineEmits<{
    generateToken: []
}>()

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', aikuLocaleStructure)

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

        <div class="mx-auto px-6 py-6 lg:flex lg:px-8">


            <div xv-else class="xmx-auto max-w-2xl lg:mx-0 lg:shrink-0">
                <!-- <div class="">
                    <a href="#" class="inline-flex space-x-6">
                        <span class="rounded-full bg-indigo-600/10 px-3 py-1 text-sm/6 font-semibold text-indigo-600 ring-1 ring-inset ring-indigo-600/10">
                            What's new?
                        </span>
                    </a>
                </div> -->

                <h1 class="text-pretty text-2xl font-semibold tracking-tight">
                    {{ trans("Connect to the API") }}
                </h1>
                <p class="mt-3 max-w-2xl text-sm text-gray-500">
                    {{ trans("Connect your own systems directly to your account: browse the product catalogue with live prices, build your portfolio, manage your clients, create and submit orders, follow their progress, and download your product data as CSV or JSON feeds — everything you can do here, automated from your side.") }}
                </p>

                <!-- Section: See Documentation -->
                <div class="mt-6 flex flex-col gap-x-6">
                    <div class="mb-2 text-sm text-gray-500">{{ trans("Full reference of the available endpoints, parameters and examples:") }}</div>
                    <a :href="layout?.retina?.type === 'fulfilment' ? 'https://documenter.getpostman.com/view/28816137/2sBY4WmwBA' : 'https://documenter.getpostman.com/view/28816137/2sB34Zrjrp'" target="_blank" rel="noopener noreferrer" class="w-fit">
                        <Button
                            :label="trans('API documentation')"
                            iconRight="fal fa-external-link"
                        >
                        </Button>
                    </a>
                </div>

                <!-- Section: How to use your token -->
                <div class="mt-6 max-w-2xl rounded-lg border border-purple-300 bg-purple-50 p-4 text-purple-900">
                    <div class="text-sm font-semibold mb-1">{{ trans("To use the API you need a token") }}</div>
                    <ul class="list-disc pl-5 space-y-1 text-sm">
                        <li>{{ trans("Copy the full token from the popup when you generate it — it is shown only once. The shortened label in the token list is just a reference, not the token itself.") }}</li>
                        <li>{{ trans("Send it with every request as a header:") }} <code class="bg-purple-100 px-1 rounded">Authorization: Bearer &lt;your token&gt;</code></li>
                        <li>{{ trans("Base URL:") }} <code class="bg-purple-100 px-1 rounded">https://api.aiku.io</code></li>
                    </ul>
                    <Button
                        @click="emit('generateToken')"
                        :label="trans('Generate API token')"
                        icon="fal fa-key"
                        type="secondary"
                        class="mt-3"
                    />
                </div>

                <!-- Section: Sandbox -->
                <div class="mt-6 max-w-2xl rounded-lg border border-gray-300 bg-gray-50 p-4">
                    <div class="text-sm font-semibold mb-1">{{ trans("Test safely in our staging environment") }}</div>
                    <ul class="list-disc pl-5 space-y-1 text-sm text-gray-600 mb-3">
                        <li>{{ trans("Staging is a separate copy of the site where you can experiment without affecting your real data. Log in with the same email and password as here.") }}</li>
                        <li>{{ trans("Every Sunday at 03:00 UTC staging is reset with a fresh copy of production. Anything you created in staging is erased, so start your tests over after each reset.") }}</li>
                        <li>{{ trans("Production API tokens do not work in staging. Generate a separate token in staging for your tests — it will stop working at the next reset, so generate a new one each week.") }}</li>
                        <li>{{ trans("The API documentation applies to staging unchanged — the only difference is the base URL:") }} <code class="bg-gray-200 px-1 rounded">https://api.aiku-sandbox.uk</code></li>
                    </ul>
                    <a href="https://canary.aw-dropship.com/app" target="_blank" rel="noopener noreferrer" class="w-fit">
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
