import AVFoundation
import Foundation
import UIKit

@MainActor
protocol ScanSignals: AnyObject {
    func successTone()
    func errorTone()
    func successVibration()
    func errorVibration()
}

@MainActor
final class SilentScanSignals: ScanSignals {
    func successTone() {}
    func errorTone() {}
    func successVibration() {}
    func errorVibration() {}
}

@MainActor
final class ScanFeedback {
    static let shared = ScanFeedback(
        defaults: .standard,
        signals: CrashReporting.isRunningTests ? SilentScanSignals() : DeviceScanSignals()
    )

    private let defaults: UserDefaults
    private let signals: ScanSignals

    init(defaults: UserDefaults, signals: ScanSignals) {
        self.defaults = defaults
        self.signals = signals
    }

    var playsSound: Bool { defaults.object(forKey: ScanSettingsKey.sound) as? Bool ?? true }
    var vibrates: Bool { defaults.object(forKey: ScanSettingsKey.vibrate) as? Bool ?? true }

    func accepted() {
        if playsSound {
            signals.successTone()
        }
        if vibrates {
            signals.successVibration()
        }
    }

    func rejected() {
        if playsSound {
            signals.errorTone()
        }
        if vibrates {
            signals.errorVibration()
        }
    }
}

enum ScanTone {
    static let success = wav(frequencies: [400, 1200], seconds: 0.15)
    static let error = wav(frequencies: [300, 400, 500], seconds: 0.4)

    static func wav(frequencies: [Double], seconds: Double, sampleRate: Int = 22_050) -> Data {
        let sampleCount = Int(Double(sampleRate) * seconds)
        let fadeCount = max(1, sampleRate / 200)
        var samples = Data(capacity: sampleCount * 2)
        for index in 0..<sampleCount {
            let time = Double(index) / Double(sampleRate)
            let mixed = frequencies.reduce(0) { $0 + sin(2 * .pi * $1 * time) } / Double(max(frequencies.count, 1))
            let edge = Double(min(index, sampleCount - 1 - index, fadeCount)) / Double(fadeCount)
            let value = Int16(max(-1, min(1, mixed * edge * 0.8)) * Double(Int16.max))
            withUnsafeBytes(of: value.littleEndian) { samples.append(contentsOf: $0) }
        }
        var file = Data()
        file.append(contentsOf: Array("RIFF".utf8))
        file.append(littleEndian: UInt32(36 + samples.count))
        file.append(contentsOf: Array("WAVEfmt ".utf8))
        file.append(littleEndian: UInt32(16))
        file.append(littleEndian: UInt16(1))
        file.append(littleEndian: UInt16(1))
        file.append(littleEndian: UInt32(sampleRate))
        file.append(littleEndian: UInt32(sampleRate * 2))
        file.append(littleEndian: UInt16(2))
        file.append(littleEndian: UInt16(16))
        file.append(contentsOf: Array("data".utf8))
        file.append(littleEndian: UInt32(samples.count))
        file.append(samples)
        return file
    }
}

private extension Data {
    mutating func append<Value: FixedWidthInteger>(littleEndian value: Value) {
        Swift.withUnsafeBytes(of: value.littleEndian) { append(contentsOf: $0) }
    }
}

@MainActor
final class DeviceScanSignals: ScanSignals {
    private lazy var successPlayer = player(ScanTone.success)
    private lazy var errorPlayer = player(ScanTone.error)
    private let impact = UIImpactFeedbackGenerator(style: .medium)
    private let notification = UINotificationFeedbackGenerator()
    private var isSessionReady = false

    func successTone() {
        play(successPlayer)
    }

    func errorTone() {
        play(errorPlayer)
    }

    func successVibration() {
        impact.impactOccurred()
    }

    func errorVibration() {
        notification.notificationOccurred(.error)
    }

    private func play(_ player: AVAudioPlayer?) {
        guard let player else { return }
        if !isSessionReady {
            try? AVAudioSession.sharedInstance().setCategory(.ambient)
            isSessionReady = true
        }
        player.currentTime = 0
        player.play()
    }

    private func player(_ sound: Data) -> AVAudioPlayer? {
        let player = try? AVAudioPlayer(data: sound, fileTypeHint: AVFileType.wav.rawValue)
        player?.prepareToPlay()
        return player
    }
}
