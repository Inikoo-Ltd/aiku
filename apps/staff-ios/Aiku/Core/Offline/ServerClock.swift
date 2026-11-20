import Foundation

struct OfflineTime: Equatable, Sendable {
    let date: Date
    let isServerTime: Bool
}

final class ServerClock: @unchecked Sendable {
    static let shared = ServerClock()

    private struct Anchor {
        let serverTime: Date
        let elapsedAt: TimeInterval
        let boot: Int?
    }

    private let defaults: UserDefaults
    private let elapsed: @Sendable () -> TimeInterval
    private let bootTime: @Sendable () -> Int?
    private let deviceClock: @Sendable () -> Date
    private let lock = NSLock()
    private var anchor: Anchor?
    private var savedElapsedAt: TimeInterval?

    init(
        defaults: UserDefaults = .standard,
        elapsed: @escaping @Sendable () -> TimeInterval = ServerClock.monotonicSeconds,
        bootTime: @escaping @Sendable () -> Int? = ServerClock.kernelBootTime,
        deviceClock: @escaping @Sendable () -> Date = { Date() }
    ) {
        self.defaults = defaults
        self.elapsed = elapsed
        self.bootTime = bootTime
        self.deviceClock = deviceClock
        anchor = restored()
    }

    func record(_ serverTime: Date) {
        let elapsedNow = elapsed()
        let recorded = Anchor(serverTime: serverTime, elapsedAt: elapsedNow, boot: bootTime())
        lock.withLock {
            anchor = recorded
            if let saved = savedElapsedAt, elapsedNow - saved < Self.saveEvery, elapsedNow >= saved {
                return
            }
            savedElapsedAt = elapsedNow
            defaults.set(recorded.serverTime.timeIntervalSince1970, forKey: Self.serverTimeKey)
            defaults.set(recorded.elapsedAt, forKey: Self.elapsedAtKey)
            defaults.set(recorded.boot ?? Self.noBoot, forKey: Self.bootKey)
        }
    }

    func record(dateHeader: String?) {
        guard let date = ServerClockHeader.date(dateHeader) else { return }
        record(date)
    }

    func now() -> OfflineTime {
        let current = lock.withLock { anchor }
        guard let current, isFromThisBoot(current) else {
            return OfflineTime(date: deviceClock(), isServerTime: false)
        }
        return OfflineTime(date: current.serverTime.addingTimeInterval(elapsed() - current.elapsedAt), isServerTime: true)
    }

    private func isFromThisBoot(_ candidate: Anchor) -> Bool {
        guard elapsed() >= candidate.elapsedAt else { return false }
        guard let boot = candidate.boot, let currentBoot = bootTime() else { return true }
        return boot == currentBoot
    }

    private func restored() -> Anchor? {
        guard defaults.object(forKey: Self.serverTimeKey) != nil else { return nil }
        let boot = defaults.integer(forKey: Self.bootKey)
        let candidate = Anchor(
            serverTime: Date(timeIntervalSince1970: defaults.double(forKey: Self.serverTimeKey)),
            elapsedAt: defaults.double(forKey: Self.elapsedAtKey),
            boot: boot == Self.noBoot ? nil : boot
        )
        return isFromThisBoot(candidate) ? candidate : nil
    }

    static func monotonicSeconds() -> TimeInterval {
        TimeInterval(clock_gettime_nsec_np(CLOCK_MONOTONIC)) / 1_000_000_000
    }

    static func kernelBootTime() -> Int? {
        var bootTime = timeval()
        var size = MemoryLayout<timeval>.stride
        var mib: [Int32] = [CTL_KERN, KERN_BOOTTIME]
        guard sysctl(&mib, 2, &bootTime, &size, nil, 0) == 0 else { return nil }
        return Int(bootTime.tv_sec)
    }

    private static let saveEvery: TimeInterval = 60
    private static let serverTimeKey = "aiku.offline.anchorServerTime"
    private static let elapsedAtKey = "aiku.offline.anchorElapsedAt"
    private static let bootKey = "aiku.offline.anchorBoot"
    private static let noBoot = -1
}
