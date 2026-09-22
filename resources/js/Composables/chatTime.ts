// A bare clock reading is a trap on anything but today: 13:42 looks recent whether it was an
// hour ago or last March, so anything older carries its date.
export const formatChatTime = (timestamp: number): string => {
    const d = new Date(timestamp)
    const time = d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })

    return d.toDateString() === new Date().toDateString()
        ? time
        : `${d.toLocaleDateString([], { day: "numeric", month: "short" })} ${time}`
}

// How long it has been waiting, in the largest unit that still says something useful.
export const formatChatAge = (timestamp: number): string => {
    const minutes = Math.max(0, Math.round((Date.now() - timestamp) / 60000))

    if (minutes < 60) {
        return `${minutes}m`
    }

    const hours = Math.round(minutes / 60)

    return hours < 48 ? `${hours}h` : `${Math.round(hours / 24)}d`
}
