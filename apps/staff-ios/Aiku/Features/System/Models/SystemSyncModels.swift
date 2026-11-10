import Foundation

enum SystemActionStatus: Hashable, Sendable {
    case waiting
    case uploaded
    case needsReview
    case discarded
}

struct SystemQueuedAction: Identifiable, Hashable, Sendable {
    let id: String
    let record: String
    let summary: String
    let detail: String
    let icon: AppIcon
    var conflictReference: String?
    var status: SystemActionStatus = .waiting

    var title: String { "\(record) · \(summary)" }
}

struct SystemSyncRecord: Identifiable, Hashable, Sendable {
    let id: UUID
    let title: String
    let detail: String
    let chip: Chip
    let icon: AppIcon

    init(title: String, detail: String, chip: Chip, icon: AppIcon) {
        self.id = UUID()
        self.title = title
        self.detail = detail
        self.chip = chip
        self.icon = icon
    }
}

enum SystemConflictChoice: String, CaseIterable, Hashable, Sendable {
    case mine
    case aiku
    case both

    var title: String {
        switch self {
        case .mine: "Mine"
        case .aiku: "Aiku"
        case .both: "Both"
        }
    }

    var summaryWord: String {
        switch self {
        case .mine: "mine"
        case .aiku: "Aiku"
        case .both: "both"
        }
    }
}

struct SystemLineValue: Hashable, Sendable {
    let label: String
    var quantity: Int?

    init(_ label: String, quantity: Int? = nil) {
        self.label = label
        self.quantity = quantity
    }
}

struct SystemConflictLine: Identifiable, Hashable, Sendable {
    let sko: String
    let location: String
    let name: String
    let required: Int
    let requiredVerb: String
    let mine: SystemLineValue
    let aiku: SystemLineValue
    let mineSummary: String
    let aikuSummary: String
    let aikuBy: String
    var isAdditive = true
    var choice: SystemConflictChoice?

    var id: String { sko }

    var subtitle: String { "\(location) · \(name) · \(required) \(requiredVerb)" }

    var allowsBoth: Bool {
        isAdditive && mine.quantity != nil && aiku.quantity != nil
    }

    var choices: [SystemConflictChoice] {
        allowsBoth ? [.mine, .aiku, .both] : [.mine, .aiku]
    }

    var bothQuantity: Int? {
        guard allowsBoth, let mineQuantity = mine.quantity, let aikuQuantity = aiku.quantity else { return nil }
        return min(mineQuantity + aikuQuantity, required)
    }

    var bothLabel: String {
        guard let bothQuantity, let mineQuantity = mine.quantity, let aikuQuantity = aiku.quantity else { return "" }
        return mineQuantity + aikuQuantity > required ? "\(bothQuantity) (max)" : "\(bothQuantity)"
    }

    func label(for choice: SystemConflictChoice) -> String {
        switch choice {
        case .mine: mine.label
        case .aiku: aiku.label
        case .both: bothLabel
        }
    }
}

struct SystemSameLine: Identifiable, Hashable, Sendable {
    let sko: String
    let chip: Chip

    var id: String { sko }
}

struct SystemConflict: Identifiable, Hashable, Sendable {
    let reference: String
    let kind: String
    let title: String
    let headerTitle: String
    let summary: String
    let icon: AppIcon
    var lines: [SystemConflictLine]
    let sameLines: [SystemSameLine]
    var isResolved = false
    var isDiscarded = false

    var id: String { reference }

    var totalLines: Int { lines.count + sameLines.count }

    var differenceLabel: String {
        lines.count == 1 ? "1 line differs" : "\(lines.count) lines differ"
    }

    var isOpen: Bool { !isResolved && !isDiscarded }

    var undecidedCount: Int { lines.filter { $0.choice == nil }.count }

    var canApply: Bool { !lines.isEmpty && undecidedCount == 0 }

    var choiceSummary: String {
        let order: [SystemConflictChoice] = [.both, .mine, .aiku]
        return order.compactMap { choice in
            let count = lines.filter { $0.choice == choice }.count
            return count > 0 ? "\(count) \(choice.summaryWord)" : nil
        }
        .joined(separator: ", ")
    }
}
