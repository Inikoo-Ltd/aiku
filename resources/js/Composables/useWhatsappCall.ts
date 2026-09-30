/**
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright: 2026
 */
import { computed, reactive, ref } from "vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"

export type WhatsappCall = {
    id: number
    organisation?: string | null
    wa_call_id: string
    status: "ringing" | "in_progress" | "completed" | "missed" | "rejected" | "failed"
    direction: "user_initiated" | "business_initiated"
    phone_number: string | null
    user_id: number | null
    user_name?: string | null
    duration_seconds: number | null
    answered_at: string | null
    ended_at: string | null
}

// A WhatsApp call is audio. Meta lists video and screen sharing as planned rather than
// released, so there is nothing to negotiate for them and the console offers voice only.
export const WHATSAPP_VIDEO_AVAILABLE = false

export const WHATSAPP_OUTGOING_CALL_AVAILABLE = false

// One call at a time per agent, so the state is module-level: the dock and the conversation
// are two windows onto the same call rather than two calls. Customers can still ring at the
// same moment, so every incoming call waits in its own slot until somebody takes it.
const state = reactive<{ call: WhatsappCall | null; ringing: WhatsappCall[]; micReady: boolean }>({
    call: null,
    ringing: [],
    micReady: false,
})

// Meta gives up on an unanswered call after about a minute; a lost terminate webhook must not
// leave a customer listed as ringing for ever.
const RING_TIMEOUT_MS = 90_000

const removeRinging = (callId: number) => {
    state.ringing = state.ringing.filter((ringing) => ringing.id !== callId)
}

const notifyAnsweredElsewhere = (payload: WhatsappCall) =>
    notify({
        title: ctrans("WhatsApp call answered"),
        text: ctrans(":name is handling the call from :phone", {
            name: payload.user_name ?? ctrans("Another customer service"),
            phone: payload.phone_number ?? "",
        }),
        type: "info",
    })

const upsertRinging = (payload: WhatsappCall) => {
    if (state.ringing.some((ringing) => ringing.id === payload.id)) return

    state.ringing = [...state.ringing, payload]
    setTimeout(() => removeRinging(payload.id), RING_TIMEOUT_MS)
}

const busy = ref(false)
const tick = ref(Date.now())

let peer: RTCPeerConnection | null = null
let localStream: MediaStream | null = null
let remoteAudio: HTMLAudioElement | null = null
let ticker: ReturnType<typeof setInterval> | null = null

const iceServers = (): RTCIceServer[] => {
    const configured = (window as any).whatsappCallIceServers

    return Array.isArray(configured) && configured.length
        ? configured
        : [{ urls: "stun:stun.l.google.com:19302" }]
}

const startTicker = () => {
    if (ticker) return
    ticker = setInterval(() => (tick.value = Date.now()), 1000)
}

const stopTicker = () => {
    if (!ticker) return
    clearInterval(ticker)
    ticker = null
}

const teardownMedia = () => {
    localStream?.getTracks().forEach((track) => track.stop())
    localStream = null

    peer?.close()
    peer = null

    if (remoteAudio) {
        remoteAudio.srcObject = null
        remoteAudio.remove()
        remoteAudio = null
    }

    state.micReady = false
    stopTicker()
}

/**
 * Meta answers over the same webhook that opened the call, so there is no trickle channel to
 * send candidates on: the answer has to be complete before it is posted, which means waiting
 * for ICE gathering to finish rather than sending the first answer the browser produces.
 */
const gatherComplete = (connection: RTCPeerConnection): Promise<void> =>
    new Promise((resolve) => {
        if (connection.iceGatheringState === "complete") return resolve()

        const timeout = setTimeout(resolve, 2000)

        connection.addEventListener("icegatheringstatechange", () => {
            if (connection.iceGatheringState === "complete") {
                clearTimeout(timeout)
                resolve()
            }
        })
    })

const openPeer = async (): Promise<RTCPeerConnection> => {
    localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false })
    state.micReady = true

    peer = new RTCPeerConnection({ iceServers: iceServers() })

    localStream.getTracks().forEach((track) => peer!.addTrack(track, localStream!))

    remoteAudio = document.createElement("audio")
    remoteAudio.autoplay = true
    document.body.appendChild(remoteAudio)

    peer.ontrack = (event) => {
        if (remoteAudio) remoteAudio.srcObject = event.streams[0]
    }

    peer.onconnectionstatechange = () => {
        if (peer && ["failed", "closed"].includes(peer.connectionState)) {
            teardownMedia()
        }
    }

    return peer
}

const normaliseSdp = (sdp: string) => sdp.trimEnd() + "\r\n"

const buildAnswer = async (offerSdp: string): Promise<string> => {
    const connection = await openPeer()

    await connection.setRemoteDescription({ type: "offer", sdp: normaliseSdp(offerSdp) })

    const answer = await connection.createAnswer()
    await connection.setLocalDescription(answer)
    await gatherComplete(connection)

    return connection.localDescription?.sdp ?? answer.sdp ?? ""
}

const buildOffer = async (): Promise<string> => {
    const connection = await openPeer()

    const offer = await connection.createOffer({ offerToReceiveAudio: true })
    await connection.setLocalDescription(offer)
    await gatherComplete(connection)

    return connection.localDescription?.sdp ?? offer.sdp ?? ""
}

const fetchRemoteSdp = (organisation: string, metaChatCallId: number): Promise<string | undefined> =>
    axios
        .get(route("grp.org.chat.agents.whatsapp.calls.offer", { organisation, metaChatCall: metaChatCallId }))
        .then((response) => response.data?.data?.remote_sdp)

export const useWhatsappCall = () => {
    const activeCall = computed(() => state.call)
    const ringingCalls = computed(() => state.ringing)
    const call = computed(() => state.call ?? state.ringing[0] ?? null)
    const isRinging = computed(() => call.value?.status === "ringing")
    const isLive = computed(() => state.call?.status === "in_progress")

    const elapsedSeconds = computed(() => {
        if (!state.call?.answered_at) return 0
        void tick.value

        return Math.max(0, Math.floor((Date.now() - new Date(state.call.answered_at).getTime()) / 1000))
    })

    const formattedElapsed = computed(() => {
        const minutes = Math.floor(elapsedSeconds.value / 60)
            .toString()
            .padStart(2, "0")
        const seconds = (elapsedSeconds.value % 60).toString().padStart(2, "0")

        return `${minutes}:${seconds}`
    })

    const isOutgoing = computed(() => call.value?.direction === "business_initiated")

    const statusLabel = computed(() => {
        if (isRinging.value) return isOutgoing.value ? ctrans("Calling…") : ctrans("Incoming WhatsApp call")

        return state.micReady ? ctrans("On the call") : ctrans("Connecting…")
    })

    // The broadcast is the one source of truth for a call's state: a call answered or hung up
    // in another tab has to close this one's media too, or the agent keeps a dead line open.
    // A tab with the conversation open hears the same event on the session and the shop channel.
    const applyBroadcast = (payload: WhatsappCall, organisation?: string) => {
        const isMine = state.call?.id === payload.id

        if (isMine && state.call!.status === payload.status) {
            return
        }

        if (["completed", "missed", "rejected", "failed"].includes(payload.status)) {
            removeRinging(payload.id)

            if (isMine) {
                teardownMedia()
                state.call = null
            }

            return
        }

        // An outgoing call belongs only to the tab that dialled it, the one holding the offer.
        if (payload.direction === "business_initiated" && !isMine && !(peer && !state.call)) {
            return
        }

        if (payload.status === "ringing" && payload.direction === "user_initiated") {
            if (!isMine) upsertRinging(payload)

            return
        }

        // Only the tab that answered holds the media. Everywhere else the ringing stops and
        // the agent is told who picked it up, instead of being shown a line they are not on.
        if (payload.status === "in_progress" && payload.direction === "user_initiated" && !isMine) {
            if (state.ringing.some((ringing) => ringing.id === payload.id)) {
                removeRinging(payload.id)
                notifyAnsweredElsewhere(payload)
            }

            return
        }

        state.call = payload

        if (payload.status === "in_progress") {
            startTicker()

            if (payload.direction === "business_initiated" && organisation && peer && !peer.remoteDescription) {
                const connection = peer
                fetchRemoteSdp(organisation, payload.id)
                    .then((sdp) => sdp && connection.setRemoteDescription({ type: "answer", sdp: normaliseSdp(sdp) }))
                    .catch(() => end(organisation))
            }
        }
    }

    const answer = async (organisation: string, callId?: number) => {
        const incoming = state.ringing.find((ringing) => callId === undefined || ringing.id === callId)

        if (!incoming || state.call || busy.value) return

        busy.value = true
        state.call = incoming
        removeRinging(incoming.id)

        // Two agents can press Answer together; the loser has by then heard the winner's
        // broadcast, so the call is reported as taken rather than put back to ring.
        const giveBack = (message: string) => {
            const takenByColleague = state.call?.status === "in_progress" ? state.call : null
            teardownMedia()
            state.call = null

            if (takenByColleague) {
                notifyAnsweredElsewhere(takenByColleague)

                return
            }

            upsertRinging(incoming)
            notify({ title: ctrans("Something went wrong"), text: message, type: "error" })
        }

        try {
            const offer = await fetchRemoteSdp(organisation, incoming.id)

            if (!offer) throw new Error("missing offer")

            const sdp = await buildAnswer(offer)

            const { data } = await axios.post(
                route("grp.org.chat.agents.whatsapp.calls.answer", {
                    organisation,
                    metaChatCall: incoming.id,
                }),
                { sdp }
            )

            if (!data?.ok) {
                giveBack(data?.message ?? "")

                return
            }

            startTicker()
        } catch (error) {
            giveBack(ctrans("The call could not be answered."))
        } finally {
            busy.value = false
        }
    }

    const dial = async (organisation: string, metaChatSessionUlid: string) => {
        if (state.call || busy.value) return

        busy.value = true

        try {
            const sdp = await buildOffer()

            const { data } = await axios.post(
                route("grp.org.chat.agents.whatsapp.calls.start", {
                    organisation,
                    metaChatSession: metaChatSessionUlid,
                }),
                { sdp }
            )

            if (!data?.ok) {
                teardownMedia()
                notify({ title: ctrans("Something went wrong"), text: data?.message ?? "", type: "error" })
            }
        } catch (error) {
            teardownMedia()
            notify({
                title: ctrans("Something went wrong"),
                text: ctrans("The call could not be started."),
                type: "error",
            })
        } finally {
            busy.value = false
        }
    }

    const end = async (organisation: string, callId?: number) => {
        const target = callId === undefined ? call.value : [state.call, ...state.ringing].find((candidate) => candidate?.id === callId)

        if (!target || busy.value) return

        busy.value = true
        const isMine = state.call?.id === target.id

        try {
            await axios.post(
                route("grp.org.chat.agents.whatsapp.calls.end", { organisation, metaChatCall: target.id })
            )
        } catch (error) {
            notify({
                title: ctrans("Something went wrong"),
                text: ctrans("The call could not be ended."),
                type: "error",
            })
        } finally {
            removeRinging(target.id)

            if (isMine) {
                teardownMedia()
                state.call = null
            }

            busy.value = false
        }
    }

    return {
        call,
        activeCall,
        ringingCalls,
        isRinging,
        isLive,
        busy: computed(() => busy.value),
        micReady: computed(() => state.micReady),
        elapsedSeconds,
        formattedElapsed,
        isOutgoing,
        statusLabel,
        videoAvailable: WHATSAPP_VIDEO_AVAILABLE,
        applyBroadcast,
        answer,
        dial,
        end,
    }
}
