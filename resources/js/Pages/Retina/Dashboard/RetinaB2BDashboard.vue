<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faUser, faBuilding, faEnvelope, faPhone, faTags } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import EmailSubscribetion from '@/Components/EmailSubscribetion.vue'
import { faXmark } from "@fortawesome/free-solid-svg-icons";
import { Link } from "@inertiajs/vue3";

library.add(faUser, faBuilding, faEnvelope, faPhone, faXmark, faTags)

const props = defineProps<{
    data: {}
}>()

console.log('RetinaB2BDashboard', props)

const showBanner = ref(true);

const userCustomerTags = computed(() => {
    return props.data?.customer?.tags?.filter((tag: any) => tag.scope === 'User Customer') || []
})

const hasTags = computed(() => userCustomerTags.value.length > 0)

onMounted(() => {
    showBanner.value = !hasTags.value
})
</script>

<template>
    <div class="p-8">
        <!-- Customer Contact Information -->
        <div v-if="data?.customer" class="mb-8 p-4 bg-gray-50 rounded-lg border border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">{{ trans("Customer Information") }}</h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column: Customer Information -->
                <div class="space-y-3 text-sm">
                    <div v-if="data.customer.contact_name" class="flex items-center">
                        <FontAwesomeIcon
                            icon="fas fa-user"
                            class="text-gray-600 mr-2 w-4 h-4"
                            v-tooltip="trans('Contact Name')"
                        />
                        <span class="text-gray-900">{{ data.customer.contact_name }}</span>
                    </div>
                    <div v-if="data.customer.company_name" class="flex items-center">
                        <FontAwesomeIcon
                            icon="fas fa-building"
                            class="text-gray-600 mr-2 w-4 h-4"
                            v-tooltip="trans('Company Name')"
                        />
                        <span class="text-gray-900">{{ data.customer.company_name }}</span>
                    </div>
                    <div v-if="data.customer.email" class="flex items-center">
                        <FontAwesomeIcon
                            icon="fas fa-envelope"
                            class="text-gray-600 mr-2 w-4 h-4"
                            v-tooltip="trans('Email')"
                        />
                        <span class="text-gray-900">{{ data.customer.email }}</span>
                    </div>
                    <div v-if="data.customer.phone" class="flex items-center">
                        <FontAwesomeIcon
                            icon="fas fa-phone"
                            class="text-gray-600 mr-2 w-4 h-4"
                            v-tooltip="trans('Phone')"
                        />
                        <span class="text-gray-900">{{ data.customer.phone }}</span>
                    </div>
                    <div v-if="userCustomerTags.length > 0" class="flex items-center">
                        <FontAwesomeIcon
                            icon="fas fa-tags"
                            class="text-gray-600 mr-2 w-4 h-4"
                            v-tooltip="trans('Interests')"
                        />
                        <div class="flex items-center gap-2 w-full">
                            <span
                                v-for="tag in userCustomerTags"
                                :key="tag.id"
                                class="bg-green-50 text-green-700 border-green-200 hover:bg-green-100 px-2 py-0.5 rounded-full text-xs font-medium border transition-colors duration-200 ease-in-out"
                            >
                                {{ tag.name }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Email Subscriptions -->
                <!-- <div class="flex justify-start lg:justify-end">
                    <EmailSubscribetion
                        v-if="data?.customer?.email_subscriptions"
                        :emailSubscriptions="data.customer.email_subscriptions"
                        containerClass="p-3 bg-white rounded-md border border-gray-200 w-full max-w-sm"
                    />
                </div> -->
            </div>
        </div>

        <div>
            <h1 class="text-4xl mb-4">{{ trans("Hello") }}, <span class="font-bold">{{ data?.customer?.contact_name }}</span>!</h1>
            <p>
                {{ trans("Welcome to the E-commerce dashboard. Here you can manage your business-to-business operations.") }}
            </p>
        </div>

        <!-- resources/js/Pages/Retina/Dashboard/RetinaB2BDashboard.vue
        <pre class="bg-yellow-100">{{ data }}</pre> -->

    </div>

    <div v-if="showBanner" class="absolute inset-x-0 bottom-0">
        <div class="flex items-center gap-x-6 bg-yellow-600 px-6 py-2.5 sm:px-3.5 sm:before:flex-1 rounded-b-md">
            <p class="truncate text-sm/6 text-white">
                <Link :href="route('retina.sysadmin.settings.edit', { section: 1 })" class="underline font-semibold">
                    {{ trans("Help us personalize your experience!") }}
                </Link>
                <span class="mx-2">—</span>
                {{ trans("Please fill in your interests to get relevant offers and recommendations.") }}
            </p>
            <div class="flex flex-1 justify-end">
                <button type="button" @click="showBanner = false" class="-m-3 p-3 focus-visible:-outline-offset-4">
                    <span class="sr-only">Dismiss</span>
                    <FontAwesomeIcon icon="fa-xmark" class="text-white" />
                </button>
            </div>
        </div>
    </div>
</template>
