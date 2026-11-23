import SwiftUI

struct RecordRow<Leading: View>: View {
    let reference: String
    var date: String?
    var dateCaption: String?
    var party: String?
    var badges: [Chip] = []
    var details: [String] = []
    var action: (() -> Void)?
    @ViewBuilder var leading: () -> Leading

    init(
        _ reference: String,
        date: String? = nil,
        dateCaption: String? = nil,
        party: String? = nil,
        badges: [Chip] = [],
        details: [String] = [],
        action: (() -> Void)? = nil,
        @ViewBuilder leading: @escaping () -> Leading
    ) {
        self.reference = reference
        self.date = date
        self.dateCaption = dateCaption
        self.party = party
        self.badges = badges
        self.details = details
        self.action = action
        self.leading = leading
    }

    var body: some View {
        if let action {
            Button(action: action) { content }
                .buttonStyle(.plain)
        } else {
            content
        }
    }

    private var content: some View {
        HStack(alignment: .center, spacing: 12) {
            leading()
            VStack(alignment: .leading, spacing: 6) {
                Text(reference)
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.text)
                    .lineLimit(1)
                    .truncationMode(.middle)
                if !badges.isEmpty || !(party ?? "").isEmpty {
                    FlowLayout(spacing: 6, lineSpacing: 4) {
                        ForEach(Array(badges.enumerated()), id: \.offset) { _, chip in
                            StatusChip(chip)
                        }
                        if let party, !party.isEmpty {
                            Text(party)
                                .font(.aiku(14, .medium))
                                .foregroundStyle(AikuColor.text2)
                                .lineLimit(1)
                        }
                    }
                }
                if !details.isEmpty {
                    Text(details.joined(separator: " · "))
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                        .lineLimit(2)
                }
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            if let date {
                VStack(alignment: .trailing, spacing: 2) {
                    HStack(spacing: 4) {
                        AppIcon.faCalendar.image
                            .font(.system(size: 11))
                            .foregroundStyle(AikuColor.text3)
                            .accessibilityHidden(true)
                        Text(date)
                            .font(.aiku(13, .semibold))
                            .foregroundStyle(AikuColor.text2)
                            .lineLimit(1)
                    }
                    if let caption = shownCaption {
                        Text(caption)
                            .font(.aiku(11))
                            .foregroundStyle(AikuColor.text3)
                            .lineLimit(1)
                    }
                }
                .frame(maxHeight: .infinity, alignment: .top)
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .frame(minHeight: 64)
        .contentShape(Rectangle())
        .accessibilityElement(children: .combine)
    }

    private var shownCaption: String? {
        guard let dateCaption else { return nil }
        let translated = L10n.text(dateCaption)
        let repeatsStatus = badges.contains { badge in
            badge.text.localizedCaseInsensitiveCompare(dateCaption) == .orderedSame
                || badge.text.localizedCaseInsensitiveCompare(translated) == .orderedSame
        }
        return repeatsStatus ? nil : translated
    }
}

extension RecordRow where Leading == EmptyView {
    init(
        _ reference: String,
        date: String? = nil,
        dateCaption: String? = nil,
        party: String? = nil,
        badges: [Chip] = [],
        details: [String] = [],
        action: (() -> Void)? = nil
    ) {
        self.init(reference, date: date, dateCaption: dateCaption, party: party, badges: badges, details: details, action: action) { EmptyView() }
    }
}

enum RecordDateCaption {
    static let expected = "Expected"
    static let dispatched = "Dispatched"
    static let received = "Received"
    static let bookedIn = "Booked in"
    static let started = "Started"
    static let created = "Created"

    static func unlessRepeating(_ caption: String, status: String?) -> String? {
        guard let status = status?.trimmingCharacters(in: .whitespacesAndNewlines), !status.isEmpty else { return caption }
        return status.localizedCaseInsensitiveCompare(caption) == .orderedSame ? nil : caption
    }
}
