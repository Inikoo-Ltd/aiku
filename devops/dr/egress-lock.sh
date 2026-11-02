#!/usr/bin/env bash
#
# Rehearsal isolation (INI-133). A DR box holds a copy of production and the production
# .env: left online it would send mail, charge cards, answer marketplaces and post to
# Discord exactly like production. While the lock is on, nothing leaves through the
# network card except to AWS S3 Frankfurt (the pgBackRest repository), the instance
# metadata service (the IAM role's key), the VPC DNS resolver and AWS time. Docker
# containers (imgproxy) are cut off the same way. Replies to incoming connections (ssh,
# the browser through the hosts-file entry) still flow.
#
# Not for a real disaster: there the box IS production and must reach the world.
#
# Usage (as root):  egress-lock.sh on | off | status
#
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "run as root"; exit 1; }

nic=$(ip route show default | awk '{print $5; exit}')

case ${1:-} in
  on)
    ranges=$(curl -fsS https://ip-ranges.amazonaws.com/ip-ranges.json)
    s3v4=$(jq -r '[.prefixes[] | select(.service=="S3" and .region=="eu-central-1") | .ip_prefix] | join(", ")' <<<"$ranges")
    s3v6=$(jq -r '[.ipv6_prefixes[] | select(.service=="S3" and .region=="eu-central-1") | .ipv6_prefix] | join(", ")' <<<"$ranges")
    [[ -n $s3v4 ]] || { echo "no S3 eu-central-1 ranges found; not locking"; exit 1; }
    nft delete table inet aiku_dr_egress 2>/dev/null || true
    nft -f - <<NFT
table inet aiku_dr_egress {
  set s3v4 { type ipv4_addr; flags interval; elements = { $s3v4 } }
  set s3v6 { type ipv6_addr; flags interval; ${s3v6:+elements = { $s3v6 }} }
  chain output_out {
    type filter hook output priority 0; policy accept;
    oifname != "$nic" accept
    ct state established,related accept
    ip daddr { 169.254.169.254, 169.254.169.253, 169.254.169.123 } accept
    meta l4proto { udp, tcp } th dport 53 accept
    ip daddr @s3v4 tcp dport 443 accept
    ip6 daddr @s3v6 tcp dport 443 accept
    meta l4proto tcp counter log prefix "aiku-dr-egress-drop " level info reject with tcp reset
    counter log prefix "aiku-dr-egress-drop " level info reject
  }
  chain forward_out {
    type filter hook forward priority 0; policy accept;
    oifname "$nic" ct state established,related accept
    oifname "$nic" meta l4proto tcp counter reject with tcp reset
    oifname "$nic" counter reject
  }
}
NFT
    # survives a reboot: the lock must hold before supervisor starts Horizon again
    nft list table inet aiku_dr_egress > /etc/nftables.conf
    systemctl enable nftables
    echo "egress locked on $nic"
    ;;
  off)
    nft delete table inet aiku_dr_egress
    echo "#!/usr/sbin/nft -f" > /etc/nftables.conf
    systemctl disable nftables
    echo "egress open"
    ;;
  status)
    if nft list table inet aiku_dr_egress >/dev/null 2>&1; then
      echo "LOCKED on $nic"
      nft list table inet aiku_dr_egress | grep -E 'counter packets [1-9]' || true
      # proof: the outside world must fail, S3 must answer
      curl -sS -m 5 -o /dev/null https://www.google.com && echo "FAIL: internet reachable" || echo "ok: internet blocked"
      curl -sS -m 5 -o /dev/null -w 'ok: S3 answers %{http_code}\n' https://s3.eu-central-1.amazonaws.com/
    else
      echo "OPEN"
    fi
    ;;
  *) echo "usage: $0 on|off|status"; exit 1 ;;
esac
