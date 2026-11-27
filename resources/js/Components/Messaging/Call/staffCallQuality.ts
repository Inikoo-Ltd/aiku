/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import type { Room, RoomOptions, TrackPublishOptions, VideoCaptureOptions } from "livekit-client"

export type StaffCallDataMode = "auto" | "low" | "audio"

export const STAFF_CALL_DATA_MODES: StaffCallDataMode[] = ["auto", "low", "audio"]

const DATA_MODE_KEY = "aiku.staff-call.data-mode"

/**
 * Every quality choice of staff calls, in one place so it can be tuned after real calls. Option names follow
 * livekit-client 2.25 (RoomOptions, TrackPublishDefaults, VideoPresets, ScreenSharePresets).
 */
export const staffCallQuality = (lk: typeof import("livekit-client")) => {
    const { VideoPresets, ScreenSharePresets } = lk

    const camera = {
        oneToOne: { capture: VideoPresets.h1080, layers: [VideoPresets.h180, VideoPresets.h540] },
        group: { capture: VideoPresets.h720, layers: [VideoPresets.h180, VideoPresets.h360] },
        lowData: { capture: VideoPresets.h360, layers: [VideoPresets.h180], frameRate: 15 },
        frameRate: 30,
        degradationPreference: "maintain-framerate" as RTCDegradationPreference,
    }

    const screen = {
        capture: ScreenSharePresets.h1080fps15,
        contentHint: "text" as const,
        degradationPreference: "maintain-resolution" as RTCDegradationPreference,
        lowDataQuality: lk.VideoQuality.HIGH,
    }

    const room: Partial<RoomOptions> = {
        adaptiveStream: true,
        dynacast: true,
        audioCaptureDefaults: {
            echoCancellation: true,
            noiseSuppression: true,
            autoGainControl: true,
            channelCount: 1,
        },
        publishDefaults: {
            simulcast: true,
            audioPreset: { maxBitrate: 64_000 },
            dtx: true,
            red: true,
            videoCodec: "vp8",
            screenShareEncoding: screen.capture.encoding,
        },
    }

    const cameraFor = (mode: StaffCallDataMode, othersInCall: number): { capture: VideoCaptureOptions; publish: TrackPublishOptions } => {
        const preset = mode === "low" ? camera.lowData : othersInCall > 1 ? camera.group : camera.oneToOne
        const frameRate = mode === "low" ? camera.lowData.frameRate : camera.frameRate

        return {
            capture: { resolution: { ...preset.capture.resolution, frameRate } },
            publish: {
                simulcast: true,
                videoEncoding: { ...preset.capture.encoding, maxFramerate: frameRate },
                videoSimulcastLayers: preset.layers,
                degradationPreference: camera.degradationPreference,
            },
        }
    }

    return { room, camera, screen, cameraFor }
}

export const rememberedDataMode = (): StaffCallDataMode => {
    try {
        const saved = window.localStorage.getItem(DATA_MODE_KEY) as StaffCallDataMode | null
        return saved && STAFF_CALL_DATA_MODES.includes(saved) ? saved : "auto"
    } catch {
        return "auto"
    }
}

export const rememberDataMode = (mode: StaffCallDataMode) => {
    try {
        window.localStorage.setItem(DATA_MODE_KEY, mode)
    } catch { }
}

export type StaffCallQualitySample = {
    path?: "udp" | "tcp" | "relay"
    connection?: string
    rtt_ms?: number
    loss_pct?: number
    jitter_ms?: number
    sent_audio_kbps?: number
    sent_video_kbps?: number
    received_audio_kbps?: number
    received_video_kbps?: number
}

const PATH_RANK = { udp: 0, tcp: 1, relay: 2 } as const

/**
 * Reads the browser's WebRTC stats of every track in the call and returns what happened since the previous read, one
 * sample per ping (PingStaffCall::addQualitySample keeps the totals). Bitrates and loss are deltas between reads, so
 * the first read only sets the baseline; path is the worst of the publisher and subscriber connections.
 */
export const staffCallQualitySampler = (room: Room) => {
    let previous: { at: number; totals: Record<string, number> } | null = null

    return async (): Promise<StaffCallQualitySample | null> => {
        if (room.state !== "connected") return null

        const publications = [
            ...room.localParticipant.trackPublications.values(),
            ...[...room.remoteParticipants.values()].flatMap((participant) => [...participant.trackPublications.values()]),
        ]
        const stats = new Map<string, any>()
        for (const publication of publications) {
            const report = await publication.track?.getRTCStatsReport().catch(() => undefined)
            report?.forEach((stat: any) => stats.set(stat.id, stat))
        }

        const totals: Record<string, number> = {}
        const add = (key: string, value: number | undefined) => (totals[key] = (totals[key] ?? 0) + (value ?? 0))
        const sample: StaffCallQualitySample = { connection: room.localParticipant.connectionQuality }
        const roundTrips: number[] = []
        let jitter: number | undefined

        stats.forEach((stat) => {
            if (stat.type === "outbound-rtp") add(`sent_${stat.kind}`, stat.bytesSent)
            if (stat.type === "inbound-rtp") {
                add(`received_${stat.kind}`, stat.bytesReceived)
                add("lost", stat.packetsLost)
                add("packets", stat.packetsReceived)
                if (stat.kind === "audio" && stat.jitter !== undefined) jitter = Math.max(jitter ?? 0, stat.jitter * 1000)
            }
            if (stat.type === "candidate-pair" && stat.nominated && stat.state === "succeeded") {
                if (stat.currentRoundTripTime !== undefined) roundTrips.push(stat.currentRoundTripTime * 1000)
                const local = stats.get(stat.localCandidateId)
                const path = local?.candidateType === "relay" ? "relay" : local?.protocol === "tcp" ? "tcp" : local ? "udp" : undefined
                if (path && (!sample.path || PATH_RANK[path] > PATH_RANK[sample.path])) sample.path = path
            }
        })

        if (roundTrips.length) sample.rtt_ms = Math.max(...roundTrips)
        if (jitter !== undefined) sample.jitter_ms = jitter

        const now = Date.now()
        if (previous) {
            const seconds = (now - previous.at) / 1000
            const delta = (key: string) => Math.max(0, (totals[key] ?? 0) - (previous!.totals[key] ?? 0))
            const kbps = (key: string) => Math.round((delta(key) * 8) / 1000 / seconds)
            sample.sent_audio_kbps = kbps("sent_audio")
            sample.sent_video_kbps = kbps("sent_video")
            sample.received_audio_kbps = kbps("received_audio")
            sample.received_video_kbps = kbps("received_video")
            const lost = delta("lost")
            const packets = lost + delta("packets")
            if (packets) sample.loss_pct = Math.round((lost / packets) * 1000) / 10
        }
        previous = { at: now, totals }

        return sample
    }
}
