<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { usePage } from "@inertiajs/vue3"
import { ref } from 'vue'
import { Dialog, DialogPanel } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes, faBars, faChevronDown } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faTimes, faBars, faChevronDown)

const grpDomain = usePage().props.grpDomain

const isMobileMenuOpen = ref(false)

const dropdownCompanies = [
    { name: 'About us', href: '#', description: 'Learn more about our company values and mission to empower others' },
    { name: 'Careers', href: '#', description: 'Looking for you next career opportunity? See all of our open positions' },
    {
        name: 'Support',
        href: '#',
        description: 'Get in touch with our dedicated support team or reach out on our community forums',
    },
    { name: 'Blog', href: '#', description: 'Read our latest announcements and get perspectives from our team' },
]

</script>

<template>
    <header class="bg-white absolute inset-x-0 top-0 z-50">
        <nav class="mx-auto flex max-w-7xl items-center justify-between p-3 lg:px-8" aria-label="Global">
            <!-- Logo: Aiku -->
            <div class="flex lg:flex-1">
                <a href="#" class="-m-1.5 p-1.5">
                    <span class="sr-only">Aiku</span>
                    <img class="h-8 w-auto" src="/art/logo.svg" alt="" />
                </a>
            </div>

            <!-- <div class="flex lg:hidden">
                <button type="button" class="-m-2.5 inline-flex items-center justify-center rounded-md p-2.5 text-gray-700"
                    @click="isMobileMenuOpen = true">
                    <span class="sr-only">Open main menu</span>
                    <FontAwesomeIcon icon='fal fa-bars' class='' aria-hidden='true' />
                </button>
            </div> -->

            <!-- Page list -->
            <!-- <PopoverGroup class="hidden lg:flex lg:gap-x-12">
                <a href="#" class="text-sm font-semibold leading-6 text-gray-700">Features</a>
                <a href="#" class="text-sm font-semibold leading-6 text-gray-700">Marketplace</a>

                <Popover class="relative">
                    <PopoverButton class="flex items-center gap-x-1 text-sm font-semibold leading-6 text-gray-700">
                        Company
                        <FontAwesomeIcon icon='fal fa-chevron-down' class='h-3 aspect-square text-indigo-600' fixed-width aria-hidden='true' />
                    </PopoverButton>

                    <transition enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 translate-y-1" enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 translate-y-1">
                        <PopoverPanel
                            class="absolute -left-8 top-full z-10 mt-3 w-96 rounded-xl bg-white p-4 shadow-lg ring-1 ring-gray-900/5">
                            <div v-for="company in dropdownCompanies" :key="company.name" class="relative rounded-lg p-4 hover:bg-gray-50">
                                <a :href="company.href" class="block text-sm font-semibold leading-6 text-gray-700">
                                    {{ company.name }}
                                    <span class="absolute inset-0" />
                                </a>
                                <p class="mt-1 text-sm leading-6 text-gray-600">{{ company.description }}</p>
                            </div>
                        </PopoverPanel>
                    </transition>
                </Popover>
            </PopoverGroup> -->

            <!-- <div class="hidden lg:flex lg:flex-1 lg:justify-end">
                <a :href="grpDomain" class="bg-indigo-600 px-3 py-2 rounded-sm text-sm font-semibold leading-none text-white">
                    Log in
                </a>
            </div> -->
        </nav>

        <!-- Mobile: Right sidebar -->
        <Dialog as="div" class="lg:hidden" @close="isMobileMenuOpen = false" :open="isMobileMenuOpen">
            <div class="fixed inset-0 z-50" />
            <DialogPanel
                class="fixed inset-y-0 right-0 z-50 w-full overflow-y-auto bg-white px-6 py-6 sm:max-w-sm sm:ring-1 sm:ring-gray-900/10">
                <div class="flex items-center justify-between">
                    <a href="#" class="-m-1.5 p-1.5">
                        <span class="sr-only">Your Company</span>
                        <img class="h-8 w-auto" src="/art/logo.svg" alt="" />
                    </a>
                    <button type="button" class="-m-2.5 rounded-md p-2.5 text-gray-700" @click="isMobileMenuOpen = false">
                        <span class="sr-only">Close menu</span>
                        <FontAwesomeIcon icon='fal fa-times' class='h-6 aspect-square' fixed-width aria-hidden='true' />
                    </button>
                </div>
                <div class="mt-6 flow-root">
                    <div class="-my-6 divide-y divide-gray-500/10">
                        <div class="space-y-2 py-6">
                            <a href="#" class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-700 hover:bg-gray-50">
                                Home
                            </a>
                        </div>
                        
                        <div class="py-6">
                            <a :href="grpDomain"
                                class="bg-indigo-600 rounded-sm px-3 py-1.5 font-semibold leading-none text-white hover:bg-indigo-700">
                                {{ trans('Log in') }}
                            </a>
                        </div>
                    </div>
                </div>
            </DialogPanel>
        </Dialog>
    </header>
</template>
