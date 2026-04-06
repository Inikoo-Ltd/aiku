<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import { ref } from "vue"
import { useForm } from "@inertiajs/vue3"
import { InputNumber, Checkbox, Textarea } from "primevue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"

const props = defineProps<{
    product_category_id: number
}>()

const isOpenModal = ref(false)

const form = useForm({
    reviewable_type: "ProductCategory",
    reviewable_id: props.product_category_id,
    rating: 5,
    title: "",
    message: "",
    is_verified_purchase: false,
})

const openModal = (): void => {
    form.reviewable_id = props.product_category_id
    isOpenModal.value = true
}

const closeModal = (): void => {
    isOpenModal.value = false
    form.reset("rating", "title", "message", "is_verified_purchase")
    form.rating = 5
}

const submitReview = (): void => {
    form.reviewable_type = "ProductCategory"
    form.reviewable_id = props.product_category_id

    form.post(route("grp.models.review.store"), {
        preserveScroll: true,
        onSuccess: () => {
            notify({
                title: trans("Success"),
                text: trans("Review created successfully"),
                type: "success",
            })
            closeModal()
        },
        onError: () => {
            notify({
                title: trans("Something went wrong"),
                text: trans("Failed to create review"),
                type: "error",
            })
        },
    })
}
</script>

<template>
    <div>
        <Button :label="trans('Create New Review')" icon="fas fa-star" @click="openModal" />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="closeModal">
            <div class="space-y-4 p-1">
                <h2 class="text-center text-2xl font-bold">{{ trans("Create New Review") }}</h2>

                <div class="space-y-2">
                    <label class="font-medium">{{ trans("Rating") }}</label>
                    <InputNumber v-model="form.rating" :min="1" :max="5" fluid />
                    <div v-if="form.errors.rating" class="text-sm text-red-500">{{ form.errors.rating }}</div>
                </div>

                <div class="space-y-2">
                    <label class="font-medium">{{ trans("Title") }}</label>
                    <PureInput v-model="form.title" />
                    <div v-if="form.errors.title" class="text-sm text-red-500">{{ form.errors.title }}</div>
                </div>

                <div class="space-y-2">
                    <label class="font-medium">{{ trans("Message") }}</label>
                    <Textarea v-model="form.message" rows="5" class="w-full" />
                    <div v-if="form.errors.message" class="text-sm text-red-500">{{ form.errors.message }}</div>
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox v-model="form.is_verified_purchase" binary inputId="is_verified_purchase" />
                    <label for="is_verified_purchase">{{ trans("Verified purchase") }}</label>
                </div>

                <div class="flex justify-end gap-3">
                    <Button type="cancel" @click="closeModal" />
                    <Button :label="trans('Save')" :isLoading="form.processing" :disabled="form.processing" @click="submitReview" />
                </div>
            </div>
        </Modal>
    </div>
</template>
