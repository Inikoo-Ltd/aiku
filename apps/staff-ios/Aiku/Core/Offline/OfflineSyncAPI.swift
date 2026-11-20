import Foundation

struct SyncActionRequest: Sendable {
    let sequence: Int64
    let operation: String
    let performedAt: Date
    let timeSourceIsServer: Bool
    let seenAt: Date?
    let payload: OfflinePayload
}

struct SyncResult: Sendable {
    let sequence: Int64
    let result: OfflineResult
}

enum SyncAnswer: Sendable {
    case answered(results: [SyncResult], expectedSequence: Int64?)
    case sessionExpired
    case unreachable
    case failed(statusCode: Int)
}

enum NoticeAnswer: Sendable {
    case sent
    case unreachable
    case refused
}

enum OfflineOperationsState: String, Sendable {
    case open
    case recent
}

enum OfflineResolution: String, CaseIterable, Sendable {
    case putBack = "put_back"
    case done
    case retry
    case recount
    case discard
    case keepCount = "keep_count"

    var label: String {
        switch self {
        case .putBack: "I put it back"
        case .done: "Done"
        case .retry: "Try again"
        case .recount: "Count again"
        case .discard: "Discard my count"
        case .keepCount: "Keep my count"
        }
    }
}

enum OfflineOutcomeStatus {
    static let applied = "applied"
    static let partlyApplied = "partly_applied"
    static let refused = "refused"
    static let conflict = "conflict"
    static let blocked = "blocked"
    static let void = "void"
    static let alreadyDone = "already_done"
    static let missingPrevious = "missing_previous"
    static let resolved = "resolved"
    static let rolledBack = "rolled_back"
    static let expired = "expired"
}

enum OfflineInstruction {
    static let none = "none"
    static let putBack = "put_back"
    static let unpack = "unpack"
    static let keepInTote = "keep_in_tote"
    static let pickAgain = "pick_again"
    static let recount = "recount"
    static let checkShelf = "check_shelf"
}

struct OfflineOperationEntry: Decodable, Identifiable, Hashable, Sendable {
    struct Labels: Decodable, Hashable, Sendable {
        var title = ""
        var subtitle: String?
        var locationCode: String?

        init() {}

        private enum CodingKeys: String, CodingKey { case title, subtitle, locationCode }

        init(from decoder: Decoder) throws {
            let container = try decoder.container(keyedBy: CodingKeys.self)
            title = container.offlineString(.title) ?? ""
            subtitle = container.offlineString(.subtitle)
            locationCode = container.offlineString(.locationCode)
        }
    }

    struct Record: Decodable, Hashable, Sendable {
        let type: String
        let id: Int

        var key: OfflineRecordKey? {
            OfflineRecordType(rawValue: type).map { OfflineRecordKey(type: $0, id: id) }
        }
    }

    struct CountedRow: Decodable, Hashable, Sendable {
        let locationCode: String?
        let counted: Double?
        let quantity: Double?
    }

    struct Person: Decodable, Hashable, Sendable {
        let id: Int?
        let name: String?
    }

    var sequence: Int?
    var status = ""
    var operationId: Int?
    var entryID: Int?
    var operation = ""
    var reason: String?
    var instruction = OfflineInstruction.none
    var message: String?
    var requestedQuantity: Double?
    var appliedQuantity: Double?
    var countedSince = false
    var taskReference: String?
    var conflictingUser: Person?
    var blockedBySequence: Int?
    var performedAt: String?
    var syncedAt: String?
    var state: String?
    var resolution: String?
    var labels = Labels()
    var record: Record?
    var resolutions: [String] = []
    var canRollback = false
    var countedRows: [CountedRow] = []

    var id: Int { key ?? sequence ?? 0 }

    var key: Int? { operationId ?? entryID }

    var currentState: String { state ?? status }

    var allowedResolutions: [OfflineResolution] { resolutions.compactMap(OfflineResolution.init(rawValue:)) }

    var offlineOperation: OfflineOperation? { OfflineOperation(rawValue: operation) }

    var operationLabel: String { offlineOperation?.label ?? operation }

    var missingQuantity: Double? {
        guard let requestedQuantity else { return nil }
        let missing = requestedQuantity - (appliedQuantity ?? 0)
        return missing > 0 ? missing : nil
    }

    var isOpen: Bool {
        [OfflineOutcomeStatus.partlyApplied, OfflineOutcomeStatus.refused, OfflineOutcomeStatus.conflict, OfflineOutcomeStatus.blocked].contains(currentState)
    }

    var result: OfflineResult {
        OfflineResult(
            status: status,
            operationID: key,
            reason: reason,
            instruction: instruction,
            message: message,
            requestedQuantity: requestedQuantity,
            appliedQuantity: appliedQuantity,
            countedSince: countedSince,
            taskReference: taskReference,
            conflictingUserName: conflictingUser?.name
        )
    }

    private enum CodingKeys: String, CodingKey {
        case sequence, status, operationId, id, operation, reason, instruction, message, requestedQuantity, appliedQuantity
        case countedSince, taskReference, conflictingUser, blockedBySequence, performedAt, syncedAt, state, resolution
        case labels, record, resolutions, canRollback, countedRows
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        sequence = container.offlineInt(.sequence)
        status = container.offlineString(.status) ?? ""
        operationId = container.offlineInt(.operationId)
        entryID = container.offlineInt(.id)
        operation = container.offlineString(.operation) ?? ""
        reason = container.offlineString(.reason)
        instruction = container.offlineString(.instruction) ?? OfflineInstruction.none
        message = container.offlineString(.message)
        requestedQuantity = container.offlineDouble(.requestedQuantity)
        appliedQuantity = container.offlineDouble(.appliedQuantity)
        countedSince = (try? container.decodeIfPresent(Bool.self, forKey: .countedSince)) ?? false
        taskReference = container.offlineString(.taskReference)
        conflictingUser = try? container.decodeIfPresent(Person.self, forKey: .conflictingUser)
        blockedBySequence = container.offlineInt(.blockedBySequence)
        performedAt = container.offlineString(.performedAt)
        syncedAt = container.offlineString(.syncedAt)
        state = container.offlineString(.state)
        resolution = container.offlineString(.resolution)
        labels = (try? container.decodeIfPresent(Labels.self, forKey: .labels)) ?? Labels()
        record = try? container.decodeIfPresent(Record.self, forKey: .record)
        resolutions = ((try? container.decodeIfPresent([String].self, forKey: .resolutions)) ?? nil) ?? []
        canRollback = (try? container.decodeIfPresent(Bool.self, forKey: .canRollback)) ?? false
        countedRows = ((try? container.decodeIfPresent([CountedRow].self, forKey: .countedRows)) ?? nil) ?? []
    }

    static func == (lhs: OfflineOperationEntry, rhs: OfflineOperationEntry) -> Bool {
        lhs.key == rhs.key && lhs.sequence == rhs.sequence && lhs.currentState == rhs.currentState && lhs.resolution == rhs.resolution
    }

    func hash(into hasher: inout Hasher) {
        hasher.combine(key)
        hasher.combine(sequence)
        hasher.combine(currentState)
    }
}

struct OfflineResolveOutcome: Sendable {
    let operation: OfflineOperationEntry
    let results: [OfflineOperationEntry]
}

struct OfflineSyncAPI: Sendable {
    let client: APIClient

    static let shared = OfflineSyncAPI(client: .shared)

    func sync(warehouseID: Int, actions: [SyncActionRequest]) async -> SyncAnswer {
        let body: OfflinePayload = ["actions": .array(actions.map { action in
            .object([
                "sequence": .number(Double(action.sequence)),
                "operation": .string(action.operation),
                "performed_at": .string(Self.wireTime(action.performedAt)),
                "time_source": .string(action.timeSourceIsServer ? "server" : "device"),
                "seen_at": action.seenAt.map { .string(Self.wireTime($0)) } ?? .null,
                "payload": .object(action.payload)
            ])
        })]
        guard let (data, response) = await post("mobile/warehouses/\(warehouseID)/offline/sync", body) else {
            return .unreachable
        }
        switch response.statusCode {
        case 200:
            guard let page = try? Self.decoder.decode(SyncPage.self, from: data) else { return .failed(statusCode: 200) }
            let results = page.data.results.compactMap { entry -> SyncResult? in
                guard let sequence = entry.sequence else { return nil }
                return SyncResult(sequence: Int64(sequence), result: entry.result)
            }
            return .answered(results: results, expectedSequence: page.data.expectedSequence.map(Int64.init))
        case 401:
            return .sessionExpired
        default:
            return .failed(statusCode: response.statusCode)
        }
    }

    func operations(_ state: OfflineOperationsState) async throws -> [OfflineOperationEntry] {
        let endpoint = Endpoint(path: "mobile/offline/operations", queryItems: [URLQueryItem(name: "state", value: state.rawValue)])
        let data = try await client.send(endpoint)
        return try Self.decoder.decode(OperationList.self, from: data).data
    }

    func resolve(operationID: Int, resolution: OfflineResolution) async throws -> OfflineResolveOutcome {
        let data = try await client.send(Self.postEndpoint("mobile/offline/operations/\(operationID)/resolve", ["resolution": .string(resolution.rawValue)]))
        let answer = try Self.decoder.decode(ResolveResponse.self, from: data)
        return OfflineResolveOutcome(operation: answer.data.operation, results: answer.data.results ?? [])
    }

    func rollback(operationID: Int, reason: String?) async throws -> OfflineOperationEntry {
        var body: OfflinePayload = [:]
        if case .string(let text) = OfflineJSON.optional(reason) {
            body["reason"] = .string(text)
        }
        let data = try await client.send(Self.postEndpoint("mobile/offline/operations/\(operationID)/rollback", body))
        return try Self.decoder.decode(RollbackResponse.self, from: data).data.operation
    }

    func waitingNotice(userID: Int, count: Int, oldestPerformedAt: Date) async -> NoticeAnswer {
        let body: OfflinePayload = [
            "user_id": .int(userID),
            "count": .int(count),
            "oldest_performed_at": .string(Self.wireTime(oldestPerformedAt))
        ]
        guard let (_, response) = await post("mobile/offline/waiting-notice", body) else { return .unreachable }
        return (200..<300).contains(response.statusCode) ? .sent : .refused
    }

    func refetch(path: String) async -> Bool {
        (try? await client.perform(Endpoint(path: path))) != nil
    }

    private func post(_ path: String, _ body: OfflinePayload) async -> (Data, HTTPURLResponse)? {
        try? await client.perform(Self.postEndpoint(path, body))
    }

    private static func postEndpoint(_ path: String, _ body: OfflinePayload) -> Endpoint {
        Endpoint(path: path, method: .post, body: try? JSONEncoder().encode(body))
    }

    static func wireTime(_ date: Date) -> String {
        date.formatted(Date.ISO8601FormatStyle(includingFractionalSeconds: true))
    }

    private static var decoder: JSONDecoder {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return decoder
    }

    private struct SyncPage: Decodable {
        struct Payload: Decodable {
            let results: [OfflineOperationEntry]
            let expectedSequence: Int?

            private enum CodingKeys: String, CodingKey { case results, expectedSequence }

            init(from decoder: Decoder) throws {
                let container = try decoder.container(keyedBy: CodingKeys.self)
                results = ((try? container.decodeIfPresent([OfflineOperationEntry].self, forKey: .results)) ?? nil) ?? []
                expectedSequence = container.offlineInt(.expectedSequence)
            }
        }

        let data: Payload
    }

    private struct OperationList: Decodable {
        let data: [OfflineOperationEntry]
    }

    private struct ResolveResponse: Decodable {
        struct Payload: Decodable {
            let operation: OfflineOperationEntry
            let results: [OfflineOperationEntry]?
        }

        let data: Payload
    }

    private struct RollbackResponse: Decodable {
        struct Payload: Decodable {
            let operation: OfflineOperationEntry
        }

        let data: Payload
    }
}

extension KeyedDecodingContainer {
    func offlineInt(_ key: Key) -> Int? {
        if let value = try? decodeIfPresent(Int.self, forKey: key) { return value }
        if let value = try? decodeIfPresent(Double.self, forKey: key) { return Int(value) }
        if let text = try? decodeIfPresent(String.self, forKey: key) { return Int(text.trimmingCharacters(in: .whitespaces)) }
        return nil
    }

    func offlineDouble(_ key: Key) -> Double? {
        if let value = try? decodeIfPresent(Double.self, forKey: key) { return value }
        if let text = try? decodeIfPresent(String.self, forKey: key) { return Double(text.trimmingCharacters(in: .whitespaces)) }
        return nil
    }

    func offlineString(_ key: Key) -> String? {
        guard let text = try? decodeIfPresent(String.self, forKey: key) else { return nil }
        let trimmed = text.trimmingCharacters(in: .whitespacesAndNewlines)
        return trimmed.isEmpty ? nil : trimmed
    }
}
