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
    wa_call_id: string
    status: "ringing" | "in_progress" | "completed" | "missed" | "rejected" | "failed"
    direction: "user_initiated" | "business_initiated"
    phone_number: string | null
    user_id: number | null
    duration_seconds: number | null
    answered_at: string | null
    ended_at: string | null
}

// A WhatsApp call is audio. Meta lists video and screen sharing as planned rather than
// released, so there is nothing to negotiate for them and the console offers voice only.
export const WHATSAPP_VIDEO_AVAILABLE = false

// One call at a time per agent, so the state is module-level: the dock and the conversation
// are two windows onto the same call rather than two calls.
const state = reactive<{ call: WhatsappCall | null; micReady: boolean }>({
    call: null,
    micReady: false,
})

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

const buildAnswer = async (offerSdp: string): Promise<string> => {
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

    await peer.setRemoteDescription({ type: "offer", sdp: offerSdp })

    const answer = await peer.createAnswer()
    await peer.setLocalDescription(answer)
    await gatherComplete(peer)

    return peer.localDescription?.sdp ?? answer.sdp ?? ""
}

export const useWhatsappCall = () => {
    const call = computed(() => state.call)
    const isRinging = computed(() => state.call?.status === "ringing")
    const isLive = computed(() => state.call?.status === "in_progress")

    const elapsedSeconds = computed(() => {
        if (!state.call?.answered_at) return 0
        void tick.value

        return Math.max(0, Math.floor((Date.now() - new Date(state.call.answered_at).getTime()) / 1000))
    })

    // The broadcast is the one source of truth for a call's state: a call answered or hung up
    // in another tab has to close this one's media too, or the agent keeps a dead line open.
    const applyBroadcast = (payload: WhatsappCall) => {
        if (state.call && state.call.id !== payload.id && state.call.status === "in_progress") {
            return
        }

        state.call = payload

        if (["completed", "missed", "rejected", "failed"].includes(payload.status)) {
            teardownMedia()
            state.call = null

            return
        }

        if (payload.status === "in_progress") startTicker()
    }

    const answer = async (organisation: string) => {
        if (!state.call || busy.value) return

        busy.value = true

        try {
            const offer = await axios
                .get(route("grp.org.chat.agents.whatsapp.calls.offer", {
                    organisation,
                    metaChatCall: state.call.id,
                }))
                .then((response) => response.data?.data?.remote_sdp)

            if (!offer) throw new Error("missing offer")

            const sdp = await buildAnswer(offer)

            const { data } = await axios.post(
                route("grp.org.chat.agents.whatsapp.calls.answer", {
                    organisation,
                    metaChatCall: state.call.id,
                }),
                { sdp }
            )

            if (!data?.ok) {
                teardownMedia()
                notify({ title: ctrans("Something went wrong"), text: data?.message ?? "", type: "error" })

                return
            }

            startTicker()
        } catch (error) {
            teardownMedia()
            notify({
                title: ctrans("Something went wrong"),
                text: ctrans("The call could not be answered."),
                type: "error",
            })
        } finally {
            busy.value = false
        }
    }

    const end = async (organisation: string) => {
        if (!state.call || busy.value) return

        busy.value = true
        const callId = state.call.id

        try {
            await axios.post(
                route("grp.org.chat.agents.whatsapp.calls.end", { organisation, metaChatCall: callId })
            )
        } catch (error) {
            notify({
                title: ctrans("Something went wrong"),
                text: ctrans("The call could not be ended."),
                type: "error",
            })
        } finally {
            teardownMedia()
            state.call = null
            busy.value = false
        }
    }

    return {
        call,
        isRinging,
        isLive,
        busy: computed(() => busy.value),
        micReady: computed(() => state.micReady),
        elapsedSeconds,
        videoAvailable: WHATSAPP_VIDEO_AVAILABLE,
        applyBroadcast,
        answer,
        end,
    }
}
