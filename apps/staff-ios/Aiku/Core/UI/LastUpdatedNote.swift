import SwiftUI

struct LastUpdatedNote: View {
    let date: Date
    var savedRowsOnly = false

    var body: some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(Self.text(date))
                .font(.aiku(12))
                .foregroundStyle(AikuColor.text3)
            if savedRowsOnly {
                Text(L10n.text(Self.savedRowsOnlyText))
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text3)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(.horizontal, 4)
    }

    static func text(_ date: Date) -> String {
        let time = date.formatted(date: .omitted, time: .shortened)
        guard Calendar.current.isDateInToday(date) else {
            return "Last updated \(date.formatted(.dateTime.day().month(.abbreviated))) \(time)"
        }
        return "Last updated \(time)"
    }

    static let savedRowsOnlyText = "Saved copy: only rows loaded before are included"
}
