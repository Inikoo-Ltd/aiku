import { onBeforeUnmount, onMounted } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import axios from "axios"

type TicketRow = { id: number } & Record<string, unknown>

const ROW_REFRESH_DELAY_MS = 500

const rowsAt = (props: Record<string, unknown>, path: string): TicketRow[] => {
    const value = path.split(".").reduce<unknown>((current, key) => (current as Record<string, unknown> | undefined)?.[key], props)

    return Array.isArray(value) ? value : []
}

export const useLiveTicketRows = (rowPaths: string[]) => {
    const page = usePage()
    const groupId = (page.props.layout as any)?.group?.id
    const channelName = `grp.${groupId}.general`
    const pendingRefreshes = new Map<number, ReturnType<typeof setTimeout>>()

    const pathsShowing = (ticketId: number) =>
        rowPaths.filter((path) => rowsAt(page.props as Record<string, unknown>, path).some((row) => row.id === ticketId))

    const isShown = (ticketId: number) => pathsShowing(ticketId).length > 0

    const refreshRow = async (ticketId: number) => {
        const paths = pathsShowing(ticketId)

        if (!paths.length) {
            return
        }

        let freshRow: TicketRow | null = null

        try {
            const { data } = await axios.get(route("grp.json.ticket.row", { ticket: ticketId }))
            freshRow = data
        } catch (error: any) {
            if (![403, 404].includes(error?.response?.status)) {
                return
            }
        }

        pathsShowing(ticketId).forEach((path) => {
            router.replaceProp(path, (rows: TicketRow[]) => freshRow
                ? rows.map((row) => (row.id === ticketId ? { ...row, ...freshRow } : row))
                : rows.filter((row) => row.id !== ticketId))
        })
    }

    const onTicketUpdated = (event: { id: number }) => {
        if (!isShown(event.id)) {
            return
        }

        clearTimeout(pendingRefreshes.get(event.id))
        pendingRefreshes.set(event.id, setTimeout(() => {
            pendingRefreshes.delete(event.id)
            refreshRow(event.id)
        }, ROW_REFRESH_DELAY_MS))
    }

    onMounted(() => {
        if (groupId) {
            window.Echo.private(channelName).listen(".ticket-updated", onTicketUpdated)
        }
    })

    onBeforeUnmount(() => {
        pendingRefreshes.forEach((timer) => clearTimeout(timer))
        pendingRefreshes.clear()

        if (groupId) {
            window.Echo.private(channelName).stopListening(".ticket-updated", onTicketUpdated)
        }
    })

    return { isShown }
}
