<!--
  - Contact Preferences Component
  - Reusable component for managing customer contact preferences
  -->

<script setup lang="ts">
import { ref, computed, inject } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck, faTimes, faPencil, faEnvelope, faPhone, faMapMarkerAlt, faBan, faUndo } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'
import ToggleSwitch from 'primevue/toggleswitch'
import { notify } from '@kyvg/vue3-notification'

library.add(faCheck, faTimes, faPencil, faEnvelope, faPhone, faMapMarkerAlt, faBan, faUndo)

interface ContactPreference {
    label: string
    field: string
    is_allowed: boolean
    icon: any
    updated_at: string | null
}

interface ContactPreferences {
    update_route?: {
        method: string
        name: string
        parameters: number[]
    }
    dont_contact_me: {
        label: string
        is_active: boolean
        activated_at: string | null
        reason: string | null
    }
    preferences: {
        [key: string]: ContactPreference
    }
}

interface Props {
    contactPreferences: ContactPreferences
    containerClass?: string
    showEditButton?: boolean
    editable?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    containerClass: 'mt-4 w-64 border border-gray-300 rounded-md p-3',
    showEditButton: true,
    editable: true
})

const layout = inject('layout')

// Local state management
const localDontContactMe = ref(props.contactPreferences?.dont_contact_me?.is_active ?? false)

// Local state for individual preferences
const localPreferences = ref(
    Object.keys(props.contactPreferences?.preferences || {}).reduce((acc, key) => {
        acc[key] = props.contactPreferences?.preferences[key]?.is_allowed ?? false
        return acc
    }, {} as Record<string, boolean>)
)

// Computed property for "don't contact me" toggle switch
const dontContactMeToggle = computed({
    get: () => localDontContactMe.value,
    set: (value: boolean) => {
        localDontContactMe.value = value
        
        // Don't automatically change other preferences when toggling "don't contact me"
        console.log('Dont contact me status changed to:', localDontContactMe.value)
    }
})

// Function to undo "don't contact me" and restore original view
const undoDontContact = () => {
    if (!props.editable) return
    
    localDontContactMe.value = false
    
    // API call disabled for UI testing
    console.log('Undo don\'t contact me - restored to normal view')
    
    // Show success toast for UI feedback
    notify({
        title: trans('Success'),
        text: trans('Contact preferences have been restored'),
        type: 'success'
    })
}

// Function to handle individual preference toggle
const togglePreference = async (preferenceKey: string, value: boolean) => {
    if (!props.editable || localDontContactMe.value) return
    
    localPreferences.value[preferenceKey] = value
    
    // Get the field name for the preference
    const preference = props.contactPreferences?.preferences[preferenceKey]
    if (!preference) {
        console.error('Preference not found')
        return
    }
    
    // API call disabled for UI testing
    console.log(`Preference ${preferenceKey} updated locally to:`, value)
    
    // Show success toast for UI feedback
    notify({
        title: trans('Success'),
        text: value 
            ? trans('Contact via :method is now allowed', { method: preference.label })
            : trans('Contact via :method is now disabled', { method: preference.label }),
        type: 'success'
    })
}

// Function to handle "don't contact me" toggle
const toggleDontContactMe = async (value: boolean) => {
    if (!props.editable) return
    
    dontContactMeToggle.value = value
    
    // API call disabled for UI testing
    console.log(`Don't contact me updated locally to:`, value)
    
    // Show success toast for UI feedback
    notify({
        title: trans('Success'),
        text: value 
            ? trans('All contact methods have been disabled')
            : trans('Contact preferences have been restored'),
        type: 'success'
    })
}

// Expose methods for parent component if needed
defineExpose({
    localPreferences: computed(() => localPreferences.value),
    localDontContactMe: computed(() => localDontContactMe.value),
    undoDontContact
})
</script>

<template>
    <!-- Buttons outside and above the box -->
    <div v-if="contactPreferences">
        <!-- Don't Contact Me Button - Only show when NOT active -->
        <div v-if="contactPreferences.dont_contact_me && !localDontContactMe && editable" class="mb-3 text-center">
            <button @click="toggleDontContactMe(true)"
                class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white text-sm rounded transition-colors duration-200 flex items-center justify-center mx-auto w-full">
                <FontAwesomeIcon :icon="faBan" class="text-white text-sm mr-2" />
                {{ contactPreferences.dont_contact_me.label }}
            </button>
        </div>

        <!-- Undo Button - Only show when "don't contact me" is active -->
        <div v-if="localDontContactMe && editable" class="mb-3 text-center">
            <button @click="undoDontContact"
                class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm rounded transition-colors duration-200 w-full">
                <FontAwesomeIcon :icon="faUndo" class="text-white text-sm mr-2" />
                {{ trans('Undo Don\'t Contact') }}
            </button>
        </div>

        <!-- Contact Preferences Section Box -->
        <div :class="containerClass">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-sm font-medium text-gray-900">{{ trans("Contact Preferences") }}</h3>
            </div>

            <!-- Contact Preferences List - Hidden when "don't contact me" is active -->
            <div v-if="!localDontContactMe" class="space-y-2 overflow-hidden">
                <div v-for="(preference, key) in contactPreferences.preferences" :key="key"
                    class="flex items-center justify-between hover:bg-gray-50 rounded py-2">
                    <div class="flex items-center">
                        <span class="text-sm text-gray-700">{{ preference.label }}</span>
                    </div>

                    <!-- Always show Toggle Switch (no edit mode) -->
                    <div v-if="editable" class="flex items-center">
                        <ToggleSwitch :modelValue="localPreferences[key]"
                            @update:modelValue="(value) => togglePreference(key, value)" :class="{
                                'toggle-switch-active': localPreferences[key],
                                'toggle-switch-inactive': !localPreferences[key]
                            }" v-tooltip="localPreferences[key] ? trans('Allowed') : trans('Not Allowed')" />
                    </div>
                </div>
            </div>

            <!-- Message when "don't contact me" is active -->
            <div v-if="localDontContactMe" class="mt-3">
                <!-- Message -->
                <div class="p-2 bg-red-50 rounded text-center">
                    <span class="text-xs text-red-600">{{ trans('Prospect do not want to be contacted') }}</span>
                    <div v-if="contactPreferences.dont_contact_me.reason" class="mt-1">
                        <span class="text-xs text-red-500">{{ trans('Reason') }}: {{
                            contactPreferences.dont_contact_me.reason }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Toggle Switch Active (Green) */
.toggle-switch-active :deep(.p-toggleswitch-slider) {
    background-color: #10b981 !important;
    /* green-500 */
}

.toggle-switch-active :deep(.p-toggleswitch-slider:hover) {
    background-color: #059669 !important;
    /* green-600 */
}

/* Toggle Switch Inactive (Red) */
.toggle-switch-inactive :deep(.p-toggleswitch-slider) {
    background-color: #ef4444 !important;
    /* red-500 */
}

.toggle-switch-inactive :deep(.p-toggleswitch-slider:hover) {
    background-color: #dc2626 !important;
    /* red-600 */
}

/* Handle (circle) styling */
.toggle-switch-active :deep(.p-toggleswitch-handle),
.toggle-switch-inactive :deep(.p-toggleswitch-handle) {
    background-color: white !important;
    border: 2px solid white !important;
}

/* Hover effects */
.hover\:bg-gray-50:hover {
    background-color: #f9fafb;
}

.hover\:bg-gray-100:hover {
    background-color: #f3f4f6;
}
</style>