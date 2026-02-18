<script setup lang="ts">
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import Button from '../Elements/Buttons/Button.vue'
import { ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

const props = defineProps<{
    webpage: {}
}>()


const isLoadingReindexing = ref(false)
const aaa = async () => {
    try {
        isLoadingReindexing.value = true
        const response = await axios.post(
            route(
                'grp.models.webpage_luigi.reindex',
                {
                    webpage: props.webpage?.id
                }
            ),
            {}
        )
        

        // console.log('Response axios:', response.data)

        if (response.data.status === 'error') {
            throw new Error(response.data?.message);
            
        } else {
            notify({
                title: trans("Success!"),
                text: response.data?.message || trans("Successfully reindexing webpage"),
                type: 'success'
            })
        }
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error.message || trans("Please try again or contact administrator"),
            type: 'error'
        })
    } finally {
        isLoadingReindexing.value = false
    }
}
</script>

<template>

    <Button
        v-if="webpage?.luigi_data?.luigisbox_tracker_id"
        @click="aaa"
        icon="fal fa-search"
        method="post"
        :type="webpage?.luigi_data?.luigisbox_private_key ? 'tertiary' : 'warning'"
        full
        :loading="isLoadingReindexing"
    >
        <template #label>
            <span class="text-xs">
                {{ trans('Reindex Webpage Search') }}
            </span>
        </template>
        <template #iconRight>
            <div v-if="!webpage?.luigi_data?.luigisbox_private_key"
                v-tooltip="trans('Please input Luigi Private Key do start reindexing')" class="text-amber-500">
                <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="" fixed-width aria-hidden="true" />
            </div>
        </template>
    </Button>
</template>