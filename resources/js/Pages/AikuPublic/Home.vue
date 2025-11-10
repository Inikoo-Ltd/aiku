<script setup>

import { faCheck, faPlus, faMinus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Head } from '@inertiajs/vue3'
import { faGithub } from '@fortawesome/free-brands-svg-icons'
library.add(faCheck, faPlus, faMinus, faGithub)

const features = [
    {
        name: 'Customized Configurations',
        description: 'Tailor your ERP system to fit your unique requirements.'
    },
    {
        name: 'Robust Security Measures',
        description: 'Rest easy knowing your data is protected by our advanced security protocols.'
    },
    {
        name: '24/7 Accessibility',
        description: 'Access your ERP data anytime, anywhere for seamless business operations.'
    },
    {
        name: 'Data Integrity Assurance',
        description: 'Ensure the accuracy and integrity of your business data with our comprehensive management solutions.'
    },
    {
        name: 'Efficient Workflow Management',
        description: 'Streamline your business processes with our intuitive workflow tools.'
    },
    {
        name: 'Seamless Integration',
        description: 'Effortlessly integrate your ERP software with existing systems for enhanced efficiency.'
    },
];



</script>

<template layout="AikuPublic">
    <Head title="Free ERP Online Software" />
    <div class="bg-white pb-16">
        <!-- Hero section -->
        <div class="relative isolate overflow-hidden bg-gray-900 pb-16 pt-14 sm:pb-20">
            <img src="https://wallpapers.com/images/hd/warehouse-background-pzkv8y7kvdmmnyej.jpg" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover"
                style="filter: brightness(0.4) blur(5px)" />
            <div class="absolute inset-x-0 -top-40 -z-10 transform-gpu overflow-hidden blur-3xl sm:-top-80"
                aria-hidden="true">
                <div class="relative left-[calc(50%-11rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 rotate-[30deg] bg-gradient-to-tr from-[#ff80b5] to-[#9089fc] opacity-20 sm:left-[calc(50%-30rem)] sm:w-[72.1875rem]"
                    style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)" />
            </div>
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="mx-auto max-w-2xl py-32 sm:py-44">
                    <div class="text-center">
                        <h1 class="text-4xl font-medium tracking-tight text-white sm:text-6xl">
                            <span class="font-black">Take Control of Your Warehouse</span>
                        </h1>
                        <p class="mt-6 leading-8 text-gray-300">
                            We are building an open-source 100% free ERP online software. Aiku Warehouse Management ERP provides the comprehensive solution you need to take control.
                            Streamline operations, boost productivity, and gain real-time inventory insights with our powerful features designed to optimize every aspect of your warehouse.
                            Reduce costs, minimize errors, and enhance customer satisfaction with our user-friendly software.
                        </p>
                        <a href="https://github.com/inikoo/aiku" target="_blank" class="mt-10 flex items-center justify-center gap-x-6">

                        </a>
                    </div>
                </div>
            </div>
            <div class="absolute inset-x-0 top-[calc(100%-13rem)] -z-10 transform-gpu overflow-hidden blur-3xl sm:top-[calc(100%-30rem)]"
                aria-hidden="true">
                <div class="relative left-[calc(50%+3rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 bg-gradient-to-tr from-[#ff80b5] to-[#9089fc] opacity-20 sm:left-[calc(50%+36rem)] sm:w-[72.1875rem]"
                    style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)" />
            </div>
        </div>

        <!-- Feature section -->
        <div class="mt-32">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="mx-auto max-w-2xl sm:text-center">
                    <h2 class="text-base font-semibold leading-7 text-indigo-600">All-Inclusive Solutions</h2>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
                        End-to-End ERP Solutions: Your Answer to Streamlined Operations
                    </p>
                    <p class="mt-6 text-lg leading-8 text-gray-600">
                        Simplify your business processes effortlessly. Our comprehensive ERP software covers everything from inventory management to logistics, guaranteeing smooth operations for your enterprise.
                    </p>
                </div>
            </div>
            <div class="relative overflow-hidden pt-16">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <img src="assets/Aiku Page.png"
                        alt="App screenshot" class="mb-[-7%] rounded-xl shadow-2xl ring-1 ring-gray-900/10"
                        width="100%" height="100%" />
                    <div class="relative" aria-hidden="true">
                        <div class="absolute -inset-x-20 bottom-0 bg-gradient-to-t from-white pt-[7%]" />
                    </div>
                </div>
            </div>
            
        </div>

        <!-- Testimonial section -->
        <div class="relative z-10 mt-16 bg-gray-900 pb-20 sm:mt-24 sm:pb-24 xl:pb-0">
            <div class="absolute inset-0 overflow-hidden" aria-hidden="true">
                <div class="absolute left-[calc(50%-19rem)] top-[calc(50%-36rem)] transform-gpu blur-3xl">
                    <div class="aspect-[1097/1023] w-[68.5625rem] bg-gradient-to-r from-[#ff4694] to-[#776fff] opacity-25"
                        style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)" />
                </div>
            </div>
            <div
                class="mx-auto flex max-w-7xl flex-col items-center gap-x-8 gap-y-10 px-6 sm:gap-y-8 lg:px-8 xl:flex-row xl:items-stretch">
                <div class="w-full max-w-2xl xl:max-w-none xl:flex-auto xl:px-16 xl:py-24">
                    <figure class="relative isolate pt-6 sm:pt-12">
                        <svg viewBox="0 0 162 128" fill="none" aria-hidden="true"
                            class="absolute left-0 top-0 -z-10 h-32 stroke-white/20">
                            <path id="b56e9dab-6ccb-4d32-ad02-6b4bb5d9bbeb"
                                d="M65.5697 118.507L65.8918 118.89C68.9503 116.314 71.367 113.253 73.1386 109.71C74.9162 106.155 75.8027 102.28 75.8027 98.0919C75.8027 94.237 75.16 90.6155 73.8708 87.2314C72.5851 83.8565 70.8137 80.9533 68.553 78.5292C66.4529 76.1079 63.9476 74.2482 61.0407 72.9536C58.2795 71.4949 55.276 70.767 52.0386 70.767C48.9935 70.767 46.4686 71.1668 44.4872 71.9924L44.4799 71.9955L44.4726 71.9988C42.7101 72.7999 41.1035 73.6831 39.6544 74.6492C38.2407 75.5916 36.8279 76.455 35.4159 77.2394L35.4047 77.2457L35.3938 77.2525C34.2318 77.9787 32.6713 78.3634 30.6736 78.3634C29.0405 78.3634 27.5131 77.2868 26.1274 74.8257C24.7483 72.2185 24.0519 69.2166 24.0519 65.8071C24.0519 60.0311 25.3782 54.4081 28.0373 48.9335C30.703 43.4454 34.3114 38.345 38.8667 33.6325C43.5812 28.761 49.0045 24.5159 55.1389 20.8979C60.1667 18.0071 65.4966 15.6179 71.1291 13.7305C73.8626 12.8145 75.8027 10.2968 75.8027 7.38572C75.8027 3.6497 72.6341 0.62247 68.8814 1.1527C61.1635 2.2432 53.7398 4.41426 46.6119 7.66522C37.5369 11.6459 29.5729 17.0612 22.7236 23.9105C16.0322 30.6019 10.618 38.4859 6.47981 47.558L6.47976 47.558L6.47682 47.5647C2.4901 56.6544 0.5 66.6148 0.5 77.4391C0.5 84.2996 1.61702 90.7679 3.85425 96.8404L3.8558 96.8445C6.08991 102.749 9.12394 108.02 12.959 112.654L12.959 112.654L12.9646 112.661C16.8027 117.138 21.2829 120.739 26.4034 123.459L26.4033 123.459L26.4144 123.465C31.5505 126.033 37.0873 127.316 43.0178 127.316C47.5035 127.316 51.6783 126.595 55.5376 125.148L55.5376 125.148L55.5477 125.144C59.5516 123.542 63.0052 121.456 65.9019 118.881L65.5697 118.507Z" />
                            <use href="#b56e9dab-6ccb-4d32-ad02-6b4bb5d9bbeb" x="86" />
                        </svg>
                        <blockquote class="text-xl font-semibold leading-8 text-white sm:text-2xl sm:leading-9">
                            <p>
                                Aiku surpassed my expectations with their exceptional warehouse service. Their flexible storage options accommodated my varying needs effortlessly.
                                Security measures were robust, providing peace of mind for my stored items.
                                The convenience of 24/7 access was invaluable for managing my inventory efficiently.
                                Overall, Aiku offers a top-tier solution for anyone seeking reliable warehouse services.
                            </p>
                        </blockquote>
                        <figcaption class="mt-8 text-base">
                            <div class="font-semibold text-white">-</div>
                        </figcaption>
                    </figure>
                </div>
            </div>
        </div>

        
        <div class="mx-auto mt-16 max-w-7xl px-6 sm:mt-20 md:mt-24 lg:px-8">
            <dl
                class="mx-auto grid max-w-2xl grid-cols-1 gap-x-6 gap-y-10 text-base leading-7 text-gray-600 sm:grid-cols-2 lg:mx-0 lg:max-w-none lg:grid-cols-3 lg:gap-x-8 lg:gap-y-16">
                <div v-for="feature in features" :key="feature.name" class="relative pl-9">
                    <dt class="inline font-semibold text-gray-900">
                        <component :is="feature.icon" class="absolute left-1 top-1 h-5 w-5 text-indigo-600"
                            aria-hidden="true" />
                        {{ feature.name }}
                    </dt>
                    {{ ' ' }}
                    <dd class="inline">{{ feature.description }}</dd>
                </div>
            </dl>
        </div>


    </div>
</template>