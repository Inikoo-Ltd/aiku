<!--
  - Contact Preferences Component
  - Reusable component for managing customer contact preferences
  -->

<script setup lang="ts">
import { ref, computed, inject } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck, faTimes, faPencil, faEnvelope, faPhone, faMapMarkerAlt, faBan } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'
import ToggleSwitch from 'primevue/toggleswitch'
import { notify } from '@kyvg/vue3-notification'

library.add(faCheck, faTimes, faPencil, faEnvelope, faPhone, faMapMarkerAlt, faBan)

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
const isEditingContactPreferences = ref(false)

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
        
        // If "don't contact me" is activated, disable all other preferences
        if (value) {
            Object.keys(localPreferences.value).forEach(key => {
                localPreferences.value[key] = false
            })
        }
        
        console.log('Dont contact me status changed to:', localDontContactMe.value)
    }
})

// Function to toggle edit mode for contact preferences
const toggleEditContactPreferences = () => {
    if (!props.editable) return
    isEditingContactPreferences.value = !isEditingContactPreferences.value
}

// Function to handle individual preference toggle
const togglePreference = async (preferenceKey: string, value: boolean) => {
    if (!props.editable || localDontContactMe.value) return
    
    localPreferences.value[preferenceKey] = value
    
    // Get the field name for the preference
    const preference = props.contactPreferences?.preferences[preferenceKey]
    if (!preference || !props.contactPreferences?.update_route) {
        console.error('Preference or update route not found')
        notify({
            title: trans('Error'),
            text: trans('Contact preference configuration not found'),
            type: 'error'
        })
        return
    }
    
    const updateRoute = props.contactPreferences.update_route
    const fieldName = preference.field
    
    try {
        // Make API call to update preference status
        await router.patch(
            route(updateRoute.name, updateRoute.parameters),
            {
                [fieldName]: value
            }
        )
        
        // Show success toast
        notify({
            title: trans('Success'),
            text: value 
                ? trans('Contact via :method is now allowed', { method: preference.label })
                : trans('Contact via :method is now disabled', { method: preference.label }),
            type: 'success'
        })
        
        console.log(`Preference ${preferenceKey} updated successfully to:`, value)
    } catch (error) {
        console.error('Failed to update preference:', error)
        
        // Revert the local state on error
        localPreferences.value[preferenceKey] = !value
        
        // Show error toast
        notify({
            title: trans('Error'),
            text: trans('Failed to update contact preference. Please try again.'),
            type: 'error'
        })
    }
}

// Function to handle "don't contact me" toggle
const toggleDontContactMe = async (value: boolean) => {
    if (!props.editable) return
    
    dontContactMeToggle.value = value
    
    if (!props.contactPreferences?.update_route) {
        console.error('Update route not found')
        notify({
            title: trans('Error'),
            text: trans('Contact preference configuration not found'),
            type: 'error'
        })
        return
    }
    
    const updateRoute = props.contactPreferences.update_route
    
    try {
        // Prepare data for API call
        const updateData: Record<string, boolean> = {
            dont_contact_me: value
        }
        
        // If activating "don't contact me", also disable all other preferences
        if (value) {
            Object.keys(props.contactPreferences.preferences).forEach(key => {
                const preference = props.contactPreferences.preferences[key]
                updateData[preference.field] = false
            })
        }
        
        // Make API call to update all preferences
        await router.patch(
            route(updateRoute.name, updateRoute.parameters),
            updateData
        )
        
        // Show success toast
        notify({
            title: trans('Success'),
            text: value 
                ? trans('All contact methods have been disabled')
                : trans('Contact preferences have been restored'),
            type: 'success'
        })
        
        console.log(`Don't contact me updated successfully to:`, value)
    } catch (error) {
        console.error('Failed to update dont contact me:', error)
        
        // Revert the local state on error
        localDontContactMe.value = !value
        if (!value) {
            // Restore previous preferences state if needed
            Object.keys(localPreferences.value).forEach(key => {
                localPreferences.value[key] = props.contactPreferences?.preferences[key]?.is_allowed ?? false
            })
        }
        
        // Show error toast
        notify({
            title: trans('Error'),
            text: trans('Failed to update contact preferences. Please try again.'),
            type: 'error'
        })
    }
}

// Expose methods for parent component if needed
defineExpose({
    toggleEditContactPreferences,
    isEditingContactPreferences: computed(() => isEditingContactPreferences.value),
    localPreferences: computed(() => localPreferences.value),
    localDontContactMe: computed(() => localDontContactMe.value)
})
</script>

<template>
    <!-- Contact Preferences Section -->
    <div v-if="contactPreferences" :class="containerClass">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-medium text-gray-900">{{ trans("Contact Preferences") }}</h3>
            
            <!-- Edit Button -->
            <button 
                v-if="showEditButton && editable"
                @click="toggleEditContactPreferences"
                :style="{ color: layout?.app?.theme?.[0] || '#6366f1' }"
                class="p-1 rounded transition-colors duration-200 hover:bg-gray-100"
                v-tooltip="isEditingContactPreferences ? trans('Cancel Edit') : trans('Edit Contact Preferences')"
            >
                <FontAwesomeIcon 
                    :icon="isEditingContactPreferences ? faTimes : faPencil" 
                    class="text-sm" 
                    fixed-width 
                />
            </button>
        </div>

        <!-- Don't Contact Me Status -->
        <!-- <div v-if="contactPreferences.dont_contact_me" class="mb-3 p-2 rounded-md"
            :class="localDontContactMe ? 'bg-red-50 border border-red-200' : 'bg-gray-50 border border-gray-200'">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <FontAwesomeIcon 
                        :icon="faBan" 
                        :class="localDontContactMe ? 'text-red-500' : 'text-gray-400'"
                        class="text-sm mr-2" 
                    />
                    <span class="text-sm font-medium"
                        :class="localDontContactMe ? 'text-red-700' : 'text-gray-700'">
                        {{ contactPreferences.dont_contact_me.label }}
                    </span>
                </div>

               
                <div v-if="isEditingContactPreferences && editable" class="flex items-center">
                    <ToggleSwitch 
                        :modelValue="localDontContactMe"
                        @update:modelValue="toggleDontContactMe" 
                        :class="{
                            'toggle-switch-danger': localDontContactMe,
                            'toggle-switch-inactive': !localDontContactMe
                        }" 
                        v-tooltip="localDontContactMe ? trans('Contact Suspended') : trans('Contact Allowed')" 
                    />
                </div>

              
                <div v-else class="flex items-center">
                    <FontAwesomeIcon 
                        :icon="localDontContactMe ? faCheck : faTimes"
                        :class="localDontContactMe ? 'text-red-500' : 'text-green-500'"
                        class="text-sm" 
                    />
                    <span 
                        class="ml-1 text-xs"
                        :class="localDontContactMe ? 'text-red-600' : 'text-green-600'"
                    >
                        {{ localDontContactMe ? trans('Active') : trans('Inactive') }}
                    </span>
                </div>
            </div>
        </div> -->

        <!-- Contact Preferences List - Hidden when "don't contact me" is active -->
        <div v-if="!localDontContactMe" class="space-y-2 overflow-hidden">
            <div 
                v-for="(preference, key) in contactPreferences.preferences" 
                :key="key"
                class="flex items-center justify-between hover:bg-gray-50 rounded py-2"
            >
                <div class="flex items-center">
                    <span class="text-sm text-gray-700">{{ preference.label }}</span>
                </div>

                <!-- Edit Mode: Toggle Switch -->
                <div v-if="isEditingContactPreferences && editable" class="flex items-center">
                    <ToggleSwitch 
                        :modelValue="localPreferences[key]"
                        @update:modelValue="(value) => togglePreference(key, value)" 
                        :class="{
                            'toggle-switch-active': localPreferences[key],
                            'toggle-switch-inactive': !localPreferences[key]
                        }" 
                        v-tooltip="localPreferences[key] ? trans('Allowed') : trans('Not Allowed')" 
                    />
                </div>

                <!-- View Mode: Status Display -->
                <div v-else class="flex items-center">
                    <FontAwesomeIcon 
                        :icon="preference.is_allowed ? faCheck : faTimes"
                        :class="preference.is_allowed ? 'text-green-500' : 'text-red-500'"
                        class="text-sm" 
                    />
                    <span 
                        class="ml-1 text-xs"
                        :class="preference.is_allowed ? 'text-green-600' : 'text-red-600'"
                    >
                        {{ preference.is_allowed ? trans('Allowed') : trans('Not Allowed') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Message when "don't contact me" is active -->
        <div v-if="localDontContactMe" class="mt-3 p-2 bg-red-50 rounded text-center">
            <span class="text-xs text-red-600">{{ trans('All contact methods are suspended') }}</span>
            <div v-if="contactPreferences.dont_contact_me.reason" class="mt-1">
                <span class="text-xs text-red-500">{{ trans('Reason') }}: {{ contactPreferences.dont_contact_me.reason }}</span>
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