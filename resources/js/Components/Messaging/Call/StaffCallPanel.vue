<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, shallowRef, watch } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faMicrophone, faMicrophoneSlash, faVideo, faVideoSlash, faDesktop, faCog, faPhoneSlash, faExpand, faCompress, faExclamationTriangle, faVolumeUp, faSignal, faTachometerAltSlow, faLink, faLock, faLockOpen, faCheck, faTimes, faUserMinus, faPencil, faEraser, faBan, faWindowMaximize } from "@fal"
import Popover from "primevue/popover"
import Select from "primevue/select"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import StaffCallTrack from "@/Components/Messaging/Call/StaffCallTrack.vue"
import StaffCallMarksCanvas from "@/Components/Messaging/Call/StaffCallMarksCanvas.vue"
import { MarkBoard, MARKS_TOPIC, PAGE_MARKS_CHANNEL, encodeMark, markRateLimiter, pointsIn, readMark, type MarkMessage, type PageMarkEvent, type SharedSurface } from "@/Components/Messaging/Call/staffCallMarks"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { useStaffCalls, type StaffCallJoin } from "@/Stores/staff-calls"
import { rememberDataMode, rememberedDataMode, staffCallQuality, staffCallQualitySampler, type StaffCallDataMode } from "@/Components/Messaging/Call/staffCallQuality"
import type { LocalTrack, Participant, Room, Track } from "livekit-client"

library.add(faMicrophone, faMicrophoneSlash, faVideo, faVideoSlash, faDesktop, faCog, faPhoneSlash, faExpand, faCompress, faExclamationTriangle, faVolumeUp, faSignal, faTachometerAltSlow, faLink, faLock, faLockOpen, faCheck, faTimes, faUserMinus, faPencil, faEraser, faBan, faWindowMaximize)

const props = defineProps<{
    join: StaffCallJoin
    startExpanded?: boolean
}>()

const store = useStaffCalls()
const room = shallowRef<Room | null>(null)
let livekit: typeof import("livekit-client") | null = null
let quality: ReturnType<typeof staffCallQuality> | null = null
const dataMode = ref<StaffCallDataMode>(rememberedDataMode())
const dataModeOptions = [
    { label: ctrans("Auto"), value: "auto", icon: "fal fa-signal" },
    { label: ctrans("Low data"), value: "low", icon: "fal fa-tachometer-alt-slow" },
    { label: ctrans("Audio only"), value: "audio", icon: "fal fa-microphone" },
]
let isLeaving = false
let pingTimer: ReturnType<typeof setInterval> | null = null
let sampleQuality: ReturnType<typeof staffCallQualitySampler> | null = null
let ringTimer: ReturnType<typeof setTimeout> | null = null

const version = ref(0)
const bump = () => version.value++
const connection = ref<"connecting" | "connected" | "reconnecting" | "failed">("connecting")
const connectError = ref<string | null>(null)
const mediaError = ref<string | null>(null)
const audioBlocked = ref(false)
const isExpanded = ref(!!props.startExpanded)
const audioSink = ref<HTMLElement | null>(null)
const screenBox = ref<HTMLElement | null>(null)
const devicesPopover = ref()
const devices = reactive<Record<MediaDeviceKind, { label: string; value: string }[]>>({ audioinput: [], videoinput: [], audiooutput: [] })
const chosenDevice = reactive<Record<MediaDeviceKind, string>>({ audioinput: "default", videoinput: "", audiooutput: "default" })

const canShareScreen = typeof navigator !== "undefined" && !!navigator.mediaDevices?.getDisplayMedia && !window.matchMedia("(pointer: coarse)").matches
const canChooseSpeaker = typeof HTMLMediaElement !== "undefined" && "setSinkId" in HTMLMediaElement.prototype

const call = computed(() => props.join.call)
const isDm = computed(() => call.value.conversation_type === "dm")
const isDirect = computed(() => !!call.value.is_direct)
const isGuest = computed(() => !!store.guest)
const isWaitingForAnswer = computed(() => isDm.value && !isGuest.value && !call.value.answered_at && call.value.started_by_user_id === store.myId)

const linkState = ref<"idle" | "copying" | "copied" | "failed">("idle")
const copyMeetingLink = async () => {
    linkState.value = "copying"
    try {
        const url = await store.shareLink(call.value)
        if (!url) throw new Error()
        await navigator.clipboard.writeText(url)
        linkState.value = "copied"
        setTimeout(() => (linkState.value = "idle"), 2500)
    } catch {
        linkState.value = "failed"
    }
}

const recordingBy = ref<string | null>(null)
const readRecordingMark = (metadata: string | undefined) => {
    try {
        recordingBy.value = metadata ? JSON.parse(metadata)?.recording?.by ?? null : null
    } catch {
        recordingBy.value = null
    }
}
const recordPopover = ref()
const keepAudioMonths = ref(0)
const keepAudioOptions = [
    { label: ctrans("Delete the audio once the summary is written"), value: 0 },
    { label: ctrans("Keep the audio for 1 month"), value: 1 },
    { label: ctrans("Keep the audio for 3 months"), value: 3 },
    { label: ctrans("Keep the audio for 6 months"), value: 6 },
    { label: ctrans("Keep the audio for 12 months"), value: 12 },
]
const recordingBusy = ref(false)
const recordingError = ref<string | null>(null)
const setRecording = async (record: boolean) => {
    recordingBusy.value = true
    recordingError.value = null
    if (!(await store.setRecording(call.value, record, keepAudioMonths.value))) {
        recordingError.value = record ? ctrans("Could not start recording. Try again.") : ctrans("Could not stop recording. Try again.")
    }
    recordingBusy.value = false
    recordPopover.value?.hide()
}

const participants = computed<Participant[]>(() => {
    version.value
    if (!room.value) return []
    return [room.value.localParticipant, ...room.value.remoteParticipants.values()]
})

const trackOf = (participant: Participant, source: Track.Source): Track | null => {
    const publication = participant.getTrackPublication(source)
    return publication && !publication.isMuted && publication.track ? publication.track : null
}

const local = computed(() => {
    version.value
    const participant = room.value?.localParticipant
    return {
        microphone: !!participant?.isMicrophoneEnabled,
        camera: !!participant?.isCameraEnabled,
        screen: !!participant?.isScreenShareEnabled,
    }
})

const sharedScreen = computed(() => {
    version.value
    if (!livekit || !room.value) return null
    const me = room.value.localParticipant
    const myScreen = trackOf(me, livekit.Track.Source.ScreenShare)
    if (myScreen) return { identity: me.identity, name: ctrans("You"), track: myScreen, isMine: true }
    const sharers = participants.value.filter((participant) => !participant.isLocal && trackOf(participant, livekit!.Track.Source.ScreenShare))
    const sharer = sharers[sharers.length - 1]
    return sharer ? { identity: sharer.identity, name: sharer.name || ctrans("Guest"), track: trackOf(sharer, livekit.Track.Source.ScreenShare)!, isMine: false } : null
})

const PAGE_MARKS_KEY = "staff-call-marks-on-page"
const marks = new MarkBoard()
const allowMarks = markRateLimiter()
const screenView = ref<{ element: HTMLVideoElement | null } | null>(null)
const pencil = ref(false)
const othersDrawingOff = ref(false)
const marksOnMyPage = ref((() => {
    try {
        return localStorage.getItem(PAGE_MARKS_KEY) !== "off"
    } catch {
        return true
    }
})())
const canDraw = computed(() => !!sharedScreen.value && (sharedScreen.value.isMine || !othersDrawingOff.value))
const isDrawing = computed(() => pencil.value && canDraw.value)
const pageChannel = typeof BroadcastChannel !== "undefined" ? new BroadcastChannel(PAGE_MARKS_CHANNEL) : null
const postToPage = (event: PageMarkEvent) => pageChannel?.postMessage(event)

const relayToPage = (identity: string, name: string, mark: MarkMessage) => {
    const screen = sharedScreen.value
    if (!screen?.isMine || !marksOnMyPage.value) return
    const track = screen.track.mediaStreamTrack
    const settings = track.getSettings() as MediaTrackSettings & { displaySurface?: string }
    if (!settings.width || !settings.height || !["browser", "window", "monitor"].includes(settings.displaySurface ?? "")) return
    postToPage({
        type: "mark",
        identity,
        name,
        mark,
        surface: settings.displaySurface as SharedSurface,
        width: settings.width,
        height: settings.height,
        handle: (track as MediaStreamTrack & { getCaptureHandle?: () => { handle?: string } | null }).getCaptureHandle?.()?.handle ?? null,
    })
}

const showMark = (identity: string, name: string, mark: MarkMessage) => {
    marks.apply(identity, name, mark)
    relayToPage(identity, name, mark)
}

const sendMark = (mark: MarkMessage, reliable = true) => {
    room.value?.localParticipant.publishData(encodeMark(mark), { reliable, topic: MARKS_TOPIC }).catch(() => null)
}

const showAndSendMine = (mark: MarkMessage, reliable = true) => {
    const me = room.value!.localParticipant
    showMark(me.identity, me.name || ctrans("You"), mark)
    sendMark(mark, reliable)
}

const receiveMark = (payload: Uint8Array, participant?: Participant, topic?: string) => {
    if (topic !== MARKS_TOPIC || !participant) return
    const mark = readMark(payload)
    const screen = sharedScreen.value
    if (!mark || !screen || mark.s !== screen.identity) return
    if (mark.t === "o") {
        if (participant.identity === screen.identity) othersDrawingOff.value = mark.off
        return
    }
    if (othersDrawingOff.value && participant.identity !== screen.identity) return
    if (!allowMarks(participant.identity, pointsIn(mark))) return
    showMark(participant.identity, participant.name || ctrans("Guest"), mark)
}

let lastPointerAt = 0
let strokeKey = 0
let strokeBatch: number[] = []
let strokeTimer: ReturnType<typeof setInterval> | null = null
let lastStrokePoint: [number, number] | null = null

const pointAt = (x: number, y: number) => {
    const now = performance.now()
    if (now - lastPointerAt < 50) return
    lastPointerAt = now
    showAndSendMine({ t: "p", s: sharedScreen.value!.identity, x, y }, false)
}

const pointerGone = () => showAndSendMine({ t: "h", s: sharedScreen.value!.identity }, false)

const sendStrokeBatch = (isLast = false) => {
    if (!strokeBatch.length && !isLast) return
    sendMark({ t: "d", s: sharedScreen.value!.identity, k: strokeKey, p: strokeBatch, ...(isLast ? { e: 1 as const } : {}) })
    strokeBatch = []
}

const strokePoint = (x: number, y: number) => {
    if (lastStrokePoint && Math.hypot(x - lastStrokePoint[0], y - lastStrokePoint[1]) < 0.002) return
    lastStrokePoint = [x, y]
    const me = room.value!.localParticipant
    showMark(me.identity, me.name || ctrans("You"), { t: "d", s: sharedScreen.value!.identity, k: strokeKey, p: [x, y] })
    strokeBatch.push(x, y)
    if (strokeBatch.length >= 120) sendStrokeBatch()
    strokeTimer ??= setInterval(sendStrokeBatch, 50)
}

const strokeEnd = () => {
    if (strokeTimer) clearInterval(strokeTimer)
    strokeTimer = null
    if (sharedScreen.value) {
        sendStrokeBatch(true)
        const me = room.value!.localParticipant
        showMark(me.identity, me.name || ctrans("You"), { t: "d", s: sharedScreen.value.identity, k: strokeKey, p: [], e: 1 })
    }
    strokeKey++
    lastStrokePoint = null
}

const clearMarks = () => showAndSendMine({ t: "c", s: sharedScreen.value!.identity })

const switchOthersDrawing = () => {
    othersDrawingOff.value = !othersDrawingOff.value
    sendMark({ t: "o", s: room.value!.localParticipant.identity, off: othersDrawingOff.value })
}

const switchMarksOnMyPage = () => {
    marksOnMyPage.value = !marksOnMyPage.value
    try {
        localStorage.setItem(PAGE_MARKS_KEY, marksOnMyPage.value ? "on" : "off")
    } catch { }
    if (!marksOnMyPage.value) postToPage({ type: "end" })
}

const tellNewcomerDrawingIsOff = () => {
    if (sharedScreen.value?.isMine && othersDrawingOff.value) {
        setTimeout(() => sendMark({ t: "o", s: room.value!.localParticipant.identity, off: true }), 1000)
    }
}

watch(() => sharedScreen.value?.identity ?? null, () => {
    marks.clear()
    othersDrawingOff.value = false
    pencil.value = false
    postToPage({ type: "end" })
})

const tiles = computed(() => {
    version.value
    if (!livekit) return []
    return participants.value.map((participant) => ({
        key: participant.identity,
        name: participant.isLocal ? ctrans("You") : participant.name || ctrans("Guest"),
        isGuest: participant.identity.startsWith("guest-"),
        initial: (participant.name || participant.identity || "?").charAt(0).toUpperCase(),
        camera: trackOf(participant, livekit!.Track.Source.Camera),
        isLocal: participant.isLocal,
        isSpeaking: participant.isSpeaking,
        isMuted: !participant.isMicrophoneEnabled,
        signal: CONNECTION_BARS[participant.connectionQuality] ?? null,
    }))
})

const CONNECTION_BARS: Record<string, { bars: number; colour: string; label: string }> = {
    excellent: { bars: 3, colour: "bg-green-500", label: ctrans("Excellent connection") },
    good: { bars: 2, colour: "bg-green-500", label: ctrans("Good connection") },
    poor: { bars: 1, colour: "bg-amber-400", label: ctrans("Weak connection") },
    lost: { bars: 0, colour: "bg-red-500", label: ctrans("Connection lost") },
}

const mediaFailureText = (error: unknown, kind: "microphone" | "camera" | "screen"): string | null => {
    const failure = livekit?.MediaDeviceFailure.getFailure(error)
    if ((error as DOMException)?.name === "NotAllowedError" && kind === "screen") return null
    if (failure === "PermissionDenied") {
        return kind === "camera"
            ? ctrans("The browser blocked the camera. Allow it from the icon in the address bar, then turn the camera on again.")
            : ctrans("The browser blocked the microphone. Allow it from the icon in the address bar, then turn the microphone on again. You can still hear the others.")
    }
    if (failure === "NotFound") return kind === "camera" ? ctrans("No camera was found.") : ctrans("No microphone was found.")
    if (failure === "DeviceInUse") return kind === "camera" ? ctrans("The camera is in use by another program.") : ctrans("The microphone is in use by another program.")
    return ctrans("Could not start the :device.", { device: kind === "camera" ? ctrans("camera") : kind === "screen" ? ctrans("screen share") : ctrans("microphone") })
}

const runMedia = async (kind: "microphone" | "camera" | "screen", action: () => Promise<unknown>) => {
    mediaError.value = null
    try {
        await action()
        await refreshDevices()
    } catch (error) {
        mediaError.value = mediaFailureText(error, kind)
    }
    bump()
}

const toggleMicrophone = () => runMedia("microphone", () => room.value!.localParticipant.setMicrophoneEnabled(!local.value.microphone, chosenDevice.audioinput ? { deviceId: chosenDevice.audioinput } : undefined))
const othersInCall = () => room.value?.remoteParticipants.size ?? Math.max(call.value.present_user_ids.length - 1, 1)

const cameraOptions = () => {
    const options = quality!.cameraFor(dataMode.value, othersInCall())
    return { capture: { ...options.capture, ...(chosenDevice.videoinput ? { deviceId: chosenDevice.videoinput } : {}) }, publish: options.publish }
}

const startCamera = async () => {
    const participant = room.value!.localParticipant
    const existing = participant.getTrackPublication(livekit!.Track.Source.Camera)
    if (existing?.track) await participant.unpublishTrack(existing.track as LocalTrack)
    const { capture, publish } = cameraOptions()
    await participant.setCameraEnabled(true, capture, publish)
}

const toggleCamera = () => runMedia("camera", () => (local.value.camera ? room.value!.localParticipant.setCameraEnabled(false) : startCamera()))
const toggleScreen = () => runMedia("screen", () => room.value!.localParticipant.setScreenShareEnabled(!local.value.screen, {
    audio: true,
    systemAudio: "include",
    contentHint: quality!.screen.contentHint,
    selfBrowserSurface: "exclude",
    resolution: quality!.screen.capture.resolution,
}, {
    screenShareEncoding: quality!.screen.capture.encoding,
    degradationPreference: quality!.screen.degradationPreference,
}))

const applyRemoteDataMode = () => {
    if (!room.value || !livekit) return
    const { Track: LkTrack, VideoQuality } = livekit
    for (const participant of room.value.remoteParticipants.values()) {
        for (const publication of participant.trackPublications.values()) {
            if (publication.kind !== LkTrack.Kind.Video) continue
            const isScreen = publication.source === LkTrack.Source.ScreenShare
            if (dataMode.value === "audio" && !isScreen) {
                publication.setSubscribed(false)
                continue
            }
            publication.setSubscribed(true)
            publication.setVideoQuality(dataMode.value === "low" ? (isScreen ? quality!.screen.lowDataQuality : VideoQuality.LOW) : VideoQuality.HIGH)
        }
    }
}

const chooseDataMode = async (mode: StaffCallDataMode) => {
    dataMode.value = mode
    rememberDataMode(mode)
    applyRemoteDataMode()
    if (!room.value || !local.value.camera) return
    await runMedia("camera", () => (mode === "audio" ? room.value!.localParticipant.setCameraEnabled(false) : startCamera()))
}

const refreshDevices = async () => {
    if (!livekit) return
    for (const kind of ["audioinput", "videoinput", "audiooutput"] as MediaDeviceKind[]) {
        try {
            const list = await livekit.Room.getLocalDevices(kind, false)
            devices[kind] = list.filter((device) => device.deviceId).map((device, index) => ({ label: device.label || `${kind} ${index + 1}`, value: device.deviceId }))
        } catch {
            devices[kind] = []
        }
        const active = room.value?.getActiveDevice(kind)
        if (active) chosenDevice[kind] = active
    }
}

const chooseDevice = async (kind: MediaDeviceKind, deviceId: string) => {
    chosenDevice[kind] = deviceId
    try {
        await room.value?.switchActiveDevice(kind, deviceId)
    } catch (error) {
        mediaError.value = mediaFailureText(error, kind === "videoinput" ? "camera" : "microphone")
    }
}

const startAudio = async () => {
    await room.value?.startAudio()
    audioBlocked.value = !room.value?.canPlaybackAudio
}

const showScreenFull = () => screenBox.value?.requestFullscreen?.()

const hangUp = async (reason: "ring_timeout" | null = null) => {
    if (isLeaving) return
    isLeaving = true
    const leaving = store.leave(reason)
    try {
        await room.value?.disconnect()
    } catch { }
    await leaving
}

const leaveOnPageHide = () => store.leaveOnPageHide()

let aloneTimer: ReturnType<typeof setTimeout> | null = null
const endWhenLeftAlone = () => {
    if (aloneTimer) clearTimeout(aloneTimer)
    aloneTimer = setTimeout(() => {
        if (isDirect.value && call.value.answered_at && room.value && room.value.remoteParticipants.size === 0) hangUp()
    }, 3000)
}

const warnBeforeUnload = (event: BeforeUnloadEvent) => {
    event.preventDefault()
}

const connect = async () => {
    livekit = await import("livekit-client")
    quality = staffCallQuality(livekit)
    const { Room, RoomEvent, Track: LkTrack } = livekit
    const lkRoom = new Room(quality.room)
    room.value = lkRoom
    sampleQuality = staffCallQualitySampler(lkRoom)

    lkRoom
        .on(RoomEvent.TrackSubscribed, (track) => {
            if (track.kind === "audio") {
                const element = track.attach()
                audioSink.value?.appendChild(element)
            }
            bump()
        })
        .on(RoomEvent.TrackUnsubscribed, (track) => {
            track.detach().forEach((element) => element.remove())
            bump()
        })
        .on(RoomEvent.ParticipantConnected, () => {
            bump()
            tellNewcomerDrawingIsOff()
        })
        .on(RoomEvent.DataReceived, (payload, participant, _kind, topic) => receiveMark(payload, participant, topic))
        .on(RoomEvent.ParticipantDisconnected, () => {
            bump()
            endWhenLeftAlone()
        })
        .on(RoomEvent.TrackPublished, () => {
            applyRemoteDataMode()
            bump()
        })
        .on(RoomEvent.ConnectionQualityChanged, bump)
        .on(RoomEvent.RoomMetadataChanged, readRecordingMark)
        .on(RoomEvent.TrackUnpublished, bump)
        .on(RoomEvent.LocalTrackPublished, bump)
        .on(RoomEvent.LocalTrackUnpublished, bump)
        .on(RoomEvent.TrackMuted, bump)
        .on(RoomEvent.TrackUnmuted, bump)
        .on(RoomEvent.ActiveSpeakersChanged, bump)
        .on(RoomEvent.MediaDevicesChanged, refreshDevices)
        .on(RoomEvent.AudioPlaybackStatusChanged, () => (audioBlocked.value = !lkRoom.canPlaybackAudio))
        .on(RoomEvent.Reconnecting, () => (connection.value = "reconnecting"))
        .on(RoomEvent.SignalReconnecting, () => (connection.value = "reconnecting"))
        .on(RoomEvent.Reconnected, () => (connection.value = "connected"))
        .on(RoomEvent.Disconnected, (reason) => {
            if (isLeaving) return
            if (reason === livekit!.DisconnectReason.DUPLICATE_IDENTITY) {
                store.active = null
                return
            }
            store.leave()
        })

    const withCamera = props.join.withCamera && dataMode.value !== "audio"
    const cameraAtStart = quality.cameraFor(dataMode.value, othersInCall())
    const capture = <T,>(kind: "microphone" | "camera", start: Promise<T>) => start.catch((error) => {
        mediaError.value = mediaError.value ?? mediaFailureText(error, kind)
        return null
    })
    const microphoneTrack = capture("microphone", livekit.createLocalAudioTrack(quality.room.audioCaptureDefaults))
    const cameraTrack = withCamera ? capture("camera", livekit.createLocalVideoTrack(cameraAtStart.capture)) : Promise.resolve(null)

    try {
        await lkRoom.connect(props.join.url, props.join.token)
    } catch {
        connection.value = "failed"
        connectError.value = ctrans("Could not connect to the call server.")
        store.error = { conversationUlid: call.value.conversation_ulid, text: ctrans("Could not connect to the call server.") }
        hangUp()
        ;(await microphoneTrack)?.stop()
        ;(await cameraTrack)?.stop()
        props.join.screen?.getTracks().forEach((track) => track.stop())
        return
    }
    connection.value = "connected"
    readRecordingMark(lkRoom.metadata)
    audioBlocked.value = !lkRoom.canPlaybackAudio
    applyRemoteDataMode()

    const [microphone, camera] = await Promise.all([microphoneTrack, cameraTrack])
    const screenVideo = props.join.screen?.getVideoTracks().find((track) => track.readyState === "live")
    const screenAudio = props.join.screen?.getAudioTracks().find((track) => track.readyState === "live")
    await Promise.all([
        microphone && lkRoom.localParticipant.publishTrack(microphone, { source: LkTrack.Source.Microphone }).catch((error) => (mediaError.value = mediaFailureText(error, "microphone"))),
        camera && lkRoom.localParticipant.publishTrack(camera, { ...cameraAtStart.publish, source: LkTrack.Source.Camera }).catch((error) => (mediaError.value = mediaFailureText(error, "camera"))),
        screenVideo && lkRoom.localParticipant.publishTrack(screenVideo, {
            source: LkTrack.Source.ScreenShare,
            screenShareEncoding: quality.screen.capture.encoding,
            degradationPreference: quality.screen.degradationPreference,
        }).catch((error) => (mediaError.value = mediaFailureText(error, "screen"))),
        screenAudio && lkRoom.localParticipant.publishTrack(screenAudio, { source: LkTrack.Source.ScreenShareAudio }).catch(() => null),
    ])
    bump()
    refreshDevices()
}

watch(() => [call.value.ended_at, call.value.present_user_ids.join(","), call.value.answered_at], () => {
    const others = call.value.present_user_ids.filter((id) => id !== store.myId)
    const otherJoinedBefore = call.value.joined_user_ids.some((id) => id !== store.myId)
    const droppedByServer = !isGuest.value && connection.value === "connected" && !call.value.present_user_ids.includes(store.myId as number)
    if (call.value.ended_at || droppedByServer || (isDirect.value && call.value.answered_at && otherJoinedBefore && !others.length)) {
        hangUp()
    }
})

onMounted(() => {
    window.addEventListener("beforeunload", warnBeforeUnload)
    window.addEventListener("pagehide", leaveOnPageHide)
    pingTimer = setInterval(async () => {
        const sample = sampleQuality ? await Promise.race([sampleQuality().catch(() => null), new Promise<null>((resolve) => setTimeout(() => resolve(null), 2000))]) : null
        store.ping(sample)
    }, store.pingSeconds * 1000)
    if (isWaitingForAnswer.value) {
        const ringLeft = store.ringSeconds * 1000 - (Date.now() - (call.value.local_started_at ?? Date.now()))
        ringTimer = setTimeout(() => {
            if (isWaitingForAnswer.value) hangUp("ring_timeout")
        }, Math.max(ringLeft, 1000))
    }
    connect()
})

onBeforeUnmount(() => {
    window.removeEventListener("beforeunload", warnBeforeUnload)
    window.removeEventListener("pagehide", leaveOnPageHide)
    if (aloneTimer) clearTimeout(aloneTimer)
    if (pingTimer) clearInterval(pingTimer)
    if (ringTimer) clearTimeout(ringTimer)
    if (strokeTimer) clearInterval(strokeTimer)
    postToPage({ type: "end" })
    pageChannel?.close()
    isLeaving = true
    props.join.screen?.getTracks().forEach((track) => track.stop())
    room.value?.disconnect()
})

const markButtonClass = "flex h-7 w-7 items-center justify-center rounded bg-black/60 text-white transition duration-200 hover:bg-black/80 disabled:opacity-40"
const controlClass = "flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition duration-200 hover:bg-white/20 active:!bg-white/30 disabled:opacity-40"
</script>

<template>
    <div
        class="fixed z-[70] flex flex-col overflow-hidden rounded-lg bg-gray-900 text-white shadow-2xl ring-1 ring-black/20"
        :class="isExpanded ? 'inset-2 md:inset-6' : 'bottom-3 right-3 w-[calc(100vw-1.5rem)] max-w-sm'"
        role="dialog"
        :aria-label="ctrans('Call')">
        <div class="flex items-center gap-2 px-3 py-2 text-sm">
            <span class="h-2 w-2 shrink-0 rounded-full" :class="connection === 'connected' ? 'bg-green-500' : 'bg-amber-400'" />
            <span class="min-w-0 flex-1 truncate font-medium">
                <template v-if="isWaitingForAnswer">{{ ctrans("Calling…") }}</template>
                <template v-else>{{ call.conversation_name || (isGuest || call.conversation_type === "meeting" ? ctrans("Meeting") : call.started_by_user_id === store.myId ? ctrans("Call") : ctrans("Call with :name", { name: call.started_by_name })) }}</template>
            </span>
            <span v-if="connection === 'reconnecting'" class="flex items-center gap-1 whitespace-nowrap text-xs text-amber-300">
                <LoadingIcon />
                {{ ctrans("Reconnecting…") }}
            </span>
            <button type="button" v-tooltip="isExpanded ? ctrans('Smaller') : ctrans('Bigger')" :aria-label="isExpanded ? ctrans('Smaller') : ctrans('Bigger')" class="flex h-7 w-7 items-center justify-center rounded text-gray-300 hover:bg-white/10" @click="isExpanded = !isExpanded">
                <FontAwesomeIcon :icon="isExpanded ? 'fal fa-compress' : 'fal fa-expand'" fixed-width aria-hidden="true" />
            </button>
        </div>

        <div v-if="recordingBy" class="flex items-center gap-2 bg-red-600 px-3 py-1 text-xs text-white" role="status">
            <span class="h-2 w-2 shrink-0 animate-pulse rounded-full bg-white" aria-hidden="true" />
            <span class="flex-1">
                <template v-if="isGuest">{{ ctrans("This call is being recorded and transcribed.") }}</template>
                <template v-else>{{ ctrans("Recording, started by :name. Everyone in the call gets the transcript and a summary.", { name: recordingBy }) }}</template>
            </span>
            <button v-if="!isGuest && call.can_record" type="button" class="font-semibold underline" :disabled="recordingBusy" @click="setRecording(false)">{{ ctrans("Stop") }}</button>
        </div>

        <div v-if="!isGuest" class="flex flex-col gap-1 border-b border-white/10 px-3 py-1.5 text-xs">
            <div class="flex items-center gap-2">
                <button type="button" class="flex items-center gap-1.5 rounded px-1.5 py-1 text-gray-200 hover:bg-white/10" :disabled="linkState === 'copying'" @click="copyMeetingLink">
                    <FontAwesomeIcon :icon="linkState === 'copied' ? 'fal fa-check' : 'fal fa-link'" :class="linkState === 'copied' && 'text-green-400'" fixed-width aria-hidden="true" />
                    {{ linkState === "copied" ? ctrans("Copied") : ctrans("Copy meeting link") }}
                </button>
                <span v-if="linkState === 'failed'" class="text-red-300">{{ ctrans("Could not copy the link.") }}</span>
                <button
                    v-if="call.can_record && !recordingBy"
                    type="button"
                    class="flex items-center gap-1.5 rounded px-1.5 py-1 text-gray-200 hover:bg-white/10"
                    v-tooltip="ctrans('Record the call for a transcript and a summary')"
                    @click="recordPopover?.toggle($event)">
                    <span class="h-2 w-2 rounded-full bg-red-500" aria-hidden="true" />
                    {{ ctrans("Record") }}
                </button>
                <span v-if="recordingError" class="text-red-300">{{ recordingError }}</span>
                <Popover ref="recordPopover">
                    <div class="flex w-72 flex-col gap-3 text-sm">
                        <p class="text-gray-600">{{ ctrans("Everyone in the call sees that it is being recorded. The transcript and summary go to the people in the call.") }}</p>
                        <Select v-model="keepAudioMonths" :options="keepAudioOptions" option-label="label" option-value="value" class="w-full" />
                        <button
                            type="button"
                            class="flex items-center justify-center gap-2 rounded bg-red-600 px-3 py-1.5 font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                            :disabled="recordingBusy"
                            @click="setRecording(true)">
                            <LoadingIcon v-if="recordingBusy" />
                            {{ ctrans("Start recording") }}
                        </button>
                    </div>
                </Popover>
                <button
                    v-if="call.meeting_url"
                    type="button"
                    class="ml-auto flex items-center gap-1.5 rounded px-1.5 py-1 text-gray-200 hover:bg-white/10"
                    v-tooltip="call.meeting_locked ? ctrans('Locked: nobody new can ask to join. Colleagues can still join.') : ctrans('Lock the meeting so nobody new can ask to join')"
                    @click="store.lockMeeting(call, !call.meeting_locked)">
                    <FontAwesomeIcon :icon="call.meeting_locked ? 'fal fa-lock' : 'fal fa-lock-open'" :class="call.meeting_locked && 'text-amber-300'" fixed-width aria-hidden="true" />
                    {{ call.meeting_locked ? ctrans("Locked") : ctrans("Lock") }}
                </button>
            </div>
            <div v-for="guest in call.guests_waiting" :key="guest.ulid" class="flex items-center gap-2 rounded bg-white/10 px-2 py-1.5" role="alert">
                <span class="min-w-0 flex-1 truncate">{{ ctrans(":name wants to join", { name: guest.name }) }}</span>
                <button type="button" class="flex items-center gap-1 rounded px-1.5 py-0.5 hover:bg-white/10" @click="store.decideGuest(call, guest.ulid, 'deny')">
                    <FontAwesomeIcon icon="fal fa-times" class="text-red-400" fixed-width aria-hidden="true" />
                    {{ ctrans("Deny") }}
                </button>
                <button type="button" class="flex items-center gap-1 rounded px-1.5 py-0.5 hover:bg-white/10" @click="store.decideGuest(call, guest.ulid, 'admit')">
                    <FontAwesomeIcon icon="fal fa-check" class="text-green-400" fixed-width aria-hidden="true" />
                    {{ ctrans("Admit") }}
                </button>
            </div>
            <div v-for="guest in call.guests_in" :key="guest.ulid" class="flex items-center gap-2 px-2 text-gray-300">
                <span class="min-w-0 flex-1 truncate">{{ ctrans(":name (guest)", { name: guest.name }) }}</span>
                <button type="button" class="flex items-center gap-1 rounded px-1.5 py-0.5 hover:bg-white/10" @click="store.decideGuest(call, guest.ulid, 'remove')">
                    <FontAwesomeIcon icon="fal fa-user-minus" class="text-red-400" fixed-width aria-hidden="true" />
                    {{ ctrans("Remove") }}
                </button>
            </div>
        </div>

        <div v-if="local.screen" class="flex items-center gap-2 bg-[var(--app-accent)] px-3 py-1 text-xs text-[var(--app-accent-text)]">
            <FontAwesomeIcon icon="fal fa-desktop" fixed-width aria-hidden="true" />
            <span class="flex-1">{{ ctrans("You are sharing your screen") }}</span>
            <button type="button" class="font-semibold underline" @click="toggleScreen">{{ ctrans("Stop sharing") }}</button>
        </div>

        <div class="flex min-h-0 flex-1 flex-col gap-2 p-2" :class="isExpanded ? '' : 'max-h-[60vh]'">
            <div v-if="connection === 'connecting'" class="flex h-40 items-center justify-center gap-2 text-sm text-gray-300">
                <LoadingIcon />
                {{ ctrans("Connecting…") }}
            </div>
            <div v-else-if="connection === 'failed'" class="flex h-40 items-center justify-center px-4 text-center text-sm text-amber-200">{{ connectError }}</div>
            <template v-else>
                <div
                    v-if="sharedScreen"
                    ref="screenBox"
                    v-tooltip="isDrawing ? null : ctrans('Click to see full size')"
                    class="relative min-h-0 flex-1 overflow-hidden rounded bg-black"
                    :class="[isExpanded ? '' : 'aspect-video flex-none', !isDrawing && 'cursor-zoom-in']"
                    @click="!isDrawing && showScreenFull()">
                    <StaffCallTrack ref="screenView" :track="sharedScreen.track" fit="contain" />
                    <StaffCallMarksCanvas
                        :board="marks"
                        :video="screenView?.element ?? null"
                        :drawing="isDrawing"
                        @pointer="pointAt"
                        @pointer-gone="pointerGone"
                        @stroke-point="strokePoint"
                        @stroke-end="strokeEnd" />
                    <span class="pointer-events-none absolute left-2 top-2 rounded bg-black/60 px-1.5 py-0.5 text-xs">{{ sharedScreen.isMine ? ctrans("Your screen") : ctrans(":name's screen", { name: sharedScreen.name }) }}</span>
                    <div class="absolute right-2 top-2 flex gap-1" @click.stop>
                        <button
                            type="button"
                            :class="[markButtonClass, isDrawing && '!bg-[var(--app-accent)] !text-[var(--app-accent-text)]']"
                            :disabled="!canDraw"
                            :aria-pressed="isDrawing"
                            :aria-label="ctrans('Draw and point')"
                            v-tooltip="canDraw ? ctrans('Draw and point on the shared screen. Everyone in the call sees it. The person sharing sees it here in the call panel and on their aiku pages, never on other programs.') : ctrans(':name switched drawing off', { name: sharedScreen.name })"
                            @click="pencil = !pencil">
                            <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                        </button>
                        <button v-if="isDrawing" type="button" :class="markButtonClass" :aria-label="ctrans('Clear drawings')" v-tooltip="ctrans('Clear all drawings for everyone')" @click="clearMarks">
                            <FontAwesomeIcon icon="fal fa-eraser" fixed-width aria-hidden="true" />
                        </button>
                        <template v-if="sharedScreen.isMine">
                            <button
                                type="button"
                                :class="[markButtonClass, othersDrawingOff && '!bg-amber-500 !text-black']"
                                :aria-pressed="othersDrawingOff"
                                :aria-label="ctrans('Drawing off for others')"
                                v-tooltip="othersDrawingOff ? ctrans('Others cannot draw on your screen. Click to let them.') : ctrans('Stop others drawing on your screen')"
                                @click="switchOthersDrawing">
                                <FontAwesomeIcon icon="fal fa-ban" fixed-width aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                :class="[markButtonClass, marksOnMyPage && '!bg-white/30']"
                                :aria-pressed="marksOnMyPage"
                                :aria-label="ctrans('Marks on my aiku page')"
                                v-tooltip="marksOnMyPage ? ctrans('Marks also show on your aiku page when it is what you share. Click to show them only here.') : ctrans('Also show marks on your aiku page when it is what you share')"
                                @click="switchMarksOnMyPage">
                                <FontAwesomeIcon icon="fal fa-window-maximize" fixed-width aria-hidden="true" />
                            </button>
                        </template>
                    </div>
                </div>
                <div class="grid gap-2" :class="sharedScreen ? 'grid-flow-col auto-cols-[7rem] overflow-x-auto' : tiles.length > 2 ? 'grid-cols-2 md:grid-cols-3' : 'grid-cols-2'">
                    <div
                        v-for="tile in tiles"
                        :key="tile.key"
                        class="relative aspect-video overflow-hidden rounded bg-gray-800 ring-2 transition duration-150"
                        :class="tile.isSpeaking ? 'ring-[var(--app-accent)]' : 'ring-transparent'">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-600 text-lg font-semibold">{{ tile.initial }}</span>
                        </div>
                        <StaffCallTrack v-if="tile.camera" :track="tile.camera" :mirror="tile.isLocal" class="absolute inset-0" />
                        <span v-if="tile.signal" v-tooltip="tile.signal.label" class="absolute right-1 top-1 flex h-3 items-end gap-px rounded bg-black/50 px-1 py-0.5" :aria-label="tile.signal.label">
                            <span v-for="bar in 3" :key="bar" class="w-0.5 rounded-sm" :class="[bar <= tile.signal.bars ? tile.signal.colour : 'bg-white/30', bar === 1 ? 'h-1' : bar === 2 ? 'h-1.5' : 'h-2']" />
                        </span>
                        <span class="absolute bottom-1 left-1 flex max-w-[90%] items-center gap-1 truncate rounded bg-black/60 px-1.5 py-0.5 text-xs">
                            <FontAwesomeIcon v-if="tile.isMuted" icon="fal fa-microphone-slash" class="text-gray-300" fixed-width :aria-label="ctrans('Muted')" />
                            {{ tile.name }}
                            <span v-if="tile.isGuest" class="rounded bg-white/20 px-1 text-[10px] uppercase">{{ ctrans("Guest") }}</span>
                        </span>
                    </div>
                </div>
                <p v-if="isWaitingForAnswer" class="text-center text-xs text-gray-400">{{ ctrans("Ringing. The call stops by itself if nobody answers.") }}</p>
            </template>

            <p v-if="mediaError" class="flex items-start gap-1.5 rounded bg-amber-500/15 px-2 py-1.5 text-xs text-amber-200" role="alert">
                <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5" fixed-width aria-hidden="true" />
                {{ mediaError }}
            </p>
            <button v-if="audioBlocked" type="button" class="flex items-center justify-center gap-1.5 rounded bg-white/10 px-2 py-1.5 text-xs hover:bg-white/20" @click="startAudio">
                <FontAwesomeIcon icon="fal fa-volume-up" fixed-width aria-hidden="true" />
                {{ ctrans("Click to hear the others") }}
            </button>
        </div>

        <div class="flex items-center justify-center gap-2 border-t border-white/10 px-3 py-2">
            <button type="button" :class="controlClass" :disabled="connection !== 'connected'" :aria-pressed="local.microphone" :aria-label="ctrans('Microphone')" v-tooltip="local.microphone ? ctrans('Mute') : ctrans('Unmute')" @click="toggleMicrophone">
                <FontAwesomeIcon :icon="local.microphone ? 'fal fa-microphone' : 'fal fa-microphone-slash'" fixed-width aria-hidden="true" />
            </button>
            <button type="button" :class="controlClass" :disabled="connection !== 'connected' || dataMode === 'audio'" :aria-pressed="local.camera" :aria-label="ctrans('Camera')" v-tooltip="dataMode === 'audio' ? ctrans('Audio only is on. Change it in the settings to use the camera.') : local.camera ? ctrans('Turn camera off') : ctrans('Turn camera on')" @click="toggleCamera">
                <FontAwesomeIcon :icon="local.camera ? 'fal fa-video' : 'fal fa-video-slash'" fixed-width aria-hidden="true" />
            </button>
            <button v-if="canShareScreen" type="button" :class="[controlClass, local.screen && '!bg-[var(--app-accent)] !text-[var(--app-accent-text)]']" :disabled="connection !== 'connected'" :aria-pressed="local.screen" :aria-label="ctrans('Share screen')" v-tooltip="local.screen ? ctrans('Stop sharing') : ctrans('Share screen')" @click="toggleScreen">
                <FontAwesomeIcon icon="fal fa-desktop" fixed-width aria-hidden="true" />
            </button>
            <button type="button" :class="controlClass" :disabled="connection !== 'connected'" v-tooltip="ctrans('Microphone, camera and speaker')" :aria-label="ctrans('Call settings')" @click="refreshDevices(); devicesPopover?.toggle($event)">
                <FontAwesomeIcon icon="fal fa-cog" fixed-width aria-hidden="true" />
            </button>
            <button type="button" class="flex h-10 items-center gap-1.5 rounded-full bg-red-600 px-4 text-sm font-medium text-white transition duration-200 hover:bg-red-700" @click="hangUp()">
                <FontAwesomeIcon icon="fal fa-phone-slash" fixed-width aria-hidden="true" />
                {{ isDirect ? ctrans("Hang up") : ctrans("Leave") }}
            </button>
        </div>

        <div ref="audioSink" class="hidden" />

        <Popover ref="devicesPopover">
            <div class="flex w-80 flex-col gap-3 text-sm">
                <div class="flex flex-col gap-1">
                    <span class="text-xs text-gray-500">{{ ctrans("Data use") }}</span>
                    <SegmentedToggle :model-value="dataMode" :options="dataModeOptions" :aria-label="ctrans('Data use')" @update:model-value="chooseDataMode($event)" />
                    <span class="text-xs text-gray-400">{{ dataMode === "low" ? ctrans("Smaller video both ways, for a slow network.") : dataMode === "audio" ? ctrans("No camera video either way. Voices and shared screens stay.") : ctrans("Best quality your connection allows.") }}</span>
                </div>
                <label class="flex flex-col gap-1">
                    <span class="text-xs text-gray-500">{{ ctrans("Microphone") }}</span>
                    <Select :model-value="chosenDevice.audioinput" :options="devices.audioinput" option-label="label" option-value="value" size="small" :placeholder="ctrans('No microphone found')" @update:model-value="chooseDevice('audioinput', $event)" />
                </label>
                <label class="flex flex-col gap-1">
                    <span class="text-xs text-gray-500">{{ ctrans("Camera") }}</span>
                    <Select :model-value="chosenDevice.videoinput" :options="devices.videoinput" option-label="label" option-value="value" size="small" :placeholder="ctrans('No camera found')" @update:model-value="chooseDevice('videoinput', $event)" />
                </label>
                <label v-if="canChooseSpeaker" class="flex flex-col gap-1">
                    <span class="text-xs text-gray-500">{{ ctrans("Speaker") }}</span>
                    <Select :model-value="chosenDevice.audiooutput" :options="devices.audiooutput" option-label="label" option-value="value" size="small" :placeholder="ctrans('Default')" @update:model-value="chooseDevice('audiooutput', $event)" />
                </label>
            </div>
        </Popover>
    </div>
</template>
