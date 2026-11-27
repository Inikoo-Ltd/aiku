/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import type { RoomOptions, TrackPublishOptions, VideoCaptureOptions } from "livekit-client"

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
