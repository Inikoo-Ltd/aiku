import SwiftUI

struct GoodsOutTimeline: View {
    let note: GoodsOutDeliveryNote

    private var entries: [(title: String, date: Date?)] {
        var result: [(String, Date?)] = [("Created", note.createdAt)]
        for state in GoodsOutDeliveryNoteState.flow.dropFirst() {
            result.append((state.title, note.stateDates[state]))
            if state == .picking, let waiting = note.stateDates[.waiting] {
                result.append((GoodsOutDeliveryNoteState.waiting.title, waiting))
            }
        }
        return result
    }

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 0) {
                ForEach(entries, id: \.title) { entry in
                    VStack(spacing: 4) {
                        Text(entry.title)
                            .font(.aiku(11, .semibold))
                            .foregroundStyle(entry.date == nil ? AikuColor.faint : AikuColor.text)
                            .lineLimit(1)
                            .minimumScaleFactor(0.8)
                        Circle()
                            .fill(entry.date == nil ? AikuColor.borderStrong : AikuColor.success)
                            .frame(width: 12, height: 12)
                        Text(entry.date.map(GoodsOutDateFormat.stamp) ?? "–")
                            .font(.aiku(11))
                            .foregroundStyle(AikuColor.text3)
                            .lineLimit(1)
                    }
                    .frame(width: 104)
                    .accessibilityElement(children: .combine)
                }
            }
            .padding(.vertical, 10)
        }
        .cardSurface()
        .accessibilityLabel("Timeline")
    }
}
