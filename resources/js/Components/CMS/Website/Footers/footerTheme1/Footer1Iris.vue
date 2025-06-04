<script setup lang="ts">
import { getStyles } from '@/Composables/styles'
import { FieldValue } from '@/types/Website/Website/footer1'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'

import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faShieldAlt, faPlus, faTrash, faArrowSquareLeft, faTriangle } from "@fas"
import { faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn, faFacebook, faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { faBars } from '@fal'
import Image from '@/Components/Image.vue'
import { ref } from 'vue'
import { inject } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import axios from 'axios'

library.add(faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn, faShieldAlt, faBars, faPlus, faTrash, faArrowSquareLeft, faFacebook, faWhatsapp)

defineProps<{
    fieldValue?: FieldValue,
    modelValue: FieldValue
}>();

const layout = inject('layout', retinaLayoutStructure)

const isLoadingSubmit = ref(false)
const currentState = ref("")
const inputEmail = ref("")
const errorMessage = ref("")
const onSubmitSubscribe = async () => {
	isLoadingSubmit.value = true
	errorMessage.value = ""
	currentState.value = ""

	if (!layout?.iris?.website?.id) {  // If in Aiku workshop preview
		setTimeout(() => {
			inputEmail.value = ""
			currentState.value = 'success'
			isLoadingSubmit.value = false
		}, 700)
	} else {  // If in Iris or Retina
		try {
			await axios.post(
				window.origin + '/app/webhooks/subscribe-newsletter/' + layout?.iris?.shop?.id,
				{
					email: inputEmail.value,
				},
			)
			
			inputEmail.value = ""
			currentState.value = 'success'
		} catch (error) {
			currentState.value = 'error'
			errorMessage.value = error?.errors?.email || 'An error occurred while subscribing.'
		}
	
		isLoadingSubmit.value = false
	}
}
</script>

<template>
    <div id="app" class="md:mx-0 pb-12 lg:pb-24 pt-4 md:pt-8 md:px-16 text-white"
        :style="getStyles(modelValue?.container?.properties)">
        <div
            class="w-full flex flex-col md:flex-row gap-4 md:gap-8 pt-2 pb-4 md:pb-6 mb-4 md:mb-10 border-0 border-b border-solid border-gray-700">
            <div class="overflow-hidden flex-1 flex items-center justify-center md:justify-start ">
                <Image v-if="modelValue?.logo?.source" :src="modelValue?.logo?.source" :imageCover="true" :alt="modelValue?.logo?.alt"
                    :imgAttributes="modelValue?.logo?.attributes" :style="getStyles(modelValue?.logo?.properties)" />
            </div>

            <div v-if="modelValue?.email"
                class="relative group flex-1 flex justify-center md:justify-start items-center">
                <div style="font-size: 17px">{{ modelValue?.email }}</div>
            </div>

            <div v-if="modelValue?.whatsapp?.number"
                class="relative group flex-1 flex gap-x-1.5 justify-center md:justify-start items-center">
                <a :href="`https://wa.me/${modelValue?.whatsapp?.number.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(modelValue?.whatsapp?.message || '')}`"
                    class="flex gap-x-2 items-center">
<!--                  This icon cause an error-->
                   <FontAwesomeIcon class="text-[#00EE52]" icon="fab fa-whatsapp" style="font-size: 22px" />
                    WA: <span style="font-size: 17px">{{ modelValue?.whatsapp?.number }}</span>
                </a>
            </div>

            <div class="group relative flex-1 flex flex-col items-center md:items-end justify-center">
                <a v-for="phone of modelValue?.phone?.numbers" :href="`tel:${phone}`" style="font-size: 17px">
                    {{ phone }}
                </a>

                <span class="" style="font-size: 15px">{{ modelValue?.phone?.caption }}</span>
            </div>
        </div>


        <div class=" grid grid-cols-1 md:grid-cols-4 gap-3 md:gap-8">
            <!--  column 1 -->
            <div class="md:px-0 grid gap-y-3 md:gap-y-6 h-fit">
                <div class="md:px-0 grid gap-y-3 md:gap-y-6 h-fit">
                    <div class="md:px-0 grid grid-cols-1 gap-y-2 md:gap-y-6 h-fit">
                        <!-- Desktop -->
                        <section v-for="item in modelValue?.columns?.column_1?.data">
                            <div
                                class="hidden md:block grid grid-cols-1 md:cursor-default space-y-1 border-b pb-2 md:border-none">
                                <div class="flex text-xl font-semibold w-fit leading-6">
                                    <div v-html="item.name" />
                                </div>

                                <div>
                                    <ul class="hidden md:block space-y-3">
                                        <li v-for="link in item.data" class="flex w-full items-center gap-2">
                                            <div class="text-sm block">
                                                <div v-html="link.name" />
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Mobile  -->
                            <div class="block md:hidden">
                                <Disclosure v-slot="{ open }" class="m-2">
                                    <div :class="open ? 'bg-[rgba(240,240,240,0.15)] rounded' : ''">
                                        <DisclosureButton
                                            class="p-3 pb-0 md:p-0 transition-all flex justify-between cursor-default  w-full">
                                            <div class="flex justify-between w-full">
                                                <span class="mb-0 pl-0 md:pl-[2.2rem] text-xl font-semibold leading-6">
                                                    <div v-html="item.name"></div>
                                                </span>
                                                <div>
                                                    <FontAwesomeIcon :icon="faTriangle"
                                                        :class="['w-2 h-2 transition-transform', open ? 'rotate-180' : '']" />
                                                </div>
                                            </div>
                                        </DisclosureButton>

                                        <DisclosurePanel class="p-3 md:p-0 transition-all cursor-default w-full">
                                            <ul class="mt-0 block space-y-4 pl-4 md:pl-[2.2rem]"
                                                style="margin-top: 0">
                                                <li v-for="menu of item.data" :key="menu.name"
                                                    class="flex items-center text-sm">
                                                    <div v-html="menu.name"></div>
                                                </li>
                                            </ul>
                                        </DisclosurePanel>
                                    </div>
                                </Disclosure>
                            </div>
                        </section>
                    </div>
                </div>

            </div>

            <!--    column 2 -->
            <div class="md:px-0 grid gap-y-3 md:gap-y-6 h-fit">
                <div class="md:px-0 grid gap-y-3 md:gap-y-6 h-fit">
                    <div class="md:px-0 grid grid-cols-1 gap-y-2 md:gap-y-6 h-fit">
                        <!-- Desktop -->
                        <section v-for="item in modelValue?.columns?.column_2?.data">
                            <div
                                class="hidden md:block grid grid-cols-1 md:cursor-default space-y-1 border-b pb-2 md:border-none">
                                <div class="flex text-xl font-semibold w-fit leading-6">
                                    <div v-html="item.name" />
                                </div>

                                <div>
                                    <ul class="hidden md:block space-y-3">
                                        <li v-for="link in item.data" class="flex w-full items-center gap-2">
                                            <div class="text-sm block">
                                                <div v-html="link.name" />
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Mobile  -->
                            <div class="block md:hidden">
                                <Disclosure v-slot="{ open }" class="m-2">
                                    <div :class="open ? 'bg-[rgba(240,240,240,0.15)] rounded' : ''">
                                        <DisclosureButton
                                            class="p-3 pb-0 md:p-0 transition-all flex justify-between cursor-default  w-full">
                                            <div class="flex justify-between w-full">
                                                <span class="mb-0 pl-0 md:pl-[2.2rem] text-xl font-semibold leading-6">
                                                    <div v-html="item.name"></div>
                                                </span>
                                                <div>
                                                    <FontAwesomeIcon :icon="faTriangle"
                                                        :class="['w-2 h-2 transition-transform', open ? 'rotate-180' : '']" />
                                                </div>
                                            </div>
                                        </DisclosureButton>

                                        <DisclosurePanel class="p-3 md:p-0 transition-all cursor-default w-full">
                                            <ul class="mt-0 block space-y-4 pl-4 md:pl-[2.2rem]"
                                                style="margin-top: 0">
                                                <li v-for="menu of item.data" :key="menu.name"
                                                    class="flex items-center text-sm">
                                                    <div v-html="menu.name"></div>
                                                </li>
                                            </ul>
                                        </DisclosurePanel>
                                    </div>
                                </Disclosure>
                            </div>
                        </section>
                    </div>
                </div>

            </div>

            <!--    column 3 -->
            <div class="md:px-0 grid gap-y-3 md:gap-y-6 h-fit">
                <div class="md:px-0 grid gap-y-3 md:gap-y-6 h-fit">
                    <div class="md:px-0 grid grid-cols-1 gap-y-2 md:gap-y-6 h-fit">
                        <!-- Desktop -->
                        <section v-for="item in modelValue?.columns?.column_3?.data">
                            <div
                                class="hidden md:block grid grid-cols-1 md:cursor-default space-y-1 border-b pb-2 md:border-none">
                                <div class="flex text-xl font-semibold w-fit leading-6">
                                    <div v-html="item.name" />
                                </div>

                                <div>
                                    <ul class="hidden md:block space-y-3">
                                        <li v-for="link in item.data" class="flex w-full items-center gap-2">
                                            <div class="text-sm block">
                                                <div v-html="link.name" />
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <!-- Mobile  -->
                            <div class="block md:hidden">
                                <Disclosure v-slot="{ open }" class="m-2">
                                    <div :class="open ? 'bg-[rgba(240,240,240,0.15)] rounded' : ''">
                                        <DisclosureButton
                                            class="p-3 pb-0 md:p-0 transition-all flex justify-between cursor-default  w-full">
                                            <div class="flex justify-between w-full">
                                                <span class="mb-0 pl-0 md:pl-[2.2rem] text-xl font-semibold leading-6">
                                                    <div v-html="item.name"></div>
                                                </span>
                                                <div>
                                                    <FontAwesomeIcon :icon="faTriangle"
                                                        :class="['w-2 h-2 transition-transform', open ? 'rotate-180' : '']" />
                                                </div>
                                            </div>
                                        </DisclosureButton>

                                        <DisclosurePanel class="p-3 md:p-0 transition-all cursor-default w-full">
                                            <ul class="mt-0 block space-y-4 pl-4 md:pl-[2.2rem]"
                                                style="margin-top: 0">
                                                <li v-for="menu of item.data" :key="menu.name"
                                                    class="flex items-center text-sm">
                                                    <div v-html="menu.name"></div>
                                                </li>
                                            </ul>
                                        </DisclosurePanel>
                                    </div>
                                </Disclosure>
                            </div>
                        </section>

                        <!-- Subscribe column -->
                        <!-- <div class="mt-10 xl:mt-0">
                            <h3 class="text-sm/6 font-semibold text-white">Subscribe to our newsletter</h3>
                            <p class="mt-2 text-sm/6 text-gray-300">The latest news, articles, and resources, sent to your inbox weekly.</p>
                            <form class="mt-6 sm:flex sm:max-w-md">
                                <label for="email-address" class="sr-only">Email address</label>
                                <input type="email" name="email-address" id="email-address" autocomplete="email" required="" class="w-full min-w-0 rounded-md bg-white/5 px-3 py-1.5 text-base text-white outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-500 sm:w-28 sm:text-sm/6" placeholder="First name" />
                                <input type="email" name="email-address" id="email-address" autocomplete="email" required="" class="w-full min-w-0 rounded-md bg-white/5 px-3 py-1.5 text-base text-white outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-500 sm:w-64 sm:text-sm/6 ml-2" placeholder="Email" />
                            </form>
                            <div class="mt-4 sm:mt-2 sm:shrink-0 w-full max-w-96 pr-2">
                                <button type="submit" class="flex w-full items-center justify-center rounded-md bg-gray-200 text-gray-700 px-3 py-2 text-sm font-semibold shadow-xs hover:bg-indigo-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">Subscribe</button>
                            </div>
                        </div> -->
                    </div>
                </div>

            </div>

            <!--  column 4 -->
            <div class="flex flex-col flex-col-reverse gap-y-6 md:block">
                <div>
                    <address
                        class="mt-10 md:mt-0 not-italic mb-4 text-center md:text-left text-xs md:text-sm text-gray-300">
                        <div v-html="modelValue?.columns.column_4.data.textBox1"></div>
                    </address>

                    <div class="flex justify-center gap-x-8 text-gray-300 md:block">
                        <div v-html="modelValue?.columns.column_4.data.textBox2"></div>
                    </div>

                    <div class="w-full mt-8">
                        <div v-html="modelValue?.paymentData.label"></div>
                    </div>

                    <div class="flex flex-col items-center gap-y-6 mt-4">
                            <div v-for="payment of modelValue.paymentData.data" :key="payment.key">
                                <img :src="payment.image" :alt="payment.alt" class="h-auto max-h-6 md:max-h-8 max-w-full w-full object-contain">
                            </div>
                        </div>
                </div>
            </div>
        </div>

        <!-- Subscribe down -->
        <div v-if="modelValue?.subscribe?.is_show"
            class="mt-16 border-t border-white/10 px-8 md:px-0 pt-8 md:mt-8 flex flex-col md:flex-row items-center md:justify-between">
            <div class="w-fit text-center md:text-left ">
                <h3 class="text-sm/6 font-semibold text-white" v-html="modelValue.subscribe?.headline ?? 'Subscribe to our newsletter'"></h3>
                <p class="mt-2 text-sm/6 text-gray-300"  v-html="modelValue.subscribe?.description ?? 'The latest news, articles, and resources, sent to your inbox weekly.'"></p>
            </div>
            
            <form xsubmit.prevent="() => onSubmitSubscribe()" class="w-full max-w-md md:w-fit mt-6 sm:flex sm:max-w-md lg:mt-0 ">
                <label for="email-address" class="sr-only">Email address</label>
                <input
                    type="email"
                    name="email-address"
                    id="email-address"
                    autocomplete="email"
                    required
                    class="w-full min-w-0 rounded-md bg-white/5 px-3 py-1.5 text-base text-white outline outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-500 md:w-56 md:text-sm/6"
                    :placeholder="modelValue?.subscribe?.placeholder ?? 'Enter your email'"
                />
                <div class="mt-4 sm:ml-4 sm:mt-0 sm:shrink-0">
                    <button type="submit" class="flex w-full items-center justify-center rounded-md bg-indigo-500 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">Subscribe</button>
                </div>
            </form>
        </div>

        <div
            class="mt-8 w-full border-0 border-t border-solid border-white/10 flex flex-col md:flex-row-reverse justify-between pt-6 items-center gap-y-8">
            <div class="grid gap-y-2 text-center md:text-left">
                <h2 style="margin-bottom: 0; font-size: inherit; font-weight: inherit"
                    class="hidden text-center tracking-wider">
                    <div v-html="modelValue?.columns.column_4.data.textBox4"></div>
                </h2>

                <div v-if="modelValue?.socialMedia?.length" class="flex gap-x-6 justify-center">
                    <a v-for="socmed of modelValue?.socialMedia" target="_blank" :href="socmed.link">
                        <FontAwesomeIcon :icon="socmed.icon" class="text-4xl md:text-2xl"></FontAwesomeIcon>
                    </a>
                </div>
            </div>

            <div id="footer_copyright"
                class="text-[13px] leading-5 md:text-[12px] text-center md:w-fit mx-auto md:mx-0">
                <div v-html="modelValue?.copyright"></div>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
.editor-class ul {
    margin-left: 0rem;
    margin-top: 0.5rem;
    list-style-position: outside;
}
</style>