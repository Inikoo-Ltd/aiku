<script setup lang="ts">
import { ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Modal from "@/Components/Utils/Modal.vue"

library.add(faTimes)

// Define props
const props = defineProps<{
    isOpen: boolean
    title?: {
        label?: string
        information?: string
    }
}>()

// Define emits
const emit = defineEmits(["close", "submit"])

// Function to close modal
const closeModal = () => {
    emit("close")
}

// Form state
const formData = ref({
    name: "",
    email: "",
    message: ""
})

// Validation state
const errors = ref({
    name: "",
    email: "",
    message: ""
})

// Form submission logic
const submitForm = () => {
    errors.value = { name: "", email: "", message: "" } // Reset errors

    if (!formData.value.name) errors.value.name = "Name is required."
    if (!formData.value.email) errors.value.email = "Email is required."
    if (!formData.value.message) errors.value.message = "Message is required."

    if (!errors.value.name && !errors.value.email && !errors.value.message) {
        emit("submit", formData.value)
        closeModal() // Close modal after submission
    }
}
</script>

<template>
    <!-- Main Modal Component -->
    <Modal :isOpen="isOpen" @onClose="closeModal" :closeButton="true" width="w-[800px]">
        
        <!-- Modal Header -->
        <div class="flex justify-between items-center border-b pb-3 px-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">
                    {{ title?.label || "Modal Title" }}
                </h2>
                <p v-if="title?.information" class="text-sm text-gray-500">
                    {{ title.information }}
                </p>
            </div>
            <button @click="closeModal" class="text-gray-500 hover:text-gray-700">
                <FontAwesomeIcon icon="fas fa-times" class="w-5 h-5" />
            </button>
        </div>

        <!-- Modal Body (Form) -->
        <div class="flex flex-col justify-between h-[500px] overflow-y-auto pb-4 px-4">
            <form @submit.prevent="submitForm">
                <!-- Name Input -->
                <div class="mb-4 mt-4">
                    <label class="block text-sm font-medium text-gray-700">Code</label>
                    <input 
                        v-model="formData.name" 
                        type="text" 
                        class="mt-1 p-2 w-full border rounded-md"
                         />
                    <p v-if="errors.name" class="text-red-500 text-sm mt-1">{{ errors.name }}</p>
                </div>

                <!-- Email Input -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Title</label>
                    <input 
                        v-model="formData.email" 
                        type="text"
                        class="mt-1 p-2 w-full border rounded-md"
                        />
                    <p v-if="errors.email" class="text-red-500 text-sm mt-1">{{ errors.email }}</p>
                </div>

               
            </form>
        </div>

        <!-- Modal Footer -->
        <div class="flex justify-end px-4 py-3 bg-gray-100 border-t">
            <button @click="closeModal" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                Cancel
            </button>
            <button @click="submitForm" class="ml-2 px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                Submit
            </button>
        </div>
        
    </Modal>
</template>
