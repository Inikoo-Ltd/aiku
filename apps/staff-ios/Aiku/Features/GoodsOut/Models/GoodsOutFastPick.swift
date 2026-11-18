import Foundation

enum GoodsOutQuickLineChange: Sendable {
    case pickAll
    case notPicked(GoodsOutNotPickedReason)

    func apply(to line: inout GoodsOutLine) {
        switch self {
        case .pickAll: line.pick(line.remaining)
        case .notPicked(let reason): line.markNotPicked(reason)
        }
    }
}

extension GoodsOutStore {
    func quickLine(
        _ action: GoodsOutNoteAction,
        line lineID: GoodsOutLine.ID,
        change: GoodsOutQuickLineChange,
        on reference: String
    ) async -> GoodsOutActionOutcome {
        guard isRemote, let id = noteID(for: reference) else {
            return .failed(message: "This delivery note is not in the selected warehouse.")
        }
        guard !isSending(lineID, on: reference),
              let noteIndex = notes.firstIndex(where: { $0.reference == reference }),
              let lineIndex = notes[noteIndex].lines.firstIndex(where: { $0.id == lineID }) else {
            return .nothingChanged(message: nil)
        }
        let original = notes[noteIndex].lines[lineIndex]
        var optimistic = original
        change.apply(to: &optimistic)
        notes[noteIndex].lines[lineIndex] = optimistic
        setPending(optimistic, id: lineID, on: reference)

        let previous = lineQueues[reference]
        let request = Task { [service] () -> Result<GoodsOutActionResponseDTO, Error> in
            await previous?.value
            do {
                return .success(try await service.perform(id, action))
            } catch {
                return .failure(error)
            }
        }
        lineQueues[reference] = Task { _ = await request.value }

        let result = await request.value
        setPending(nil, id: lineID, on: reference)
        if pendingLines[reference] == nil {
            lineQueues[reference] = nil
        }
        switch result {
        case .success(let response):
            store(response.data)
            reapplyPendingLines(on: reference)
            let message = response.result?.message.flatMap(GoodsOutMapper.nonEmpty)
            return response.isNothingChanged ? .nothingChanged(message: message) : .done(message: message)
        case .failure(let error):
            replaceLine(original, on: reference)
            if error.isCancellation { return .failed(message: "") }
            return .failed(message: GoodsOutMapper.message(for: error))
        }
    }

    private func reapplyPendingLines(on reference: String) {
        let stillSending = pendingLines[reference].map { Array($0.values) } ?? []
        for line in stillSending {
            replaceLine(line, on: reference)
        }
    }

    private func replaceLine(_ line: GoodsOutLine, on reference: String) {
        guard let noteIndex = notes.firstIndex(where: { $0.reference == reference }),
              let lineIndex = notes[noteIndex].lines.firstIndex(where: { $0.id == line.id }) else { return }
        notes[noteIndex].lines[lineIndex] = line
    }
}
