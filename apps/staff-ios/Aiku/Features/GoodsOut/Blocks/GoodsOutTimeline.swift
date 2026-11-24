import SwiftUI

struct GoodsOutTimeline: View {
    let note: GoodsOutDeliveryNote

    private var steps: [StateTimelineStep] {
        var result = [StateTimelineStep(label: "Created", date: GoodsOutDateFormat.stamp(note.createdAt))]
        for state in GoodsOutDeliveryNoteState.flow.dropFirst() {
            result.append(StateTimelineStep(label: state.title, date: note.stateDates[state].map(GoodsOutDateFormat.stamp)))
            if state == .picking, let waiting = note.stateDates[.waiting] {
                result.append(StateTimelineStep(label: GoodsOutDeliveryNoteState.waiting.title, date: GoodsOutDateFormat.stamp(waiting)))
            }
        }
        return result
    }

    var body: some View {
        StateTimeline(steps: steps)
    }
}
