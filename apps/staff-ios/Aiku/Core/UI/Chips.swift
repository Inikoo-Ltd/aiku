import SwiftUI

enum ChipStyle: Sendable {
    case tag
    case pill
}

struct StatusChip: View {
    let text: String
    var tone: Tone = .neutral
    var style: ChipStyle = .tag

    init(_ text: String, tone: Tone = .neutral, style: ChipStyle = .tag) {
        self.text = text
        self.tone = tone
        self.style = style
    }

    init(_ chip: Chip, style: ChipStyle = .tag) {
        self.init(chip.text, tone: chip.tone, style: style)
    }

    var body: some View {
        Text(text)
            .font(.aiku(style == .tag ? 11 : 12, .semibold))
            .lineLimit(1)
            .fixedSize()
            .foregroundStyle(tone.foreground)
            .padding(.horizontal, style == .tag ? 7 : 10)
            .padding(.vertical, style == .tag ? 2 : 4)
            .background(tone.background, in: RoundedRectangle(cornerRadius: style == .tag ? 6 : 999))
    }
}

struct CountBadge: View {
    let count: Int

    var body: some View {
        Text(count > 99 ? "99+" : "\(count)")
            .font(.aiku(10, .bold))
            .foregroundStyle(AikuColor.onBadge)
            .padding(.horizontal, 5)
            .frame(minWidth: 18, minHeight: 18)
            .background(AikuColor.badge, in: Capsule())
    }
}

struct FilterChip: View {
    let title: String
    var count: Int?
    let isSelected: Bool
    let action: () -> Void

    init(_ title: String, count: Int? = nil, isSelected: Bool, action: @escaping () -> Void) {
        self.title = title
        self.count = count
        self.isSelected = isSelected
        self.action = action
    }

    var body: some View {
        Button(action: action) {
            HStack(spacing: 6) {
                Text(title)
                if let count {
                    Text("\(count)").foregroundStyle(isSelected ? AikuColor.ink : AikuColor.text4)
                }
            }
            .font(.aiku(13, .semibold))
            .foregroundStyle(isSelected ? AikuColor.ink : AikuColor.text2)
            .padding(.horizontal, 12)
            .frame(minHeight: 36)
            .background(isSelected ? AikuColor.soft : AikuColor.surface, in: Capsule())
            .overlay(Capsule().strokeBorder(isSelected ? AikuColor.softBd : AikuColor.borderStrong))
            .contentShape(Capsule())
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(isSelected ? .isSelected : [])
    }
}

struct FilterChipBar<Option: Hashable>: View {
    let options: [Option]
    @Binding var selection: Option
    let title: (Option) -> String
    var count: ((Option) -> Int?)?

    init(options: [Option], selection: Binding<Option>, title: @escaping (Option) -> String, count: ((Option) -> Int?)? = nil) {
        self.options = options
        _selection = selection
        self.title = title
        self.count = count
    }

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                ForEach(options, id: \.self) { option in
                    FilterChip(title(option), count: count?(option), isSelected: option == selection) {
                        selection = option
                    }
                }
            }
            .padding(.horizontal, 16)
        }
        .padding(.horizontal, -16)
    }
}
