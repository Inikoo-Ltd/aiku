<script setup lang="ts">
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableCustomerPlatforms from "@/Components/Tables/Grp/Org/CRM/TableCustomerPlatforms.vue"
import { Head, router } from "@inertiajs/vue3"
import { PageHeading as PageHeadingTS } from "@/types/PageHeading"
import { capitalize } from "@/Composables/capitalize"
import Modal from "@/Components/Utils/Modal.vue"
import { trans } from "laravel-vue-i18n"
import { ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"

const props = defineProps<{
	data: {}
	title: string
	pageHead: object
	tabs: {
		current: string
		navigation: {}
	}
	platforms: {
		data: any
	}
    attachRoute:{}
}>()

const isModalOpen = ref(false)
const isLoading = ref<string | boolean>(false)

function changeModal() {
	isModalOpen.value = true
}

const onCreateStore = (id : any) => {
    let param = {
        platform: id,
        ...props.attachRoute.parameters
    }
    router.post(
        route(props.attachRoute.name, param),
        {
            onStart: () => isLoading.value = true,
            onError: (error) => {
                notify({
                    title: trans('Something went wrong'),
                    text: error,
                    type: 'error',
                })
            },
            onSuccess: () => {
                isModalOpen.value = false
            },
            onFinish: () => isLoading.value = false
        }
    )
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #button-new-channel="{ action }">
			<Button
				@click="() => changeModal()"
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:iconRight="action.iconRight"
				:key="`ActionButton${action.label}${action.style}`"
				:tooltip="action.tooltip" />
		</template>
	</PageHeading>
	<TableCustomerPlatforms :data="data" />

	<Modal :isOpen="isModalOpen" @onClose="isModalOpen = false" class="w-full max-w-5xl">
		<div class="flex flex-col">
			<!-- Header -->
			<div class="text-center font-semibold text-2xl mb-4">
				{{ trans("Channel List") }}
			</div>

			<!-- Horizontal list container -->
			<div class="flex flex-wrap gap-4 justify-center">
				<!-- Loop over the data -->
				<div
					v-for="(platform, index) in platforms.data"
					:key="index"
					class="bg-gray-50 border border-gray-200 rounded-md w-72 p-4">
					<div
						class="mb-4 border-b border-gray-300 pb-4 flex gap-x-4 items-center text-xl">
						<img :src="platform.iconUrl" alt="" class="h-12" />
						<div class="flex flex-col">
							<div class="font-semibold">{{ platform.name }}</div>
							<div class="text-xs text-gray-500">({{ trans("Manage product") }})</div>
						</div>
					</div>

					<div class="w-full flex justify-end">
						<template v-if="!platform.auth?.isAuthenticated">
							<!-- Only clickable when platform is 'aiku' -->
							<div
								v-if="platform.code === 'aiku'"
								target="_blank"
								class="w-full"
								:href="platform.auth?.url">
								<Button label="Connect" type="primary" full @click="() => onCreateStore(platform.id)" />
							</div>
							<!-- Render a disabled button for other platforms -->
							<span v-else class="w-full">
								<Button label="Connect" type="primary" full disabled />
							</span>
						</template>

						<template v-else>
							<Transition name="spin-to-down">
								<div class="w-full flex justify-end gap-x-2">
									<Button
										:capitalize="false"
										:label="trans('Connected')"
										type="positive"
										icon="fal fa-check"
										size="xs"
										full
										:disabled="platform.code !== 'aiku'" />
									<Button
										:loading="isLoading === 'fetch-customers'"
										type="positive"
										icon="fal fa-users"
										size="xs"
										:disabled="platform.code !== 'aiku'" />
								</div>
							</Transition>
						</template>
					</div>
				</div>
			</div>
		</div>
	</Modal>
</template>
