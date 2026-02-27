<script setup lang="ts">
import { faCheck, faPlus, faMinus, faCheckCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Head } from "@inertiajs/vue3"
import { faGithub } from "@fortawesome/free-brands-svg-icons"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
library.add(faCheck, faPlus, faMinus, faCheckCircle, faGithub)

const props = defineProps<{
	success: boolean
	message: string
	name: string
	website: string
	is_need_create_customer: string
}>()

const tiktokCode = route().queryParams?.code

const closePage = () => {
	console.log('aaaaaaa')
}
</script>

<template layout="AikuPublic">
	<Head title="Welcome to TikTok Onboarding" />
	<div class="bg-white py-16">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
			<!-- <div class="mt-8">
				<div class="bg-white shadow sm:rounded-lg">
					<div class="px-4 py-5 sm:p-6">
						<div v-if="success">
							<h3 class="text-lg font-medium text-green-600">{{ trans('Success') }}</h3>
							<p class="mt-2 text-gray-600">
								{{ trans(`Your TikTok account :name has been successfully onboarded.`, { name: name }) }}
							</p>
						</div>
						<div v-else>
							<h3 class="text-lg font-medium text-red-600">{{ trans('Error') }}</h3>
							<p class="mt-2 text-gray-600">{{ message }}</p>
						</div>
					</div>
				</div>
			</div> -->

			<!-- Sucess and not registered -->
			<div>
				<div class="text-center py-8">
					<div class=" text-7xl mb-5">
						<FontAwesomeIcon icon="fal fa-check-circle" class="text-green-500" fixed-width aria-hidden="true" />
					</div>
					
					<div class="text-green-600 text-2xl font-bold">
						Congrats!
					</div>

					<div class="text-green-600">
						Your Tiktok's app has been successfully onboarded.
					</div>

				</div>

				
				<div v-if="is_need_create_customer">
					<div class="text-center mb-4">
						Next step, select one of our warehouse that you preferred to send the products.
					</div>
					<div class="grid grid-cols-3 gap-x-8 py-4">
						<!-- Dropship UK -->
						<div class="bg-gray-100 rounded p-4">
							<div class="font-bold text-2xl text-center mb-4">
								AW Dropship UK
							</div>
							<div class="aspect-[6/3] mb-4">
								<img src="/assets/websites_logo/aw-dropship-uk.avif" alt="AW Dropship UK" />
							</div>
							<div class="flex gap-x-2">
								<a :href="'https://www.aw-dropship.com/app/registration-form?tiktok_code=' + tiktokCode" target="_blank" class="w-full">
									<Button label="Register" full type="black" key="" />
								</a>
								<a :href="'https://www.aw-dropship.com/app/login?tiktok_code=' + tiktokCode" target="_blank" class="w-full">
									<Button label="Login" full type="black" key="" />
								</a>
							</div>
						</div>
						<!-- Dropship EU -->
						<div class="bg-gray-100 rounded p-4">
							<div class="font-bold text-2xl text-center mb-4">
								AW Dropship EU
							</div>
							<div class="aspect-[6/3] mb-4">
								<img src="/assets/websites_logo/aw-dropship-eu.avif" alt="AW Dropship EU" />
							</div>
							<div class="flex gap-x-2">
								<a :href="'https://www.aw-dropship.eu/app/registration-form?tiktok_code=' + tiktokCode" target="_blank" class="w-full">
									<Button label="Register" full type="black" key="" />
								</a>
								<a :href="'https://www.aw-dropship.eu/app/login?tiktok_code=' + tiktokCode" target="_blank" class="w-full">
									<Button label="Login" full type="black" key="" />
								</a>
							</div>
						</div>
						<!-- Dropship ES -->
						<div class="bg-gray-100 rounded p-4">
							<div class="font-bold text-2xl text-center mb-4">
								AW Dropship ES
							</div>
							<div class="aspect-[6/3] mb-4">
								<img src="/assets/websites_logo/aw-dropship-es.avif" alt="AW Dropship ES" />
							</div>
							<div class="flex gap-x-2">
								<a :href="'https://www.aw-dropship.es/app/registration-form?tiktok_code=' + tiktokCode" target="_blank" class="w-full">
									<Button label="Register" full type="black" key="" />
								</a>
								<a :href="'https://www.aw-dropship.es/app/login?tiktok_code=' + tiktokCode" target="_blank" class="w-full">
									<Button label="Login" full type="black" key="" />
								</a>
							</div>
						</div>
					</div>
				</div>

				<div v-else>
					<div class="text-center">
						You can close this page and go back to your previous page.
					</div>

					<!-- <div class="mx-auto w-fit mt-2">
						<Button
							label="Close page"
							type="gray"
							@click="closePage()"
						/>
					</div> -->
				</div>

			</div>
		</div>
	</div>
</template>
