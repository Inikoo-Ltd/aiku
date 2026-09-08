<script setup lang="ts">
// This file is called in BannerWorkshop, WebsiteWorkshop
import { trans } from 'laravel-vue-i18n'
import Popover from "@/Components/Utils/Popover.vue"
import Button from '@/Components/Elements/Buttons/Button.vue'

import { faSpinnerThird } from '@fad'
import { faRocketLaunch, faCommentAltLines } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faSpinnerThird, faRocketLaunch, faCommentAltLines)

const props = defineProps<{
    modelValue: string
    is_dirty: boolean
    isLoading: boolean
}>()

const emits = defineEmits<{
    (e: 'update:modelValue', value: string): void
    (e: 'onPublish', value: { close: Function, open: boolean }): void
}>()

const onPublishWithoutComment = () => {
    emits('update:modelValue', '')
    emits('onPublish', { close: () => {}, open: false })
}
</script>

<template>
    <div class="flex items-center relative" tabindex="-1">
        <slot name="button" :onPublishWithoutComment>
            <Button
                :label="trans('Publish')"
                :style="!is_dirty ? 'tertiary' : 'primary'"
                :key="is_dirty.toString()"
                icon="far fa-rocket-launch"
                :loading="isLoading"
                class="rounded-r-none"
                @click="onPublishWithoutComment"
            />
        </slot>

        <!-- Popover (comment section): only needed when publishing with a comment -->
        <Popover>
            <template #button>
                <Button
                    :style="!is_dirty ? 'tertiary' : 'primary'"
                    :key="is_dirty.toString()"
                    icon="far fa-comment-alt-lines"
                    :disabled="isLoading"
                    :tooltip="trans('Publish with comment')"
                    class="rounded-l-none -ml-px"
                />
            </template>

            <!-- Section: Popover -->
            <template #content="{ open, close }">
                <div>
                    <div class="inline-flex items-start leading-none">
                        <span>{{ trans('Comment') }}</span>
                        <span class="ml-1 text-gray-400 text-xs">({{ trans('optional') }})</span>
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
                            :label="trans('Publish')"
                            @click="() => emits('onPublish', { open, close })"
                            :style="'primary'"
                            :loading="isLoading"
                        />
                    </div>
                </div>
            </template>
        </Popover>
    </div>
</template>

<style scoped></style>
