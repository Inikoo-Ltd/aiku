<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors } from 'chart.js'
import { Link, router, Head } from "@inertiajs/vue3"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {  faCheckCircle, faInfoCircle, faExclamationTriangle,faSeedling, faShare, faSpellCheck, faCheck, faTimes, faSignOutAlt, faTruck, faCheckDouble, faCross } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import CountUp from 'vue-countup-v3'
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { routeType } from '@/types/route'
import { inject, ref } from 'vue'
import Modal from '@/Components/Utils/Modal.vue'
import { notify } from '@kyvg/vue3-notification'
import InputNumber from 'primevue/inputnumber'
import Button from '@/Components/Elements/Buttons/Button.vue'

library.add(faCheckCircle, faInfoCircle, faExclamationTriangle, faSeedling, faShare, faSpellCheck, faCheck, faTimes, faSignOutAlt, faTruck, faCheckDouble, faCross)

ChartJS.register(ArcElement, Tooltip, Legend, Colors)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    currency: {
        code: string
        symbol: string
    }
    topUpData: {
        topUps: {
            label: string
            count: number
            description: string
            route: routeType
        }
    }
    balance?: string  // 0.00
}>()

const locale = inject('locale', {})


const isModalTopUpOpen = ref(false)
const amount = ref<number | null>(100)
const privateNote = ref<string>("")

const isLoading = ref(false)
const onSubmitTopUp = () => {
    router.post(route('retina.models.top_up_payment_api_point.store'), {
        amount: amount.value,
        // notes: privateNote.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => {
            isLoading.value = true
        },
        onFinish: () => {
            isLoading.value = false
        },
        onSuccess: () => {
            notify({
                title: trans("Success!"),
                text: trans("Top up request has been submitted successfully."),
                type: "error",
            })
            amount.value = null
            privateNote.value = ""
        },
        onError: () => {
            notify({
                title: trans("Something went wrong"),
                text: trans("Please try again or contact support."),
                type: "error",
            })
        }
    })
}
</script>

<template>

    <Head :title="title" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button :label="trans('Top Up Balance')" @click="() => isModalTopUpOpen = true" icon="fas fa-plus" />
        </template>
    </PageHeading>

    <div class="px-4 py-5 md:px-6 lg:px-8 ">

        <div class="grid md:grid-cols-2 gap-y-4 md:gap-y-0 gap-x-8">
            <!-- Section: Current balance -->
            <dl class="relative isolate bg-indigo-50 border border-indigo-200 rounded shadow px-4 py-5 sm:p-6 overflow-hidden grid items-center">
                <div class="-z-10 absolute  top-1/2 -translate-y-1/2 transform-gpu blur-2xl" aria-hidden="true">
                    <div class="aspect-[577/310] w-[36.0625rem] bg-gradient-to-r from-[#ff80b5] to-[#9089fc] opacity-30" style="clip-path: polygon(74.8% 41.9%, 97.2% 73.2%, 100% 34.9%, 92.5% 0.4%, 87.5% 0%, 75% 28.6%, 58.5% 54.6%, 50.1% 56.8%, 46.9% 44%, 48.3% 17.4%, 24.7% 53.9%, 0% 27.9%, 11.9% 74.2%, 24.9% 54.1%, 68.6% 100%, 74.8% 41.9%)" />
                </div>

                <dt class="text-base font-normal ">
                    {{ trans("Current balance") }}
                </dt>

                <dd class="mt-1 flex items-baseline justify-between md:block lg:flex">
                    <div class="flex items-baseline text-2xl font-semibold text-indigo-600">
                        {{ locale.currencyFormat(currency.code, balance) }}
                    </div>
                </dd>
            </dl>

            <!-- Section: previous top up -->
            <div class="space-y-4 grid md:grid-cols-2 gap-x-4">
                <div
                    class="flex  justify-between px-4 py-5 sm:p-6 rounded-lg bg-white border border-gray-300 tabular-nums col-span-2">
                    <dl >
                        <dt class="text-base font-medium text-gray-400 capitalize">
                            {{ topUpData.topUps.label }}
                        </dt>
                        <dd class="mt-2 flex justify-between gap-x-2">
                            <div
                                class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold text-org-500">
                                <!-- In Total -->
                                <div class="flex gap-x-2 items-end">
                                    <Link :href="route(topUpData.topUps.route.name)">
                                    <CountUp class="primaryLink inline-block" :endVal="topUpData.topUps.count"
                                        :duration="1.5" :scrollSpyOnce="true" :options="{
                                            formattingFn: (value: number) => locale.number(value)
                                        }" />
                                    </Link>
                                    <span class="text-sm font-medium leading-4 text-gray-500 ">
                                        {{ topUpData.topUps.description }}
                                    </span>
                                </div>
                            </div>
                        </dd>
                    </dl>
                </div>
            </div>



        </div>

        <Modal width="w-full max-w-xl" :isOpen="isModalTopUpOpen" @close="() => isModalTopUpOpen = false">
            <div class="p-6 max-w-lg mx-auto w-full">
                <div class="mb-8">
                    <h2 class="text-3xl font-bold">{{ trans("Top up Balance") }}</h2>
                    <span class="text-sm italic text-gray-400">
                        <FontAwesomeIcon icon="fal fa-info-circle" fixed-width aria-hidden="true" />
                        {{ trans("Deposit funds for various purposes such as orders payment.") }}
                    </span>
                </div>
                <div class="space-y-8">
                    <div>
                        <label for="amount" class="block font-medium mb-2">
                            {{ trans("Amount to deposit") }}
                        </label>
                        <InputNumber v-model="amount" inputId="currency-germany" mode="currency" placeholder="100"
                            :currency="currency.code" locale="en-GB" fluid />
                    </div>

                    <div class="flex gap-x-4">
                        <div @click="amount = 100"
                            :class="amount === 100 ? 'bg-indigo-500 text-white' : 'bg-white text-gray-500'"
                            class="h-12 w-fit cursor-pointer border border-gray-300 rounded-md flex items-center px-7 font-bold">
                            {{ locale.currencyFormat(currency.code, 100) }}
                        </div>
                        <div @click="amount = 200"
                            :class="amount === 200 ? 'bg-indigo-500 text-white' : 'bg-white text-gray-500'"
                            class="h-12 w-fit cursor-pointer border border-gray-300 rounded-md flex items-center px-7 font-bold">
                            {{ locale.currencyFormat(currency.code, 200) }}
                        </div>
                        <div @click="amount = 300"
                            :class="amount === 300 ? 'bg-indigo-500 text-white' : 'bg-white text-gray-500'"
                            class="h-12 w-fit cursor-pointer border border-gray-300 rounded-md flex items-center px-7 font-bold">
                            {{ locale.currencyFormat(currency.code, 300) }}
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end space-x-4">
                    <Button full @click="() => onSubmitTopUp()" :loading="isLoading" :label="trans('Submit')" />
                </div>
            </div>

        </Modal>
    </div>
</template>