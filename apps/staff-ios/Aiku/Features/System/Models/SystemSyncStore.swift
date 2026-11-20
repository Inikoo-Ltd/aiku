import Foundation
import Observation

struct SystemSyncService: Sendable {
    var operations: @Sendable (OfflineOperationsState) async throws -> [OfflineOperationEntry]
    var resolve: @Sendable (Int, OfflineResolution) async throws -> OfflineResolveOutcome
    var rollback: @Sendable (Int, String?) async throws -> OfflineOperationEntry

    static let live = SystemSyncService(
        operations: { try await OfflineSyncAPI.shared.operations($0) },
        resolve: { try await OfflineSyncAPI.shared.resolve(operationID: $0, resolution: $1) },
        rollback: { try await OfflineSyncAPI.shared.rollback(operationID: $0, reason: $1) }
    )
}

struct SystemQueueGroup: Identifiable, Sendable {
    let record: OfflineRecordKey
    let title: String
    let entries: [QueueEntry]

    var id: OfflineRecordKey { record }
}

enum SystemSyncChange: Equatable, Sendable {
    case resolved(OfflineResolution)
    case rolledBack
    case refused(String)
    case sessionExpired
}

@MainActor
@Observable
final class SystemSyncStore {
    static let shared = SystemSyncStore()

    private(set) var openOperations: [OfflineOperationEntry] = []
    private(set) var recentOperations: [OfflineOperationEntry] = []
    private(set) var hasLoaded = false
    private(set) var isLoading = false
    private(set) var loadError: String?
    private(set) var workingOn: Int?
    private(set) var ranAgain: [Int: [OfflineOperationEntry]] = [:]

    @ObservationIgnored private let service: SystemSyncService
    @ObservationIgnored private var titles: [OfflineRecordKey: String] = [:]

    init(service: SystemSyncService = .live) {
        self.service = service
    }

    func load() async {
        isLoading = true
        defer { isLoading = false }
        do {
            async let open = service.operations(.open)
            async let recent = service.operations(.recent)
            openOperations = try await open
            recentOperations = try await recent
            hasLoaded = true
            loadError = nil
            rememberTitles(openOperations + recentOperations)
        } catch where error.isCancellation {
            return
        } catch {
            loadError = Self.message(for: error)
        }
    }

    func operation(_ id: Int) -> OfflineOperationEntry? {
        openOperations.first { $0.key == id } ?? recentOperations.first { $0.key == id }
    }

    func resolve(_ id: Int, with resolution: OfflineResolution) async -> SystemSyncChange? {
        guard workingOn == nil, operation(id)?.allowedResolutions.contains(resolution) == true else { return nil }
        workingOn = id
        defer { workingOn = nil }
        do {
            let outcome = try await service.resolve(id, resolution)
            replace(outcome.operation)
            ranAgain[id] = outcome.results
            return .resolved(resolution)
        } catch {
            return Self.refusal(error)
        }
    }

    func rollback(_ id: Int, reason: String?) async -> SystemSyncChange? {
        guard workingOn == nil, operation(id)?.canRollback == true else { return nil }
        workingOn = id
        defer { workingOn = nil }
        do {
            replace(try await service.rollback(id, reason))
            return .rolledBack
        } catch {
            return Self.refusal(error)
        }
    }

    func groups(of queue: [QueueEntry], downloaded: (OfflineRecordKey) -> DownloadedRecord?) -> [SystemQueueGroup] {
        var order: [OfflineRecordKey] = []
        var byRecord: [OfflineRecordKey: [QueueEntry]] = [:]
        for entry in queue {
            guard let record = entry.record else { continue }
            if byRecord[record] == nil {
                order.append(record)
            }
            byRecord[record, default: []].append(entry)
        }
        return order.map { record in
            SystemQueueGroup(record: record, title: title(of: record, downloaded: downloaded), entries: byRecord[record] ?? [])
        }
    }

    func title(of record: OfflineRecordKey, downloaded: (OfflineRecordKey) -> DownloadedRecord?) -> String {
        if let known = titles[record] {
            return record.type.title(known)
        }
        if let reference = downloaded(record).flatMap({ Self.reference(in: $0.body) }) {
            titles[record] = reference
            return record.type.title(reference)
        }
        return record.type.title("#\(record.id)")
    }

    func reset() {
        openOperations = []
        recentOperations = []
        hasLoaded = false
        loadError = nil
        ranAgain = [:]
        titles = [:]
    }

    static func reference(in body: Data) -> String? {
        guard let object = try? JSONSerialization.jsonObject(with: body) as? [String: Any],
              let data = object["data"] as? [String: Any] else { return nil }
        return ["reference", "code"].lazy.compactMap { data[$0] as? String }.first { !$0.isEmpty }
    }

    private func rememberTitles(_ entries: [OfflineOperationEntry]) {
        for entry in entries {
            guard let record = entry.record?.key else { continue }
            let title = entry.labels.title.components(separatedBy: " · ").first?.trimmingCharacters(in: .whitespaces) ?? ""
            if !title.isEmpty, titles[record] == nil {
                titles[record] = title
            }
        }
    }

    private func replace(_ updated: OfflineOperationEntry) {
        if let index = openOperations.firstIndex(where: { $0.key == updated.key }) {
            openOperations.remove(at: index)
        }
        if let index = recentOperations.firstIndex(where: { $0.key == updated.key }) {
            recentOperations[index] = updated
        } else {
            recentOperations.insert(updated, at: 0)
        }
    }

    private static func refusal(_ error: Error) -> SystemSyncChange {
        if error as? APIError == .unauthorized { return .sessionExpired }
        return .refused(message(for: error))
    }

    private static func message(for error: Error) -> String {
        switch error as? APIError {
        case .offline: "This needs a connection"
        case .some(let apiError): apiError.errorDescription ?? "Something went wrong"
        case nil: "Something went wrong"
        }
    }
}
