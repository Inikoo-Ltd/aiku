<script setup lang="ts">
import { faCheck, faPlus, faMinus } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Head } from "@inertiajs/vue3"
import { faGithub } from "@fortawesome/free-brands-svg-icons"
import { trans } from "laravel-vue-i18n"
library.add(faCheck, faPlus, faMinus, faGithub)

const props = defineProps<{
	success: boolean
	message: string
	name: string
}>()
console.log(props)
</script>

<template layout="AikuPublic">
	<Head title="Welcome to TikTok Onboarding" />
	<div class="bg-white pb-16">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
			<div class="mt-8">
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
			</div>
		</div>
	</div>
</template>
