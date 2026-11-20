import Foundation

enum OfflineTexts {
    static func quantity(_ value: Double?) -> String {
        guard let value else { return "" }
        return value.rounded() == value ? String(Int(value)) : String(format: "%g", value)
    }

    static func reason(_ result: OfflineResult) -> String? {
        reason(result.reason) ?? result.message
    }

    static func reason(_ key: String?) -> String? {
        switch key {
        case "over_required": "More than the line still needed"
        case "over_packed": "More than was picked"
        case "over_checked": "More than was checked"
        case "over_expected": "More than the return expected"
        case "location_empty": "Aiku shows no stock at this location"
        case "not_enough_stock": "Aiku shows less stock at this location"
        case "wrong_state": "It had already moved on to another stage"
        case "not_handler": "Someone else is handling it"
        case "note_cancelled": "The delivery note was cancelled"
        case "return_closed": "The return was already closed"
        case "delivery_closed": "The delivery was already booked in"
        case "waiting_cleared": "The waiting was cleared after you set it"
        case "setting_off": "This is switched off for your organisation"
        case "in_picking_session": "The line is in a picking session"
        case "other_change": "Someone changed the stock after you downloaded it"
        case "not_found": "It no longer exists"
        case "no_right": "You no longer have the right to do this"
        case "invalid": "Aiku could not read this action"
        default: nil
        }
    }

    static func instruction(_ entry: OfflineOperationEntry) -> String? {
        let location = entry.labels.locationCode.flatMap { $0.isEmpty ? nil : $0 }
        let missing = entry.missingQuantity
        let requested = entry.requestedQuantity
        switch entry.instruction {
        case OfflineInstruction.putBack:
            if let location, entry.status == OfflineOutcomeStatus.partlyApplied, let missing {
                return "Put the other \(quantity(missing)) back at \(location)"
            }
            if let location, let amount = missing ?? requested {
                return "Put \(quantity(amount)) back at \(location)"
            }
            return "Put the goods back where they came from"
        case OfflineInstruction.unpack:
            return "Take \(quantity(missing ?? requested ?? 0)) out of the box again"
        case OfflineInstruction.keepInTote:
            return "Keep the goods in the tote: a stock check has been asked for"
        case OfflineInstruction.pickAgain:
            return "Pick this line again"
        case OfflineInstruction.recount:
            return "Count the location again"
        case OfflineInstruction.checkShelf:
            return "Check the shelf"
        default:
            return nil
        }
    }

    static func state(_ entry: OfflineOperationEntry) -> Chip? {
        switch entry.currentState {
        case OfflineOutcomeStatus.applied, OfflineOutcomeStatus.alreadyDone: Chip("Recorded", .success)
        case OfflineOutcomeStatus.partlyApplied: Chip("Partly recorded", .warn)
        case OfflineOutcomeStatus.refused: Chip("Not recorded", .danger)
        case OfflineOutcomeStatus.conflict: Chip("Conflict", .danger)
        case OfflineOutcomeStatus.blocked: Chip("Waiting for an earlier action", .neutral)
        case OfflineOutcomeStatus.resolved: Chip("Resolved", .success)
        case OfflineOutcomeStatus.rolledBack: Chip("Rolled back", .neutral)
        case OfflineOutcomeStatus.expired: Chip("Expired", .danger)
        default: nil
        }
    }

    static func queueState(_ entry: QueueEntry) -> Chip {
        switch entry.state {
        case .waiting: Chip("Waiting", .info)
        case .sending: Chip("Sending", .info)
        case .applied: Chip("Synced", .success)
        case .needsYou: Chip("Needs you", .warn)
        case .void: Chip("Undone")
        }
    }

    static func count(_ count: Int, _ one: String, _ many: String) -> String {
        count == 1 ? "1 \(one)" : "\(count) \(many)"
    }
}
