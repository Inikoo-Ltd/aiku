<!--
  - Email Subscription Component
  - Reusable component for managing customer email subscriptions
  -->

<script setup lang="ts">
import { ref, computed, inject } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck, faTimes, faPencil } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'
import ToggleSwitch from 'primevue/toggleswitch'
import { notify } from '@kyvg/vue3-notification'

library.add(faCheck, faTimes, faPencil)

interface EmailSubscription {
    label: string
    field: string
    is_subscribed: boolean
    unsubscribed_at: string | null
}

interface EmailSubscriptions {
    update_route: {
        method: string
        name: string
        parameters: number[]
    }
    suspended: {
        label: string
        is_suspended: boolean
        suspended_at: string | null
        suspended_cause: string | null
    }
    subscriptions: {
        [key: string]: EmailSubscription
    }
}

interface Props {
    emailSubscriptions: EmailSubscriptions
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
const localIsSuspended = ref(props.emailSubscriptions?.suspended?.is_suspended ?? false)
const isEditingEmailSubscriptions = ref(false)

// Local state for individual subscriptions
const localSubscriptions = ref(
    Object.keys(props.emailSubscriptions?.subscriptions || {}).reduce((acc, key) => {
        acc[key] = props.emailSubscriptions?.subscriptions[key]?.is_subscribed ?? false
        return acc
    }, {} as Record<string, boolean>)
)

// Computed property for toggle switch value (inverted logic)
const toggleSwitchValue = computed({
    get: () => !localIsSuspended.value,
    set: (value: boolean) => {
        localIsSuspended.value = !value
        // Here you can add API call to update the server
        console.log('Email subscription suspended status changed to:', localIsSuspended.value)
    }
})

// Function to toggle edit mode for email subscriptions
const toggleEditEmailSubscriptions = () => {
    if (!props.editable) return
    isEditingEmailSubscriptions.value = !isEditingEmailSubscriptions.value
}

// Function to handle individual subscription toggle
const toggleSubscription = async (subscriptionKey: string, value: boolean) => {
    if (!props.editable) return
    
    localSubscriptions.value[subscriptionKey] = value
    
    // Get the field name for the subscription
    const subscription = props.emailSubscriptions?.subscriptions[subscriptionKey]
    if (!subscription || !props.emailSubscriptions?.update_route) {
        console.error('Subscription or update route not found')
        notify({
            title: trans('Error'),
            text: trans('Subscription configuration not found'),
            type: 'error'
        })
        return
    }
    
    const updateRoute = props.emailSubscriptions.update_route
    const fieldName = subscription.field
    
    try {
        // Make API call to update subscription status
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
                ? trans('Successfully subscribed to :subscription', { subscription: subscription.label })
                : trans('Successfully unsubscribed from :subscription', { subscription: subscription.label }),
            type: 'success'
        })
        
        console.log(`Subscription ${subscriptionKey} updated successfully to:`, value)
    } catch (error) {
        console.error('Failed to update subscription:', error)
        
        // Revert the local state on error
        localSubscriptions.value[subscriptionKey] = !value
        
        // Show error toast
        notify({
            title: trans('Error'),
            text: trans('Failed to update email subscription. Please try again.'),
            type: 'error'
        })
    }
}

// Expose methods for parent component if needed
defineExpose({
    toggleEditEmailSubscriptions,
    isEditingEmailSubscriptions: computed(() => isEditingEmailSubscriptions.value),
    localSubscriptions: computed(() => localSubscriptions.value),
    localIsSuspended: computed(() => localIsSuspended.value)
})
</script>

<template>
    <!-- Email Subscriptions Section -->
    <div v-if="emailSubscriptions" :class="containerClass">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-medium text-gray-900">{{ trans("Subscriptions") }}</h3>
            
            <!-- Edit Button -->
            <button 
                v-if="showEditButton && editable"
                @click="toggleEditEmailSubscriptions"
                :style="{ color: layout?.app?.theme?.[0] || '#6366f1' }"
                class="p-1 rounded transition-colors duration-200 hover:bg-gray-100"
                v-tooltip="isEditingEmailSubscriptions ? trans('Cancel Edit') : trans('Edit Email Subscriptions')"
            >
                <FontAwesomeIcon 
                    :icon="isEditingEmailSubscriptions ? faTimes : faPencil" 
                    class="text-sm" 
                    fixed-width 
                />
            </button>
        </div>

        <!-- Suspended Status -->
        <div v-if="emailSubscriptions.suspended"
            :class="`mb-3 flex items-center ${isEditingEmailSubscriptions ? 'justify-between' : 'justify-end'}`">
            <!-- Note: Suspended toggle functionality can be added here if needed -->
        </div>

        <!-- Subscriptions List - Hidden when suspended -->
        <div v-if="!localIsSuspended" class="space-y-2 overflow-hidden">
            <div 
                v-for="(subscription, key) in emailSubscriptions.subscriptions" 
                :key="key"
                class="flex items-center justify-between hover:bg-gray-50 rounded p-1"
            >
                <span class="text-sm text-gray-700">{{ subscription.label }}</span>

                <!-- Edit Mode: Toggle Switch -->
                <div v-if="isEditingEmailSubscriptions && editable" class="flex items-center">
                    <ToggleSwitch 
                        :modelValue="localSubscriptions[key]"
                        @update:modelValue="(value) => toggleSubscription(key, value)" 
                        :class="{
                            'toggle-switch-active': localSubscriptions[key],
                            'toggle-switch-inactive': !localSubscriptions[key]
                        }" 
                        v-tooltip="localSubscriptions[key] ? trans('Subscribed') : trans('Unsubscribed')" 
                    />
                </div>

                <!-- View Mode: Status Display -->
                <div v-else class="flex items-center">
                    <FontAwesomeIcon 
                        :icon="subscription.is_subscribed ? faCheck : faTimes"
                        :class="subscription.is_subscribed ? 'text-green-500' : 'text-red-500'"
                        class="text-sm" 
                    />
                    <span 
                        class="ml-1 text-xs"
                        :class="subscription.is_subscribed ? 'text-green-600' : 'text-red-600'"
                    >
                        {{ subscription.is_subscribed ? trans('Subscribed') : trans('Unsubscribed') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Message when suspended -->
        <div v-if="localIsSuspended" class="mt-3 p-2 bg-red-50 rounded text-center">
            <span class="text-xs text-red-600">{{ trans('All email communications are suspended') }}</span>
        </div>
    </div>
</template>

<style scoped>
/* Toggle Switch Active (Green) */
.toggle-switch-active :deep(.p-toggleswitch-slider) {
    background-color: #10b981 !important; /* green-500 */
}

.toggle-switch-active :deep(.p-toggleswitch-slider:hover) {
    background-color: #059669 !important; /* green-600 */
}

/* Toggle Switch Inactive (Red) */
.toggle-switch-inactive :deep(.p-toggleswitch-slider) {
    background-color: #ef4444 !important; /* red-500 */
}

.toggle-switch-inactive :deep(.p-toggleswitch-slider:hover) {
    background-color: #dc2626 !important; /* red-600 */
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