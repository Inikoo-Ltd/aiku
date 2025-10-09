<script setup lang="ts">
// This file is called in BannerWorkshop, WebsiteWorkshop
import { trans } from 'laravel-vue-i18n'
import Popover from "@/Components/Utils/Popover.vue"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { computed, ref } from 'vue'
// import { v4 as uuidv4 } from 'uuid'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSpinnerThird } from '@fad'
import { faAsterisk } from '@fas'
import { faRocketLaunch } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faSpinnerThird, faAsterisk, faRocketLaunch)

const props = defineProps<{
    modelValue: string
    is_dirty: boolean
    isLoading: boolean
}>()

// const compRandomKey = computed(() => {
//     return uuidv4()
// })

const emits = defineEmits<{
    (e: 'update:modelValue', value: string): void
    (e: 'onPublish', value: {close: Function, open: boolean}): void
}>()


</script>

<template>
    <div class="flex items-center gap-2 relative" tabindex="-1">


        <!-- Popover (comment section) is appear if it 2nd publishing or more -->
        <Popover>
            <template #button="{ open }">
                <!-- Style: Compare the hash from current data with hash from empty data -->
                 <slot name="button" :open>
                    <Button
                        :label="'Publish'"
                        :style="!is_dirty
                            ? 'tertiary'
                            :  'primary'"
                    :disabled="open"
                        :key="is_dirty.toString()"
                        :icon="'far fa-rocket-launch'"
                    />
                    </slot>
            </template>

            <!-- Section: Popover -->
            <template #content="{ open, close }">
                <div>
                    <div class="inline-flex items-start leading-none">
                        <FontAwesomeIcon :icon="'fas fa-asterisk'" class="font-light text-[12px] text-red-400 mr-1" />
                        <span class="">{{ trans('Comment') }}</span>
                    </div>
                    <div class="py-2.5">
                        <textarea
                            rows="3"
                            :value="modelValue"
                            :placeholder="trans('Add additional comment')"
                            @input="emits('update:modelValue', $event.target?.value)"
                            class="block w-64 lg:w-96 rounded-md shadow-sm placeholder:text-gray-400 border-gray-300 focus:border-gray-500 focus:ring-gray-500 sm:text-sm" />
                    </div>
                    <slot name="form-extend"></slot>
                    <div class="flex justify-end">
                        <Button
                            size="s"
                            full
                            icon="far fa-rocket-launch"
                            label="Publish"
                            @click="() => emits('onPublish', {open, close})"
                            :key="(modelValue.length > 0 ).toString()"
                            :style="modelValue.length ? 'primary' : 'disabled'"
                            :loading="isLoading"
                        />
                    </div>
                </div>
            </template>
        </Popover>
    </div>
</template>

<style scoped></style>
