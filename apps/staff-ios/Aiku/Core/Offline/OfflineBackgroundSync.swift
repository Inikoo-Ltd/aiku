import BackgroundTasks
import Foundation
import UserNotifications

enum OfflineBackgroundSync {
    static let refreshIdentifier = "com.inikoo.aiku.offline-sync"
    static let processingIdentifier = "com.inikoo.aiku.offline-sync-processing"

    static func register() {
        guard !CrashReporting.isRunningTests else { return }
        for identifier in [refreshIdentifier, processingIdentifier] {
            BGTaskScheduler.shared.register(forTaskWithIdentifier: identifier, using: nil) { task in
                OfflineBackgroundSync.run(BackgroundTaskBox(task))
            }
        }
    }

    @MainActor
    static func scheduleIfWaiting() {
        guard !CrashReporting.isRunningTests, OfflineWork.shared.hasWaitingWork else { return }
        let refresh = BGAppRefreshTaskRequest(identifier: refreshIdentifier)
        refresh.earliestBeginDate = Date(timeIntervalSinceNow: refreshAfter)
        try? BGTaskScheduler.shared.submit(refresh)
        let processing = BGProcessingTaskRequest(identifier: processingIdentifier)
        processing.requiresNetworkConnectivity = true
        processing.requiresExternalPower = false
        processing.earliestBeginDate = Date(timeIntervalSinceNow: processingAfter)
        try? BGTaskScheduler.shared.submit(processing)
    }

    private static func run(_ box: BackgroundTaskBox) {
        box.task.expirationHandler = {
            Task { @MainActor in OfflineWork.shared.stopBackgroundSync() }
        }
        Task { @MainActor in
            let outcome = await OfflineWork.shared.syncInBackground(token: KeychainTokenStore.shared.load())
            OfflineBackgroundSync.scheduleIfWaiting()
            box.task.setTaskCompleted(success: outcome.status != .retry)
        }
    }

    private static let refreshAfter: TimeInterval = 15 * 60
    private static let processingAfter: TimeInterval = 5 * 60
}

private final class BackgroundTaskBox: @unchecked Sendable {
    let task: BGTask

    init(_ task: BGTask) {
        self.task = task
    }
}

final class OfflineNotifications: NSObject, UNUserNotificationCenterDelegate, @unchecked Sendable {
    static let shared = OfflineNotifications()
    static let needsYouIdentifier = "aiku.offline.needs-you"

    private let lock = NSLock()
    private var hasAsked = false

    func start() {
        guard !CrashReporting.isRunningTests else { return }
        UNUserNotificationCenter.current().delegate = self
    }

    func askQuietly() {
        let shouldAsk = lock.withLock {
            defer { hasAsked = true }
            return !hasAsked
        }
        guard shouldAsk, !CrashReporting.isRunningTests else { return }
        UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .sound, .provisional]) { _, _ in }
    }

    func announceNeedsYou(count: Int) {
        guard !CrashReporting.isRunningTests else { return }
        let content = UNMutableNotificationContent()
        content.title = "Aiku"
        content.body = L10n.formatInSavedLanguage("%lld actions need you", count)
        content.sound = .default
        let request = UNNotificationRequest(identifier: Self.needsYouIdentifier, content: content, trigger: nil)
        UNUserNotificationCenter.current().add(request) { _ in }
    }

    func userNotificationCenter(_ center: UNUserNotificationCenter, didReceive response: UNNotificationResponse) async {
        guard response.notification.request.identifier == Self.needsYouIdentifier else { return }
        await MainActor.run {
            OfflineWork.shared.openConflictsFromNotification()
        }
    }

    func userNotificationCenter(_ center: UNUserNotificationCenter, willPresent notification: UNNotification) async -> UNNotificationPresentationOptions {
        [.banner, .list, .sound]
    }
}
