<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Mon, 17 Nov 2025 16:57:42 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { inject, ref } from "vue";
import { faInfoCircle } from "@fal";
import { ctrans } from "@/Composables/useTrans";
import { useForm } from "@inertiajs/vue3";
import { library } from "@fortawesome/fontawesome-svg-core";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { notify } from "@kyvg/vue3-notification";
import axios from "axios";
import PureInputWithAddOn from "@/Components/Pure/PureInputWithAddOn.vue"

library.add(faInfoCircle);

const goNext = inject("goNext");
const cancelCreateTiktokModal = inject("cancelCreateTiktokModal");
const tiktokUserId = inject("tiktokUserId");

const isLoadingStep = ref(false)
const errors = ref({})

const props = defineProps<{
	props: {
		tiktokAuth: {
			url: any
		}
	}
}>()

const submitForm = async () => {
	isLoadingStep.value = true
	errors.value = {};

	try {
		const {data} = await axios.get(route('retina.models.dropshipping.tiktok.auth_new_check', {}));
		tiktokUserId.value = data?.platform_user_id;
		goNext();
		isLoadingStep.value = false
	} catch (err: any) {
		console.error(err)
		isLoadingStep.value = false;
		errors.value = err.response?.data?.errors;
	}
}
</script>

<template>
	<div class="flex flex-col gap-2">
		<span class="text-lg font-semibold">{{ ctrans("Authentication Settings") }}</span>
		<span class="text-sm">{{
			ctrans("This is where you need to auth your TikTok store to our system.")
		}}</span>
	</div>
	<form class="flex flex-col gap-6">
		<div class="flex items-center gap-2 w-full md:w-80">
			<a target="_blank" :href="props?.props?.tiktokAuth?.url" class="p-4 rounded bg-black text-white">
				{{ ctrans("Auth Store") }}
			</a>
			<FontAwesomeIcon
				v-tooltip="
					ctrans(
						'Requests a token from TikTok so we can sync without you entering your account details each time'
					)
				"
				icon="fal fa-info-circle"
				class="hidden md:block size-5 text-black" fixed-width />
		</div>
			<p v-if="errors?.message?.[0]" class="text-red-500">{{errors?.message?.[0]}}</p>
		<hr class="w-full border-t" />
		<div class="flex md:justify-end gap-4">
			<Button type="tertiary" size="sm" @click="cancelCreateTiktokModal">{{
				ctrans("Cancel")
			}}</Button>
			<Button size="sm" :loading="isLoadingStep" @click="submitForm">{{
				ctrans("Next")
			}}</Button>
		</div>
	</form>
</template>
