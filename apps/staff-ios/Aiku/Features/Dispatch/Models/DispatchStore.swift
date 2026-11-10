import Foundation
import Observation

@MainActor
@Observable
final class DispatchStore {
    static let shared = DispatchStore()

    let shippers: [DispatchShipper]
    private(set) var sessions: [DispatchPickingSession]
    private(set) var bays: [DispatchPickedBay]
    private(set) var reports: [DispatchProblemReport] = []
    private let trolleyCodes: [String]
    private let trolleyNotes: [String: String]

    init(
        shippers: [DispatchShipper] = DispatchSampleData.shippers,
        sessions: [DispatchPickingSession] = DispatchSampleData.sessions,
        bays: [DispatchPickedBay] = DispatchSampleData.bays,
        trolleyCodes: [String] = DispatchSampleData.trolleyCodes,
        trolleyNotes: [String: String] = DispatchSampleData.trolleyNotes
    ) {
        self.shippers = shippers
        self.sessions = sessions
        self.bays = bays
        self.trolleyCodes = trolleyCodes
        self.trolleyNotes = trolleyNotes
    }

    func session(_ reference: String) -> DispatchPickingSession? {
        sessions.first { $0.reference == reference } ?? sessions.first
    }

    func sessions(of kind: DispatchSessionKind) -> [DispatchPickingSession] {
        sessions.filter { $0.kind == kind }
    }

    var trolleys: [DispatchTrolley] {
        trolleyCodes.map { code in
            if let note = trolleyNotes[code] {
                return DispatchTrolley(code: code, holder: .deliveryNote(note))
            }
            if let holder = sessions.first(where: { $0.isStarted && $0.state != .packed && $0.trolleys.contains(code) }) {
                return DispatchTrolley(code: code, holder: .session(holder.reference))
            }
            return DispatchTrolley(code: code, holder: .free)
        }
    }

    func startPicking(_ reference: String) {
        update(reference) { session in
            session.isStarted = true
        }
    }

    @discardableResult
    func scanPick(_ reference: String) -> DispatchPickNote? {
        guard let location = session(reference)?.currentLocation,
              let note = location.notes.first(where: \.isOpen) else { return nil }
        pick(reference, at: location.location, amount: 1)
        return note
    }

    func pick(_ reference: String, at location: String, amount: Int) {
        guard amount > 0 else { return }
        update(reference) { session in
            guard let index = session.locations.firstIndex(where: { $0.location == location }) else { return }
            session.isStarted = true
            var left = amount
            for noteIndex in session.locations[index].notes.indices where left > 0 {
                let note = session.locations[index].notes[noteIndex]
                guard note.isOpen else { continue }
                let take = min(left, note.required - note.picked)
                session.locations[index].notes[noteIndex].picked += take
                left -= take
            }
        }
        settle(reference)
    }

    func pickAll(_ reference: String, at location: String) {
        guard let remaining = session(reference)?.locations.first(where: { $0.location == location })?.remaining else { return }
        pick(reference, at: location, amount: remaining)
    }

    func setWaiting(_ reference: String, at location: String) {
        update(reference) { session in
            guard let index = session.locations.firstIndex(where: { $0.location == location }),
                  let noteIndex = session.locations[index].notes.firstIndex(where: \.isOpen) else { return }
            session.locations[index].notes[noteIndex].isWaiting = true
        }
        settle(reference)
    }

    @discardableResult
    func scanPack(_ reference: String) -> DispatchPackNote? {
        guard let note = session(reference)?.currentPackNote else { return nil }
        pack(reference, deliveryNote: note.deliveryNote, amount: 1)
        return note
    }

    func pack(_ reference: String, deliveryNote: String, amount: Int) {
        guard amount > 0 else { return }
        update(reference) { session in
            guard session.state == .pickingFinished,
                  let index = session.packNotes.firstIndex(where: { $0.deliveryNote == deliveryNote }) else { return }
            let note = session.packNotes[index]
            session.packNotes[index].packed = min(note.items, note.packed + amount)
        }
    }

    func packAll(_ reference: String, deliveryNote: String) {
        guard let note = session(reference)?.packNotes.first(where: { $0.deliveryNote == deliveryNote }) else { return }
        pack(reference, deliveryNote: deliveryNote, amount: note.remaining)
    }

    func submit(_ report: DispatchProblemReport) {
        reports.append(report)
    }

    private func settle(_ reference: String) {
        guard let current = sessions.first(where: { $0.reference == reference }) else { return }
        switch current.state {
        case .pickingFinished where current.packNotes.isEmpty:
            update(reference) { session in
                session.packNotes = session.deliveryNoteLines
            }
        case .waiting where current.bay == nil:
            guard let bayIndex = bays.firstIndex(where: \.isFree) else { return }
            let waitingNotes = Set(current.allNotes.filter(\.isWaiting).map(\.deliveryNote))
            var notes: [DispatchBayNote] = []
            for note in current.allNotes where !notes.contains(where: { $0.deliveryNote == note.deliveryNote }) {
                notes.append(DispatchBayNote(deliveryNote: note.deliveryNote, isWaiting: waitingNotes.contains(note.deliveryNote)))
            }
            bays[bayIndex].notes = notes
            let bayName = bays[bayIndex].name
            update(reference) { $0.bay = bayName }
        default:
            break
        }
    }

    private func update(_ reference: String, _ change: (inout DispatchPickingSession) -> Void) {
        guard let index = sessions.firstIndex(where: { $0.reference == reference }) else { return }
        change(&sessions[index])
    }
}
