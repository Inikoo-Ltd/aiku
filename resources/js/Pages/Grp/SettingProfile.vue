<script setup lang='ts'>
import Editmodel from '@/Pages/Grp/EditModel.vue'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import { onMounted, ref } from 'vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import LoadingText from '@/Components/Utils/LoadingText.vue'
library.add(faSpinnerThird)

const dataEditProfile = ref<{} | null>(null)
onMounted(async () => {
    try {
        const { data } = await axios.get(route('grp.profile.settings'))
        dataEditProfile.value = data
    } catch (error) {
        notify({
            title: trans('Something went wrong.'),
            text: trans('Failed to fetch this page.'),
            type: 'error',
        })
    }
})
</script>

<template>
    <Editmodel v-if="dataEditProfile" v-bind="dataEditProfile"/>

    <div v-else class="h-full flex flex-col gap-y-2 items-center justify-center">
        <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin' size="2x" fixed-width aria-hidden='true' />
        <LoadingText />
    </div>
</template>
