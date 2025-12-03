<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 Jan 2025 23:38:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watchEffect } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import ValidationErrors from '@/Components/ValidationErrors.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Layout from '@/Layouts/RetinaAuth.vue'

defineOptions({ layout: Layout })

const props=defineProps({
  webUserPasswordResetID: {
    type: Number,
  },
  token: {
    type: String,
  },
  username : {
    type : String
  }
});


const isPasswordSame = ref(false)
const repeatPassword = ref('')

const formReset = useForm({
    password: '',
    web_user_password_reset_id: props.webUserPasswordResetID,
    token: props.token,
})

const submitResetPassword = () => {
  formReset.patch(route('retina.reset-password.update'), {})
}


watchEffect(() => {
    formReset.password == repeatPassword.value ? isPasswordSame.value = true : isPasswordSame.value = false
})
</script>

<template>

    <div class="space-y-4 text-gray-600">

        <form class="space-y-8" @submit.prevent="submitResetPassword">
            <div class="text-center font-semibold text-xl">
                {{ trans("Reset your password") }}
            </div>
            <div class="text-center font-semibold text-lg">
                {{ trans("Username") }} {{ username  }}
            </div>
            <div class="flex flex-col gap-y-4">
                <div class="">
                    <label for="password">{{ trans('New Password') }}</label>
                    <PureInput v-model="formReset.password" type="password" inputName="password"
                        placeholder="Enter new password" />
                    <div v-if="formReset.errors.password">{{ formReset.errors.password }}</div>
                </div>

                <div class="">
                    <label for="repeatPassword">{{ trans('Repeat New Password') }}</label>
                    <PureInput v-model="repeatPassword" type="password" inputName="repeatPassword"
                        placeholder="Repeat your new password" />
                    <div v-if="!isPasswordSame && repeatPassword && formReset.password"
                        class="text-red-500 mt-1 text-sm">Password is not match</div>
                </div>
            </div>

            <div class="flex justify-center">
                <Button :style="'primary'" :disabled="!isPasswordSame || formReset.password.length == 0"
                    :key="formReset.password + repeatPassword" :label="'Reset Password'" @click="submitResetPassword"
                    class="" />
            </div>
        </form>
    </div>
    <ValidationErrors />
</template>
