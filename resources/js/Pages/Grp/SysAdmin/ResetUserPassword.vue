<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import LayoutGrpAuth from '@/Layouts/GrpAuth.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faArrowLeft, faCheckCircle } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ref } from 'vue'
import InputError from '@/Components/InputError.vue'
library.add(faArrowLeft, faCheckCircle)


defineOptions({ layout: LayoutGrpAuth })
defineProps({
    status: {
        type: String,
    },
})

const form = useForm({
    email: '',
})

const isResetLinkSent = ref(false)

const submit = () => {
    form.post(route('grp.password.email'), {
        onSuccess: () => (console.log('ewqewq'), isResetLinkSent.value = true),
        // onError: (e) => errorForm.value = e.email
    })
}
</script>

<template>

    <Head title="Forgot Password" />
    <Link href="/login" class="absolute left-4 top-4 text-xs text-gray-600 hover:underline">
        <FontAwesomeIcon icon='fal fa-arrow-left' class='' fixed-width aria-hidden='true' />
        Back to login
    </Link>


    <!-- Section: form reset password -->
    <template v-if="!isResetLinkSent">
        <div class="text-center font-bold text-xl">Reset Password</div>
        <div class="mt-2 mb-4 text-sm text-gray-600 italic">
            We will email you a password reset link that will allow you to choose a new one.
        </div>
        <form @submit.prevent="submit" class="mt-8">
            <div>
                <label for="email" value="Email" class="font-medium text-sm">Email:</label>
                <PureInput
                    v-model="form.email"
                    id="email"
                    @update:modelValue="() => form.errors.email = ''"
                    placeholder="johndoe@gmail.com"
                    class="mt-1 block w-full"
                    type="email"
                    required
                    autofocus
                    autocomplete="email" />
                <InputError class="italic mt-2" :message="form.errors.email" />
            </div>
            <div class="flex items-center justify-center mt-8">
                <Button @click="() => submit()"
                    :loading="form.processing"
                    label="Send email reset password"
                    type="indigo" />
            </div>
        </form>
    </template>

    <!-- Section: after sent email -->
    <template v-else>
        <div class="text-center">
            <FontAwesomeIcon icon='fal fa-check-circle' class='text-green-500 text-4xl' fixed-width aria-hidden='true' />
        </div>
        <div class="text-center font-bold text-xl">Reset link sent</div>
        <div class="mt-2 mb-4 text-xs text-gray-600 italic">
            We've sent link to reset your password to {{ form.email }}. Please check email especially on spam folder.
        </div>
    </template>
</template>
