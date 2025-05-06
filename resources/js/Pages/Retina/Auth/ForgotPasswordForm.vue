<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
// import LayoutRetinaAuth from '@/Layouts/RetinaShowIris.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faArrowLeft, faCheckCircle } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ref } from 'vue'
import InputError from '@/Components/InputError.vue'
import { trans } from 'laravel-vue-i18n'
library.add(faArrowLeft, faCheckCircle)


// defineOptions({ layout: LayoutRetinaAuth })
defineProps({
    back_label: {
        type: String,
    },
    instructions: {
        type: String,
    },
    status: {
        type: String,
    },
})

const form = useForm({
    email: '',
})

const isResetLinkSent = ref(false)
const submit = () => {
    form.post(route('retina.reset-password.send'), {
        onSuccess: () => isResetLinkSent.value = true
    })
}
</script>

<template>

    <Head title="Forgot Password" />
    <!-- <Link :href="route('retina.login.show')" class="absolute left-4 top-4 text-xs text-gray-600 hover:underline">
        <FontAwesomeIcon icon='fal fa-arrow-left' class='' fixed-width aria-hidden='true' />
        {{back_label}}
    </Link>

    <template v-if="!isResetLinkSent">
        <div class="text-center font-bold text-xl">Reset Password</div>
        <div class="mt-3 mb-4 text-sm text-center ">
            {{ instructions }}
        </div>
        <form @submit.prevent="submit" class="mt-8">
            <div>
                <label for="email" value="Email" class="font-medium text-sm">Email:</label>
                <PureInput
                    v-model="form.email"
                    @update:modelValue="() => form.errors.email = ''"
                    id="email"
                    placeholder="johndoe@gmail.com"
                    class="mt-1 block w-full"
                    type="email"
                    required
                    autofocus
                    autocomplete="email" />
                <InputError class="mt-2 italic" :message="form.errors.email" />
            </div>
            <div class="flex items-center justify-center mt-8">
                <Button @click="() => submit()"
                    :loading="form.processing"
                    label="Email Password Reset Link"
                    type="indigo" />
            </div>
        </form>
    </template>

<template v-else>
        <div class="text-center">
            <FontAwesomeIcon icon='fal fa-check-circle' class='text-green-500 text-4xl' fixed-width aria-hidden='true' />
        </div>
        <div class="text-center mt-2 font-bold text-xl">Reset link sent</div>
        <div class="mt-3 mb-4 text-sm text-gray-600">
            We've sent link to reset your password to {{ form.email }}. Please check email especially on spam folder.
        </div>
    </template> -->


    <!-- Header Section -->
    <div class="flex items-center justify-center bg-gray-100  px-6 py-12 lg:px-8">
    <div class="w-full max-w-md">
      <!-- Conditional Form for Reset Link -->
      <template v-if="!isResetLinkSent">
        <div class="text-center font-bold text-2xl text-gray-800">Reset Password</div>
        <div class="mt-3 mb-6 text-sm text-center text-gray-600">{{ instructions }}</div>

        <form @submit.prevent="submit" class="mt-8 space-y-6">
          <!-- Email Input -->
          <div>
            <label for="email" class="font-medium text-sm text-gray-700">Email:</label>
            <PureInput
              v-model="form.email"
              @update:modelValue="() => form.errors.email = ''"
              id="email"
              placeholder="johndoe@gmail.com"
              type="email"
              required
              autofocus
              autocomplete="email"
            />
            <InputError class="mt-2 italic text-red-500" :message="form.errors.email" />
          </div>

          <!-- Submit Button -->
          <div class="flex items-center justify-center mt-6">
            <Button @click="submit" :loading="form.processing" label="Send Reset Link"  />
          </div>
        </form>
      </template>

      <!-- Success Message -->
      <template v-else>
        <div class="text-center">
          <FontAwesomeIcon icon="fal fa-check-circle" class="text-green-500 text-4xl" fixed-width aria-hidden="true" />
        </div>
        <div class="text-center mt-4 font-bold text-xl text-gray-800">Reset link sent</div>
        <div class="mt-3 mb-4 text-sm text-center text-gray-600">
          We've sent a link to reset your password to <strong>{{ form.email }}</strong>. Please check your email, especially the spam folder.
        </div>
      </template>

      <!-- Login Link -->
      <div class="flex justify-center items-center mt-6">
        <p class="text-sm text-gray-500">
          Remembered your password?
          <Link :href="route('retina.login.show')" class="font-medium text-indigo-600 hover:text-indigo-500 hover:underline transition duration-150 ease-in-out ml-1">Login here</Link>
        </p>
      </div>
    </div>
  </div>
  <ValidationErrors />
</template>
