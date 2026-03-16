<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import RetinaShowIris from '@/Layouts/RetinaShowIris.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faArrowLeft, faCheckCircle } from '@fal'
import { faExclamationTriangle } from "@fas"
import { library } from '@fortawesome/fontawesome-svg-core'
import { ref } from 'vue'
import InputError from '@/Components/InputError.vue'
import Modal from "@/Components/Utils/Modal.vue"
library.add(faArrowLeft, faCheckCircle, faExclamationTriangle)


defineOptions({ layout: RetinaShowIris })
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
    const { isDirty, errors, hasErrors, processing, progress, wasSuccessful, ...data } = form

    if (isUserInputPassed(data)) return
    
    form.post(route('retina.reset-password.send'), {
        onSuccess: () => isResetLinkSent.value = true
    })
}

const isModalRemoveScript = ref(false)
const isModalRemoveHtml = ref(false)

const isUserInputPassed = (dataToCheck: Record<string, any>) => {
    // Check <script>
    for (const key in dataToCheck) {
        const inputValue = dataToCheck[key]

        if (typeof inputValue === 'string' && /<script>/i.test(inputValue)) {
            isModalRemoveScript.value = true
            form.errors[key] = "Script tags are not allowed."
            return true
        }
    }

    // Check HTML tags
    for (const key in dataToCheck) {
        const inputValue = dataToCheck[key]

        if (
            typeof inputValue === 'string' &&
            /<[^>]+>/i.test(inputValue) &&
            !/<script>/i.test(inputValue)
        ) {
            isModalRemoveHtml.value = true
            form.errors[key] = "HTML tags are not allowed."
            return true
        }
    }

    return false
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
    <div class="flex items-center justify-center px-6 py-12 lg:px-8">
    <div class="w-full max-w-md">
      <!-- Conditional Form for Reset Link -->
      <template v-if="!isResetLinkSent">
        <div class="text-center font-bold text-2xl text-gray-800">{{ ctrans("Reset Password") }}</div>
        <div class="mt-3 mb-6 text-sm text-center text-gray-600">{{ instructions }}</div>

        <form @submit.prevent="submit" class="mt-8 space-y-6">
          <!-- Email Input -->
          <div>
            <label for="email" class="font-medium text-sm text-gray-700">{{ ctrans("Email") }}:</label>
            <PureInput
              v-model="form.email"
              @update:modelValue="() => form.errors.email = ''"
              id="email"
              placeholder="example@email.com"
              type="email"
              required
              autofocus
              autocomplete="email"
            />
            <InputError class="mt-2 italic text-red-500" :message="form.errors.email" />
          </div>

          <!-- Submit Button -->
          <div class="flex items-center justify-center mt-6">
            <Button @click="submit" :loading="form.processing" :label="ctrans('Send Reset Link')"  />
          </div>
        </form>
      </template>

      <!-- Success Message -->
      <template v-else>
        <div class="text-center">
          <FontAwesomeIcon icon="fal fa-check-circle" class="text-green-500 text-4xl" fixed-width aria-hidden="true" />
        </div>
        <div class="text-center mt-4 font-bold text-xl text-gray-800">{{ ctrans("Reset link sent") }}</div>
        <div class="mt-3 mb-4 text-sm text-center text-gray-600">
          {{ ctrans("We've sent a link to reset your password to:") }} <strong>{{ form.email }}</strong>.
          <br>{{ ctrans("Please check your email, especially the spam folder") }}.
        </div>
      </template>

      <!-- Login Link -->
      <div class="flex justify-center items-center mt-6">
        <p class="text-sm text-gray-500">
          {{ ctrans("Remembered your password") }}?
          <Link :href="route('retina.login.show', { tiktok_code: route().queryParams?.tiktok_code })" class="font-medium text-indigo-600 hover:text-indigo-500 hover:underline transition duration-150 ease-in-out ml-1">{{ ctrans("Login here") }}</Link>
        </p>
      </div>
      
    </div>
    <Modal :isOpen="isModalRemoveScript" @onClose="isModalRemoveScript = false" width="w-full max-w-lg">
          <div class="flex min-h-full items-end justify-center text-center sm:items-center px-2 py-3">
              <div class="relative transform overflow-hidden rounded-lg bg-white text-left transition-all w-full">
                  <div>
                      <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-gray-100">
                          <FontAwesomeIcon icon='fas fa-exclamation-triangle' class="text-red-500 text-4xl" />
                      </div>

                      <div class="mt-3 text-center">
                          <div class="font-semibold text-2xl text-red-600">
                              {{ ctrans("Don't do that to us") }}!
                          </div>

                          <div class="mt-2 text-sm opacity-75">
                              {{ ctrans("Please remove the script before you submit") }}
                          </div>
                      </div>
                  </div>

                  <div class="mt-5">
                      <Button
                          @click="isModalRemoveScript = false"
                          :label="ctrans('Okay')"
                          full
                      />
                  </div>
              </div>
          </div>
      </Modal>
      <Modal :isOpen="isModalRemoveHtml" width="w-full max-w-2xl" @close="isModalRemoveHtml = false">
          <div class="flex min-h-full items-end justify-center text-center sm:items-center px-2 py-3">
              <div class="relative transform overflow-hidden rounded-lg bg-white text-left transition-all w-full">
                  <div>
                      <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-gray-100">
                          <FontAwesomeIcon icon='fas fa-exclamation-triangle' class="text-amber-500 text-4xl" />
                      </div>

                      <div class="mt-3 text-center">
                          <div class="font-semibold text-2xl text-amber-600">
                              {{ ctrans("Remove the HTML code") }}!
                          </div>

                          <div class="mt-2 text-sm opacity-75">
                              {{ ctrans("It looks like you added HTML code. Please remove it before submitting.") }}
                          </div>
                      </div>
                  </div>

                  <div class="mt-5">
                      <Button
                          @click="isModalRemoveHtml = false"
                          :label="ctrans('Okay')"
                          full
                      />
                  </div>
              </div>
          </div>
      </Modal>
  </div>
  <ValidationErrors />
</template>
