import SwiftUI

struct GoodsOutChannelChips: View {
    let channel: GoodsOutChannel
    var platform: String?

    var body: some View {
        FlowLayout(spacing: 6, lineSpacing: 6) {
            StatusChip(channel.title, tone: .info)
            if let platform {
                GoodsOutPlatformBadge(platform: platform)
            }
        }
    }
}

struct GoodsOutPlatformBadge: View {
    let platform: String

    var body: some View {
        HStack(spacing: 6) {
            Text(String(platform.prefix(1)))
                .font(.aiku(9, .bold))
                .foregroundStyle(AikuColor.text3)
                .frame(width: 20, height: 20)
                .background(AikuColor.bg, in: Circle())
                .accessibilityLabel("\(platform) logo")
            Text(platform)
                .font(.aiku(12, .bold))
                .foregroundStyle(AikuColor.text2)
        }
        .padding(.leading, 4)
        .padding(.trailing, 10)
        .frame(height: 28)
        .background(AikuColor.surface, in: Capsule())
        .overlay(Capsule().strokeBorder(AikuColor.borderStrong))
    }
}
