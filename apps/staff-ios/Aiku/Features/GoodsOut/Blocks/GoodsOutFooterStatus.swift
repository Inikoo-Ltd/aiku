import SwiftUI

struct GoodsOutFooterStatus: View {
    let title: String
    var detail: String?

    var body: some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(title)
                .font(.aiku(15, .bold))
                .foregroundStyle(AikuColor.text)
            if let detail {
                Text(detail)
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text3)
                    .fixedSize(horizontal: false, vertical: true)
            }
        }
        .frame(maxWidth: .infinity, minHeight: 52, alignment: .leading)
        .accessibilityElement(children: .combine)
    }
}

struct GoodsOutFooterCaption: View {
    let text: String

    init(_ text: String) {
        self.text = text
    }

    var body: some View {
        Text(text)
            .font(.aiku(12))
            .foregroundStyle(AikuColor.text4)
            .multilineTextAlignment(.center)
            .frame(maxWidth: .infinity)
    }
}

struct GoodsOutSectionTitle: View {
    let title: String
    var detail: String?

    var body: some View {
        HStack(alignment: .firstTextBaseline) {
            Text(title.uppercased())
                .font(.aiku(13, .bold))
                .tracking(0.8)
                .foregroundStyle(AikuColor.text3)
                .accessibilityAddTraits(.isHeader)
            Spacer(minLength: 8)
            if let detail {
                Text(detail)
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text3)
            }
        }
        .padding(.horizontal, 4)
    }
}
