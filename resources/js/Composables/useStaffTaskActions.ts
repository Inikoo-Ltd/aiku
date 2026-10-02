import { usePage } from "@inertiajs/vue3"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
import { useStaffMessaging } from "@/Stores/staff-messaging"

export const useStaffTaskActions = (onUpdated: (task: any) => void) => {
    const store = useStaffMessaging()
    const page = usePage()

    const notifyFailure = (error: any) => notify({ title: ctrans("Could not update task"), text: error.response?.data?.message, type: "error" })

    const update = async (task: any, payload: Record<string, unknown>) => {
        try {
            const { data } = await axios.patch(route("grp.tasks.update", task.reference), payload)
            onUpdated(data.data)
        } catch (error: any) {
            notifyFailure(error)
        }
    }

    const claim = (task: any) => update(task, { assignee_id: page.props?.auth?.user?.id, status: "in_progress" })

    const syncCollaborators = async (task: any, people: any[]) => {
        try {
            const { data } = await axios.patch(route("grp.tasks.collaborators.update", task.reference), { collaborator_ids: people.map((person) => person.id) })
            onUpdated(data.data)
        } catch (error: any) {
            notifyFailure(error)
        }
    }

    const toggleSubscription = async (task: any) => {
        try {
            const { data } = await axios.post(route("grp.tasks.subscription.toggle", task.reference))
            onUpdated(data.data)
            await store.fetchConversations()
        } catch (error: any) {
            notifyFailure(error)
        }
    }

    return { update, claim, syncCollaborators, toggleSubscription }
}
