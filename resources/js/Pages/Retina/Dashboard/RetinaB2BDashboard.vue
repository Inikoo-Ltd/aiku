<script setup lang="ts">
import { ref, computed, onMounted, inject } from 'vue'
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faUser, faBuilding, faEnvelope, faPhone, faTags, faMedal as fasMedal, faGlobeEurope, faIslandTropical, faIdCard } from "@fas"
import { faMedal, faPencil } from "@fal"
import { faMedal as fadMedal } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faXmark } from "@fortawesome/free-solid-svg-icons";
import { Link, Deferred } from "@inertiajs/vue3";
import B2BDashboardInsights from "@/Components/Retina/Dashboard/B2BDashboardInsights.vue"
import WhatsAppDashboardOptIn, { type WhatsappOptInData } from "@/Components/Retina/Dashboard/WhatsAppDashboardOptIn.vue"
import RangeAdvisorDashboardCard from "@/Components/Retina/Dashboard/RangeAdvisorDashboardCard.vue"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { textReplaceVariables } from "@/Composables/Workshop"

library.add(faUser, faMedal, faPencil, fasMedal, fadMedal,faBuilding, faEnvelope, faPhone, faXmark, faTags, faGlobeEurope, faIslandTropical, faIdCard)

const props = defineProps<{
    data: {}
    welcome_message: string
    insights?: Record<string, any>
    whatsapp_optin?: WhatsappOptInData | null
}>()
const layout = inject('layout', retinaLayoutStructure)

const showBanner = ref(false);

const userCustomerTags = computed(() => {
    return props.data?.customer?.tags?.filter((tag: any) => tag.scope === 'User Customer') || []
})

const hasTags = computed(() => userCustomerTags.value.length > 0)

const hasWhatsappCard = computed(() => !!props.whatsapp_optin)
const hasRangeAdvisorCard = computed(() => !!layout.iris?.shopping_assistant)

/* onMounted(() => {
    showBanner.value = !hasTags.value
}) */
</script>

<template>
    <div class="px-6 pb-12 pt-10 lg:px-14">
        <div v-if="welcome_message" class="mb-4" v-html="textReplaceVariables(welcome_message, layout.iris_variables)"></div>

        <div class="mb-6 min-h-[3.75rem]">
            <div id="retina-dashboard-heading"></div>
            <div v-if="!insights" aria-hidden="true">
                <div class="skeleton h-8 w-1/3 rounded" />
                <div class="skeleton mt-1 h-6 w-1/2 rounded" />
            </div>
        </div>

        <div class="mb-8 grid grid-cols-1 items-start gap-4 lg:grid-cols-5">
            <div
                v-if="data?.customer"
                class="rounded-lg border px-4 py-3"
                :class="[
                    layout.offer_data?.type === 'gr' ? 'bg-yellow-50/30 border-yellow-300' : 'bg-gray-50 border-gray-200',
                    hasWhatsappCard === hasRangeAdvisorCard ? 'lg:col-span-5' : 'lg:col-span-2',
                ]"
            >
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                    <h2 class="text-sm font-semibold text-gray-900">{{ ctrans("Customer Information") }}</h2>
                    <Link :href="route('retina.sysadmin.settings.edit')" class="whitespace-nowrap text-xs text-gray-500 underline hover:text-gray-700">
                        <FontAwesomeIcon icon="fal fa-pencil" class="opacity-80" fixed-width aria-hidden="true" />
                        {{ ctrans("Edit information") }}
                    </Link>
                </div>
                <ul class="mt-2 flex flex-wrap items-center gap-x-8 gap-y-2 text-sm">
                <li v-if="data.customer.contact_name" class="flex min-w-0 items-center gap-2">
                    <FontAwesomeIcon icon="fas fa-user" class="size-4 shrink-0 text-gray-500" v-tooltip="ctrans('Contact Name')" fixed-width />
                    <span class="min-w-0 break-words text-gray-900">{{ data.customer.contact_name }}</span>
                </li>
                <li v-if="data.customer.company_name" class="flex min-w-0 items-center gap-2">
                    <FontAwesomeIcon icon="fas fa-building" class="size-4 shrink-0 text-gray-500" v-tooltip="ctrans('Company Name')" fixed-width />
                    <span class="min-w-0 break-words text-gray-900">{{ data.customer.company_name }}</span>
                </li>
                <li v-if="data.customer.email" class="flex min-w-0 items-center gap-2">
                    <FontAwesomeIcon icon="fas fa-envelope" class="size-4 shrink-0 text-gray-500" v-tooltip="ctrans('Email')" fixed-width />
                    <span class="min-w-0 break-all text-gray-900">{{ data.customer.email }}</span>
                </li>
                <li v-if="data.customer.phone" class="flex min-w-0 items-center gap-2">
                    <FontAwesomeIcon icon="fas fa-phone" class="size-4 shrink-0 text-gray-500" v-tooltip="ctrans('Phone')" fixed-width />
                    <span class="min-w-0 break-words text-gray-900">{{ data.customer.phone }}</span>
                </li>
                <li v-if="userCustomerTags.length > 0" class="flex min-w-0 items-center gap-2">
                    <FontAwesomeIcon icon="fas fa-tags" class="size-4 shrink-0 text-gray-500" v-tooltip="ctrans('Interests')" fixed-width />
                    <span class="flex flex-wrap items-center gap-1.5">
                        <span
                            v-for="tag in userCustomerTags"
                            :key="tag.id"
                            class="rounded-full border border-green-200 bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700"
                        >
                            {{ tag.name }}
                        </span>
                    </span>
                </li>
                <li v-if="data.customer.identity_document_number" class="flex min-w-0 items-center gap-2">
                    <FontAwesomeIcon icon="fas fa-id-card" class="size-4 shrink-0 text-gray-500" v-tooltip="data.customer.identity_document_number?.label" fixed-width />
                    <span class="min-w-0 break-words text-gray-900">{{ data.customer.identity_document_number?.number }}</span>
                    <span class="text-xs text-gray-400">{{ data.customer.identity_document_number?.label }}</span>
                </li>
                <li v-if="data.customer.identity_document_number_alt" class="flex min-w-0 items-center gap-2">
                    <FontAwesomeIcon icon="fas fa-id-card" class="size-4 shrink-0 text-gray-500" v-tooltip="data.customer.identity_document_number_alt?.label" fixed-width />
                    <span class="min-w-0 break-words text-gray-900">{{ data.customer.identity_document_number_alt?.number }}</span>
                    <span class="text-xs text-gray-400">{{ data.customer.identity_document_number_alt?.label }}</span>
                </li>
                </ul>
            </div>

            <WhatsAppDashboardOptIn v-if="whatsapp_optin" :data="whatsapp_optin" class="lg:col-span-3" />

            <RangeAdvisorDashboardCard v-if="hasRangeAdvisorCard" :class="hasWhatsappCard ? 'lg:col-span-2' : 'lg:col-span-3'" />
        </div>

        <!-- <div>
            <h1 class="text-4xl mb-4">{{ ctrans("Hello") }}, <span class="font-bold">{{ data?.customer?.contact_name }}</span>!</h1>
            <p>
                {{ ctrans("Welcome to the E-commerce dashboard. Here you can manage your business-to-business operations.") }}
            </p>
        </div> -->
        <Deferred data="insights">
            <template #fallback>
                <div class="space-y-6" aria-hidden="true">
                    <div class="h-10 skeleton rounded-lg" />
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <div class="h-72 skeleton rounded-lg lg:col-span-2" />
                        <div class="h-72 skeleton rounded-lg" />
                    </div>
                </div>
            </template>
            <B2BDashboardInsights v-if="insights" :insights="insights" heading-target="#retina-dashboard-heading" />
        </Deferred>
    </div>

    <div v-if="showBanner" class="absolute inset-x-0 bottom-0">
        <div class="flex items-center gap-x-6 bg-yellow-600 px-6 py-2.5 sm:px-3.5 sm:before:flex-1 rounded-b-md">
            <p class="truncate text-sm/6 text-white">
                <Link :href="route('retina.sysadmin.settings.edit', { section: 1 })" class="underline font-semibold">
                    {{ ctrans("Help us personalize your experience!") }}
                </Link>
                <span class="mx-2">—</span>
                {{ ctrans("Please fill in your interests to get relevant offers and recommendations.") }}
            </p>
            <div class="flex flex-1 justify-end">
                <button type="button" @click="showBanner = false" class="-m-3 p-3 focus-visible:-outline-offset-4">
                    <span class="sr-only">Dismiss</span>
                    <FontAwesomeIcon icon="fa-xmark" class="text-white" fixed-width />
                </button>
            </div>
        </div>
    </div>
</template>
