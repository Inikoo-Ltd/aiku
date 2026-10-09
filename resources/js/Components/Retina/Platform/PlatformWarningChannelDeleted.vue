<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { ctrans } from '@/Composables/useTrans'
import { Message } from 'primevue'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import { routeType } from '@/types/route'

defineProps<{
    create_route?: routeType
}>()
</script>

<template>
    <Message severity="error" class="mt-8">
        <div class="ml-2 font-normal flex flex-col gap-x-4 gap-y-3 sm:flex-row sm:items-center justify-between w-full">
            <div>
                <FontAwesomeIcon icon="fad fa-exclamation-triangle" class="text-xl" fixed-width aria-hidden="true" />
                <div class="inline items-center gap-x-2">
                    <span class="font-bold">{{ ctrans("This channel has already been deleted.") }}</span>
                    <span class="ml-1">
                        {{ ctrans("It can no longer be used or connected. To sell on this platform again, create a new channel and connect it.") }}
                    </span>
                </div>
            </div>

            <div class="w-full sm:w-fit h-fit">
                <ButtonWithLink
                    v-if="create_route"
                    :routeTarget="create_route"
                    :label="ctrans('Create new channel')"
                    type="secondary"
                    icon="fal fa-plus"
                    full
                />
            </div>
        </div>
    </Message>
</template>
