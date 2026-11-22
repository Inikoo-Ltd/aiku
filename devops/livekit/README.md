# Calls server: LiveKit on its own Hetzner Cloud box (INI-444)

Voice and video calls with screen sharing for staff chat. LiveKit (open source, Apache 2.0)
forwards each person's camera, microphone or screen to the others without re-encoding, so it
needs bandwidth more than CPU. Laravel never carries call traffic: it signs short-lived tokens
with the API key and secret, and LiveKit refuses anyone without one.

It runs on its own small Hetzner Cloud server, not on boro or litio: Cloudflare's proxy does not
carry UDP media, so this box is reached directly on DNS-only names, and a long call must never
compete with orders and the websites.

**As built on 21 Nov 2026:** server `erbio` (erbium, after the fibre amplifiers that carry long-distance calls; named `calls` until 22 Nov 2026), Hetzner Cloud **CX23** (2 shared vCPU, 4 GB, 40 GB) in
**Falkenstein**, Ubuntu 24.04, IPv4 `46.224.136.237`, EUR 5.49 + EUR 0.50 for the IPv4 = EUR 5.99
a month excl. VAT. The CX33 recommended below was listed at EUR 8.49 and marked "not available" in
Nuremberg, Falkenstein and Helsinki that day, so Raul chose the CX23; rescale when a larger type is
needed. Hetzner firewall `erbio` attached, DNS-only A records `calls.aiku.io` and
`calls-turn.aiku.io` in Cloudflare, certificate registered without an email
(`--register-unsafely-without-email` in place of `-m $LE_EMAIL` in step 10). One addition to the
steps: `60-livekit.conf` goes to `/etc/sysctl.d/` (then `sysctl --system`) before the dry start,
which removes LiveKit's "UDP receive buffer is too small" warning.

Everything here is for rebuilding the box from nothing without Raul. Files in this folder:

| File | Goes to |
| --- | --- |
| `livekit.yaml` | `/etc/livekit/livekit.yaml` (no secrets) |
| `livekit.service` | `/etc/systemd/system/livekit.service` |
| `haproxy.cfg` | `/etc/haproxy/haproxy.cfg` |
| `haproxy-cert-deploy` | `/etc/letsencrypt/renewal-hooks/deploy/haproxy-cert-deploy` |
| `10-hardening.conf` | `/etc/ssh/sshd_config.d/10-hardening.conf` |
| `60-livekit.conf` | `/etc/sysctl.d/60-livekit.conf` |
| `../server-metrics.sh`, `../systemd/aiku-server-metrics.service` | DevOps dashboard sampler, as on every box |

Pinned version: **LiveKit v1.13.11** (released 25 Oct 2026), binary
`livekit_1.13.11_linux_amd64.tar.gz`, sha256
`1e9ee90ca301e1842b2887fc77cb122759756a9c8aeb4334ee91de3325ec43fb` (GitHub's asset digest,
checked against a download on 21 Nov 2026).

## 1. Decision: server type, location, load

**Recommended: Hetzner Cloud CX33 (4 shared vCPU, 8 GB RAM, 80 GB disk, 20 TB traffic) in
Nuremberg (NBG1), Ubuntu 24.04. EUR 8.99/month excl. VAT.** Measure it during the trials; move
to **CCX13** (2 dedicated vCPU, 8 GB, 80 GB, 20 TB, EUR 43.49 in NBG1, EUR 36.99 in Helsinki)
only if the measurements below show the shared CPUs hurting calls. Prices as listed on
hetzner.com/cloud on 21 Nov 2026.

The risk with shared vCPUs, and why it is measured rather than paid for up front: calls are
real time, and a late packet is as bad as a lost one. On shared vCPUs the physical cores are
shared with other customers' servers; Hetzner describes them as "a baseline CPU performance"
with bursts, "for workloads that can handle variable CPU performance". When a neighbour is busy
the hypervisor runs someone else on "our" core (CPU steal), and packets wait in short bursts:
jitter, robotic audio, frozen video. Dedicated vCPUs (CCX) "have their CPU resources
exclusively" ("no noisy neighbors" in Hetzner's words). But our load is well under one core, so a
CX33 spends most of its time idle and a short steal burst usually still leaves time to forward
packets. Whether it does on our box is a fact `vmstat` shows in two minutes (see "Comparing
shared and dedicated" below): the CX33 and CCX13 have the same disk, so the same server moves
between them in a few minutes, keeping everything installed.

Assumptions (about 100 staff, a handful of small calls at a time):

| | Typical | Busy peak |
| --- | --- | --- |
| Simultaneous calls | 2 | 4 (one of them 8 people) |
| People per call | 4 | 4, and 8 |
| Camera stream received | 1.5 Mbit/s | 1.5 Mbit/s |
| Screen share received | 2.5 Mbit/s | 2.5 Mbit/s |
| Audio | 32 kbit/s, negligible | negligible |

The server sends every stream to everyone else, so a call of N people with cameras costs
N x (N-1) x 1.5 Mbit/s outgoing, plus (N-1) x 2.5 Mbit/s while someone shares a screen.
Hetzner bills outgoing traffic only.

- One 4-person call with a screen share: 4x3x1.5 + 3x2.5 = about **25 Mbit/s** out.
- One 8-person call: 8x7x1.5 = **84 Mbit/s** out.
- Busy peak (three 4-person calls and one 8-person call): about **160 Mbit/s** out.

Monthly traffic, 22 working days:

- Typical, 2 calls for 5 hours a day: 50 Mbit/s x 5 h x 22 = **about 2.5 TB**.
- Heavy, 4 calls for 8 hours a day: 100 Mbit/s x 8 h x 22 = **about 8 TB**.

Both are well inside the 20 TB that CCX13 and CX33 each include in EU locations.

CPU: LiveKit's benchmark ran a 150-person video meeting on a 16-core c2-standard-16 at 85% CPU,
sending 93 MB/s (about 750 Mbit/s) in about 760,000 packets a second. Forwarding cost follows
packets more than bytes. Our busy peak is about 160 Mbit/s of mostly full-size video packets,
roughly 20,000 packets a second: well under one core. Scaled by bytes instead (an upper bound) it
would be about 3 cores. The CX33's 4 vCPU carry that with a wide margin. If CPU on the dashboard
stays above 60% during calls, rescale to CX43 (8 shared vCPU, 16 GB, 160 GB, EUR 16.49); if
CPU steal is the problem instead, to CCX13. Memory is not a factor without Redis or recording.

Why NBG1: central for the UK, Spain, Portugal, Slovakia and Bulgaria (Helsinki adds distance to
Iberia and the UK). Hetzner lists CX33 in NBG1 and HEL1 only; on 21 Nov 2026 its product pages
marked the shared CX types "currently unavailable". If the console will not create a CX33 in
NBG1, take a CX33 in HEL1 (same price), else a CCX13.

### Comparing shared and dedicated (a day each)

The CX33 and CCX13 are both x86 with the same 80 GB disk, so one server can be moved between them
in both directions with Hetzner's Rescale, keeping its disk and everything installed.

1. Hetzner console > the server > Power > **Power off** (or `poweroff` on the box). Wait for
   "Off".
2. **Rescale** tab: pick the other type. Leave the disk size as it is (when moving up to a type
   with a bigger disk, keep the current disk size; Hetzner allows downgrading "to a server plan
   with the same disk size" only, and a disk can never shrink).
3. **Power on**. Expect a few minutes without calls (Hetzner does not document the duration);
   do it when `lk room list` is empty.
4. On the box check the IPv4 is unchanged, since `livekit.yaml` pins `node_ip`, and LiveKit is up:

   ```bash
   ip -4 -o addr show scope global
   ```

   ```bash
   systemctl is-active livekit haproxy && curl -s http://127.0.0.1:7880/
   ```

Limits (Hetzner servers FAQ): rescale only within the same architecture (x86 to x86, never to
Arm CAX), never to another location (that needs a new server), and only to a type the location
currently offers.

What to measure, the same call both days (for example three people in different countries,
cameras on, one screen share, 30 minutes):

- In each caller's Chrome, `chrome://webrtc-internals`, the received video (`inbound-rtp`,
  kind video): `packetsLost`, `jitter`, `freezeCount` and `framesDropped`; and the active
  `candidate-pair`: `currentRoundTripTime`. The connection test (section 7, step 4) also reports
  round-trip time and loss per transport.
- On the box during the call, CPU steal is the `st` column (percent of time the hypervisor gave
  our vCPU to someone else), one line a second for two minutes:

  ```bash
  vmstat 1 120
  ```

  `us`/`sy` is LiveKit's own CPU. On the dedicated type `st` should stay at 0.

Read it as: `st` above 1-2% in bursts, jitter above about 30 ms or loss above 1% on the shared
day but not on the dedicated day means the shared type is the problem. Differences that are the
same on both days come from the callers' own networks, not the server.

### Staging path

1. Development: LiveKit in Docker on the developer's machine (`livekit-server --dev`).
2. First trials: this production server, set up by this runbook, with two or three colleagues in
   different countries.
3. The management demo: the same server.

helio is not to be used for calls: its disks are failing, it swaps, and it would need media ports
opened on a box that only accepts traffic from Cloudflare.

**Indonesia.** Calls between Indonesia and Europe go through Nuremberg either way, and that is
fine: a room lives on a single node ("a room must fit on a single node", LiveKit distributed
docs), so a second region would not shorten a mixed call. A second server only helps calls where
**everyone** is in Indonesia. Add one in Singapore (SIN1, e.g. CPX22, EUR 26.99, only 1 TB
traffic included, extra traffic far dearer than in the EU) when Indonesia-only calls are
frequent and staff there complain about lag or freezes. The app would then pick the server URL
per call; a single-server LiveKit without Redis needs nothing else. Not needed now.

## 2. What to choose before starting

| Value | Suggested | Used in |
| --- | --- | --- |
| Signalling hostname `CALLS_DOMAIN` | `calls.aiku.io` | DNS, certificate, `LIVEKIT_URL` |
| TURN hostname `TURN_DOMAIN` | `calls-turn.aiku.io` | DNS, certificate, `livekit.yaml` |
| Let's Encrypt email `LE_EMAIL` | an ops mailbox | expiry notices |
| Server name in Hetzner | `erbio` | also the DevOps dashboard slug |
| SSH key | the same key as boro and litio | Hetzner "SSH keys" |

Two hostnames are required: LiveKit always tells browsers to reach TURN over TLS at
`turns:<TURN_DOMAIN>:443`, and signalling also wants 443, so HAProxy splits 443 by the name the
browser asks for (SNI). One certificate covers both names.

## 3. Hetzner Cloud console (no commands)

1. **Firewalls > Create firewall**, name `erbio`. Inbound rules, source "Any IPv4" and "Any IPv6":

   | Protocol | Port | Why |
   | --- | --- | --- |
   | ICMP | | ping, path MTU |
   | TCP | 22 | SSH, key only, fail2ban (as on boro, litio, helio) |
   | TCP | 80 | Let's Encrypt HTTP-01 issue and renewal (nothing else listens) |
   | TCP | 443 | signalling (WebSocket) and TURN/TLS, split by SNI |
   | UDP | 443 | TURN/UDP |
   | TCP | 7881 | WebRTC over TCP, when UDP is blocked |
   | UDP | 7882 | WebRTC media (UDP mux, all calls on one port) |

   Outbound: leave empty (everything allowed). Cloud firewalls are free.
2. **Servers > Add server**: location Nuremberg, image Ubuntu 24.04, type CX33 (shared
   vCPU, x86), networking public IPv4 + IPv6, SSH key as above, firewall `erbio`, backups off (nothing
   on the box is stateful; this runbook rebuilds it), name `erbio`.
3. Write down the server's public IPv4.

## 4. Cloudflare DNS (zone of `CALLS_DOMAIN`)

Create these **after the dry start in step 9 of section 5 passes**, and before step 10 (the
certificate needs them). Two **A** records to the server's IPv4, **Proxy status: DNS only (grey
cloud)**, TTL Auto: `CALLS_DOMAIN` and `TURN_DOMAIN`. No AAAA records (LiveKit is set to
advertise the IPv4 only).

Why not proxied: Cloudflare's proxy carries only HTTP(S) on its own ports and no UDP at all, so
WebRTC media (UDP 7882), TCP 7881, TURN/UDP and TURN/TLS (not HTTP) would never arrive; it would
also present Cloudflare's certificate and addresses instead of the box's. This box does not hide
behind Cloudflare and does not need to: it holds no data, and LiveKit refuses any connection
without a token signed with aiku's secret.

## 5. Setup on the box

On your Mac, from the repository root (copies this folder and the dashboard sampler; **replace**
`SERVER_IPV4` with the address from section 3):

```bash
scp -r devops/livekit root@SERVER_IPV4:/root/
```

```bash
scp devops/server-metrics.sh devops/systemd/aiku-server-metrics.service root@SERVER_IPV4:/root/livekit/
```

Then log in as root. Set the chosen values once for this shell (**replace the three values**):

```bash
export CALLS_DOMAIN=calls.aiku.io TURN_DOMAIN=calls-turn.aiku.io LE_EMAIL=REPLACE_ME@example.com
```

```bash
export PUBLIC_IPV4=$(ip -4 -o addr show scope global | awk '{print $4}' | cut -d/ -f1 | head -1); echo $PUBLIC_IPV4
```

Check the IPv4 printed is the one in the Hetzner console.

**1. Packages**

```bash
NEEDRESTART_SUSPEND=1 DEBIAN_FRONTEND=noninteractive apt-get update
```

```bash
NEEDRESTART_SUSPEND=1 DEBIAN_FRONTEND=noninteractive apt-get -y upgrade
```

```bash
NEEDRESTART_SUSPEND=1 DEBIAN_FRONTEND=noninteractive apt-get -y install haproxy certbot ufw fail2ban unattended-upgrades tcpdump
```

**2. Hostname and time**

```bash
hostnamectl set-hostname erbio
```

```bash
timedatectl set-timezone UTC
```

**3. SSH: key only, as on the other servers**

```bash
install -m 644 /root/livekit/10-hardening.conf /etc/ssh/sshd_config.d/10-hardening.conf
```

```bash
sshd -t && systemctl reload ssh
```

```bash
systemctl enable --now fail2ban
```

Open a second SSH session now and check you can still log in before going on.

**4. ufw: the same ports as the Hetzner firewall, nothing else**

```bash
ufw default deny incoming
```

```bash
ufw default allow outgoing
```

```bash
ufw allow OpenSSH
```

```bash
ufw allow 80/tcp comment 'letsencrypt'
```

```bash
ufw allow 443/tcp comment 'livekit signal + turn/tls'
```

```bash
ufw allow 443/udp comment 'livekit turn/udp'
```

```bash
ufw allow 7881/tcp comment 'livekit webrtc tcp'
```

```bash
ufw allow 7882/udp comment 'livekit webrtc udp'
```

```bash
ufw --force enable
```

```bash
ufw status verbose
```

Two firewalls on purpose: the Hetzner one stops traffic before the box, ufw still protects it if
the box is ever moved out of the Hetzner firewall. No Docker on this box, so ufw is not bypassed.

**5. Unattended security updates, at night**

Ubuntu 24.04 installs security updates daily by default. Check both lines say "1":

```bash
cat /etc/apt/apt.conf.d/20auto-upgrades
```

If the file is missing or shows "0":

```bash
printf 'APT::Periodic::Update-Package-Lists "1";\nAPT::Periodic::Unattended-Upgrade "1";\n' > /etc/apt/apt.conf.d/20auto-upgrades
```

Move the daily install to 21:30 UTC (late evening in Europe, early morning in Indonesia):

```bash
mkdir -p /etc/systemd/system/apt-daily-upgrade.timer.d
```

```bash
printf '[Timer]\nOnCalendar=\nOnCalendar=*-*-* 21:30 UTC\nRandomizedDelaySec=0\n' > /etc/systemd/system/apt-daily-upgrade.timer.d/override.conf
```

```bash
systemctl daemon-reload && systemctl list-timers apt-daily-upgrade.timer --no-pager
```

What needrestart does here: the LiveKit binary is static, so it is never restarted by a library
update. An OpenSSL update restarts HAProxy: open WebSocket and TURN/TLS connections drop and the
LiveKit client reconnects on its own; media over UDP carries on. Kernel updates wait for a manual
reboot (`/var/run/reboot-required` exists); reboot when no call is running (section 8).

**6. LiveKit binary, pinned**

```bash
curl -fsSL -o /tmp/livekit.tar.gz https://github.com/livekit/livekit/releases/download/v1.13.11/livekit_1.13.11_linux_amd64.tar.gz
```

```bash
echo "1e9ee90ca301e1842b2887fc77cb122759756a9c8aeb4334ee91de3325ec43fb  /tmp/livekit.tar.gz" | sha256sum -c
```

```bash
tar -xzf /tmp/livekit.tar.gz -C /usr/local/bin livekit-server && rm /tmp/livekit.tar.gz
```

```bash
livekit-server --version
```

**7. Service user**

```bash
useradd --system --no-create-home --shell /usr/sbin/nologin livekit
```

```bash
install -d -m 750 -o root -g livekit /etc/livekit
```

**8. API key and secret** (not in the repository)

*First build:* generate a new pair (key `API` + 16 hex characters, secret 64 hex characters):

```bash
(umask 027; printf '%s: %s\n' "API$(openssl rand -hex 8)" "$(openssl rand -hex 32)" > /etc/livekit/keys.yaml)
```

*Rebuild:* write the pair already in aiku's production `.env` instead, so boro and litio need no
change. The command waits for input: paste one line `KEY: SECRET` (the values of
`LIVEKIT_API_KEY` and `LIVEKIT_API_SECRET`, with a space after the colon), press Enter, then
Ctrl-D. The secret is read from the terminal, so it never lands in root's shell history:

```bash
(umask 027; cat > /etc/livekit/keys.yaml)
```

```bash
chown root:livekit /etc/livekit/keys.yaml && chmod 640 /etc/livekit/keys.yaml
```

```bash
cat /etc/livekit/keys.yaml
```

The line printed is `KEY: SECRET`; these two go into aiku's `.env` (section 6). LiveKit refuses a
key file that "others" can read. **The secret must be at least 32 bytes:** aiku refuses shorter
ones (firebase/php-jwt 7 rejects HS256 keys under 32 bytes, so neither tokens nor webhooks would
work), and LiveKit logs an error for them. The generated one is 64 characters.

**9. LiveKit configuration, and a dry start**

```bash
sed -e "s/{{PUBLIC_IPV4}}/$PUBLIC_IPV4/" -e "s/{{TURN_DOMAIN}}/$TURN_DOMAIN/" -e "s/{{LIVEKIT_API_KEY}}/$(cut -d: -f1 /etc/livekit/keys.yaml)/" /root/livekit/livekit.yaml > /etc/livekit/livekit.yaml
```

```bash
grep -n -E 'node_ip|domain|api_key|{{' /etc/livekit/livekit.yaml
```

Dry start: run LiveKit in the foreground for 8 seconds with the real config and key file. This
proves the file parses and every port can be opened, before DNS is pointed at the box and before
the service is installed. Nothing needs DNS for it.

```bash
timeout 8 /usr/local/bin/livekit-server --config /etc/livekit/livekit.yaml; echo "exit code $?"
```

Pass: the log shows the server starting (node IP, ports, TURN) and the last line is
`exit code 124` (still running when `timeout` stopped it). Anything else, or any `error` line in
between (an unknown config key, `secret is too short`, a port in use), must be fixed first.

**10. Certificate for both names, renewed on its own**

DNS from section 4 must already answer with this box's IPv4:

```bash
getent hosts $CALLS_DOMAIN $TURN_DOMAIN
```

```bash
install -D -m 755 /root/livekit/haproxy-cert-deploy /etc/letsencrypt/renewal-hooks/deploy/haproxy-cert-deploy
```

```bash
certbot certonly --standalone --non-interactive --agree-tos -m $LE_EMAIL --cert-name calls -d $CALLS_DOMAIN -d $TURN_DOMAIN
```

```bash
RENEWED_LINEAGE=/etc/letsencrypt/live/calls /etc/letsencrypt/renewal-hooks/deploy/haproxy-cert-deploy
```

Renewal: Ubuntu's `certbot.timer` runs twice a day; certbot's standalone server answers on port
80 (free, HAProxy only uses 443), then the hook rebuilds `/etc/haproxy/certs/calls.pem` and
reloads HAProxy, which keeps open connections. LiveKit holds no certificate (TURN TLS ends in
HAProxy), so a renewal never restarts it. Test the whole renewal path:

```bash
certbot renew --dry-run
```

**11. HAProxy**

```bash
sed -e "s/{{CALLS_DOMAIN}}/$CALLS_DOMAIN/" -e "s/{{TURN_DOMAIN}}/$TURN_DOMAIN/" /root/livekit/haproxy.cfg > /etc/haproxy/haproxy.cfg
```

```bash
haproxy -c -f /etc/haproxy/haproxy.cfg
```

```bash
systemctl enable haproxy && systemctl restart haproxy
```

**12. LiveKit service**

```bash
install -m 644 /root/livekit/livekit.service /etc/systemd/system/livekit.service
```

```bash
systemctl daemon-reload && systemctl enable --now livekit
```

```bash
journalctl -u livekit -n 40 --no-pager
```

The log must show the server starting with the node IP and TURN, and no error (for example
`secret is too short`, `key file others permissions must be set to 0`, or a config parse error).

**13. DevOps dashboard sampler** (CPU, memory, network in and out, as on every box)

```bash
install -m 755 /root/livekit/server-metrics.sh /usr/local/bin/aiku-server-metrics
```

```bash
install -m 644 /root/livekit/aiku-server-metrics.service /etc/systemd/system/aiku-server-metrics.service
```

**Replace** with `DEVOPS_TOKEN` from the production `.env`:

```bash
(umask 077; printf 'SERVER_METRICS_TOKEN=REPLACE_DEVOPS_TOKEN\nSERVER_METRICS_SLUG=erbio\n' > /etc/aiku-server-metrics.env)
```

```bash
/usr/local/bin/aiku-server-metrics --dry-run
```

```bash
systemctl daemon-reload && systemctl enable --now aiku-server-metrics
```

The box appears on the DevOps dashboard as `erbio` on its first reading.

How it runs, and why systemd and not Docker Compose: LiveKit is one static Go binary, so a unit
file pins it, starts it on boot (`WantedBy=multi-user.target`) and restarts it on failure
(`Restart=always`) with nothing else to update. LiveKit's own Docker guide needs host networking
anyway, a custom Caddy build for the SNI split, and Docker's port publishing bypasses ufw. Logs
go to journald, which rotates by size on its own (default cap 10% of the disk); HAProxy logs to
`/var/log/haproxy.log`, rotated by the package's logrotate file.

## 6. aiku `.env` on boro and litio

The names `config/services.php` reads for `services.livekit` (url, api_key, api_secret), as in
`.env.example`. Left empty, aiku shows no call button:

```
LIVEKIT_URL=wss://CALLS_DOMAIN
LIVEKIT_API_KEY=<key from /etc/livekit/keys.yaml>
LIVEKIT_API_SECRET=<secret from /etc/livekit/keys.yaml>
```

Same values on boro and litio. The secret must be at least 32 bytes (step 8). The production
`.env` is in the nightly encrypted secrets backup, which makes it the durable copy of the key pair
for a rebuild (step 8). Rotating the secret means changing `keys.yaml`, `systemctl restart
livekit` (ends running calls) and both `.env` files.

**Webhook.** LiveKit posts room events to `https://aiku.io/webhooks/livekit` (route
`webhooks.livekit`, `ReceiveLiveKitWebhook`), signed with the same key pair: aiku checks the JWT
in the `Authorization` header against `LIVEKIT_API_SECRET` and the body's SHA-256, and answers
404 while calls are not configured. It uses `room_finished` to end a call and
`participant_joined` to mark someone present. The `webhook:` block in `livekit.yaml` names the
API key (step 9 fills it in from `keys.yaml`); the secret is not repeated there.

**Local development** uses `livekit-server --dev`, whose built-in pair is `devkey: secret`. That
secret is too short for aiku (32 bytes minimum), so start it with a long key, for example with
Docker, taking the secret from the local `.env` (`LIVEKIT_API_KEY=devkey`,
`LIVEKIT_API_SECRET` of 32+ characters, `LIVEKIT_URL=ws://localhost:7880`):

```bash
docker run -d --name aiku-livekit --rm -p 7880:7880 -p 7881:7881 -p 7882:7882/udp -e "LIVEKIT_KEYS=devkey: $(grep '^LIVEKIT_API_SECRET=' .env | cut -d= -f2)" livekit/livekit-server:v1.13.11 --dev --bind 0.0.0.0 --node-ip 127.0.0.1
```

## 7. Verification

Commands marked "from the Mac" run on your Mac; in them **replace** `CALLS_DOMAIN` and
`TURN_DOMAIN` with the real names. Install the LiveKit CLI on your Mac once:
`brew install livekit-cli`. In the Mac shell set the three values (start the line with a space
so the secret stays out of shell history):

```bash
 export LIVEKIT_URL=wss://CALLS_DOMAIN LIVEKIT_API_KEY=REPLACE_KEY LIVEKIT_API_SECRET=REPLACE_SECRET
```

1. **Health.** On the box, then from the Mac, both must print `OK` (LiveKit answers `/` with 200
   `OK`, or 406 `Not Ready` while it is starting):

   ```bash
   curl -s http://127.0.0.1:7880/
   ```

   ```bash
   curl -s https://CALLS_DOMAIN/
   ```

2. **Ports on the box.** Expect HAProxy on 443/tcp; livekit-server on 7880, 7881, 5349 and 6789
   tcp, and 7882 and 443 udp:

   ```bash
   ss -lntup | grep -E 'haproxy|livekit'
   ```

   From the Mac, 7880 and 5349 must be closed and 7881 open:

   ```bash
   nc -vz -G 3 CALLS_DOMAIN 7880; nc -vz -G 3 CALLS_DOMAIN 5349; nc -vz -G 3 CALLS_DOMAIN 7881
   ```

3. **Certificate for the TURN name**:

   ```bash
   openssl s_client -connect TURN_DOMAIN:443 -servername TURN_DOMAIN </dev/null 2>/dev/null | openssl x509 -noout -subject -ext subjectAltName -enddate
   ```

4. **LiveKit's connection test.** Make a token, then open https://livekit.io/connection-test,
   paste `wss://CALLS_DOMAIN` and the token, Run test. Every check must pass (the page now
   lives at livekit.com/connection-test; which transports it tries was not documented, so step 5
   is the real TURN test):

   ```bash
   lk token create --join --room ini-444-test --identity raul-mac --valid-for 1h
   ```

5. **Two browsers on different networks, one forced through TURN** (INI-444's done-when).
   Make a second token with `--identity` changed. Browser A on the office network: open
   https://meet.livekit.io, "Custom" tab, server URL and token, join with camera. Device B on a
   phone hotspot: Firefox, `about:config`, set `media.peerconnection.ice.relay_only` to `true`,
   then join the same room the same way (this Firefox pref is the usual way to force relay; it
   was not checked against Mozilla's documentation; if it is missing, block UDP on B's network or
   use Chrome on a network that only allows 443). Both see and hear each other. In B's `about:webrtc` the
   selected candidate pair is of type `relay` (TURN). Set the pref back to `false` afterwards.

6. **UDP is really used, not only TCP.** In browser A open `chrome://webrtc-internals`, the
   succeeded candidate pair shows protocol `udp` and remote port 7882. On the box, while the call
   runs, packets from the caller's address must show:

   ```bash
   timeout 15 tcpdump -ni any -c 20 udp port 7882
   ```

   And nothing should be on the TCP fallback unless someone is on a UDP-blocking network:

   ```bash
   timeout 15 tcpdump -ni any -c 20 tcp port 7881
   ```

7. **Not an open relay.** Every way in needs a token signed with aiku's secret.
   - The room API without a token must be refused (expected 401; any 4xx is fine, 200 is not):

     ```bash
     curl -s -o /dev/null -w '%{http_code}\n' -X POST -H 'Content-Type: application/json' -d '{}' https://CALLS_DOMAIN/twirp/livekit.RoomService/ListRooms
     ```

   - A token signed with a wrong secret must fail in the connection test (step 4):

     ```bash
     lk token create --api-secret 0000000000000000000000000000000000000000 --join --room ini-444-test --identity stranger --valid-for 5m
     ```

   - TURN with made-up credentials must be refused. Open
     https://webrtc.github.io/samples/src/content/peerconnection/trickle-ice/ , remove the
     default server, add `turn:SERVER_IPV4:443?transport=udp` with username `stranger` and
     password `stranger`, set ICE transports to "relay", Gather candidates: no candidate of type
     `relay` may appear (the page reports an authentication failure for TURN/UDP).

   How TURN credentials work (LiveKit v1.13.11 source, `pkg/service/turn.go`): there is no static
   TURN user. The username carries the API key, a participant id and an expiry; the password is a
   SHA-256 derived from the API secret. Only someone who joined with an aiku-signed token is given
   one, an allocation must start within 300 seconds of issue, and anything else is refused.

8. **Rooms and participants now** (before an upgrade or reboot):

   ```bash
   lk room list
   ```

**When someone cannot connect**

- `curl -s https://CALLS_DOMAIN/` does not say OK: `systemctl status livekit haproxy`,
  `journalctl -u livekit -n 100 --no-pager`, `tail -50 /var/log/haproxy.log`.
- Certificate error in the browser: `certbot certificates`, `ls -l /etc/haproxy/certs/`; rerun
  the hook from step 10.
- "Could not establish PC connection" or stuck on connecting: the person's network blocks UDP and
  TCP 7881 and TURN did not work. Ask them for the connection test (step 4) result; check UDP 443
  and TCP 443 are open in both firewalls (`ufw status`, Hetzner console) and that `TURN_DOMAIN`
  resolves to the box, DNS only.
- Works for some, not others: `ss -s`, CPU and network on the DevOps dashboard, `lk room list`
  for unexpected rooms, and `journalctl -u livekit --since '-1h' | grep -i -E 'error|warn'`.
- Token rejected ("unauthorized", 401): the app's `LIVEKIT_API_KEY` / `LIVEKIT_API_SECRET` differ
  from `/etc/livekit/keys.yaml`, or the server clocks differ (`timedatectl` on both sides).

## 8. Upgrades

Pick the new release on https://github.com/livekit/livekit/releases, read its notes for config
changes, and take its sha256 from `checksums.txt` there. Do it when `lk room list` is empty
(the restart ends running calls). Change `1.13.11` and the sha256 in step 6 and in this README,
run step 6 again, then:

```bash
systemctl restart livekit && journalctl -u livekit -n 40 --no-pager
```

Rollback: the same with the previous version and sha256.

## Rebuild from nothing (box lost or broken)

Nothing on the box needs saving: rooms live in memory, the key pair is in aiku's production
`.env`, the configuration is this folder.

1. Hetzner: create a new server exactly as in section 3 (the `erbio` firewall still exists).
2. Cloudflare: point both A records of section 4 at the new IPv4, still DNS only.
3. Run section 5, taking the *Rebuild* branch of step 8 (the key pair from the `.env`), so boro
   and litio need no change.
4. Run section 7, then delete the old server in the Hetzner console.

## 9. Monitoring

Nothing new is added; it fits what aiku already has:

- **CPU, memory and bandwidth**: the DevOps dashboard sampler (step 13) as box `erbio`. Watch
  outgoing Mbit/s during calls and CPU. Act when CPU stays above 60% during calls (rescale to
  CX43), when `vmstat` shows CPU steal with jitter in calls (rescale to CCX13), or monthly
  outgoing traffic heads for 20 TB (Hetzner
  console > server > Graphs/Traffic).
- **Participants and rooms**: `lk room list` from the Mac, or on the box
  `curl -s http://127.0.0.1:6789/metrics | grep -i -E 'participant|room'` (metric names are not
  documented by LiveKit; grep rather than rely on one).
- **Down alert**: `MonitorWebsitesUptime` only checks shop websites, so it does not cover this
  box. Use the Sentry uptime monitoring aiku already has (check the plan includes another
  uptime monitor): an uptime monitor on
  `https://CALLS_DOMAIN/` (expects 200 with body `OK`), alerting the usual way. Adding the URL to
  aiku's own Discord uptime check instead is an application change (a follow-up ticket).
- **Disk**: `devops/disk-alert.sh` needs the app's `.env` for its Discord webhook, which this
  box does not have; with LiveKit writing only to journald the 80 GB disk does not fill.

## 10. Costs per month (excl. VAT, Hetzner prices read on 21 Nov 2026)

| Item | EUR |
| --- | --- |
| CX33, NBG1, primary IPv4 included in the shown price | 8.99 |
| Hetzner Cloud firewall | 0 |
| Backups | 0 (off; 20% of the server price if switched on) |
| Traffic up to 20 TB outgoing (incoming is free) | 0 |
| Let's Encrypt, LiveKit licence | 0 |
| **Total** | **about 9** |

What would raise it: rescaling to CX43 (16.49) or, if shared CPUs prove too jittery, CCX13
(43.49); outgoing traffic above 20 TB (billed
per TB beyond the allowance); a Singapore server for Indonesia-only calls (CPX22 26.99 with
1 TB, and dearer extra traffic); recording, which would need LiveKit's Egress service on a
CPU-heavy box (out of scope). Hetzner's overview page also mentions EUR 0.50/month for a primary
IPv4 while the product page says "price incl. IPv4": check the console's price at checkout.

## Sources

Read on 21 Nov 2026:

- LiveKit deployment: https://docs.livekit.io/home/self-hosting/deployment/
- LiveKit ports and firewall: https://docs.livekit.io/home/self-hosting/ports-firewall/
- LiveKit VM guide (Caddy, Docker Compose): https://docs.livekit.io/home/self-hosting/vm/
- LiveKit distributed (a room fits on one node): https://docs.livekit.io/home/self-hosting/distributed/
- LiveKit benchmark: https://docs.livekit.io/home/self-hosting/benchmark/
- LiveKit CLI (`lk token create`, `lk room list`): https://docs.livekit.io/reference/developer-tools/livekit-cli/rooms.md
- Config reference for v1.13.11: https://github.com/livekit/livekit/blob/v1.13.11/config-sample.yaml
- Source, v1.13.11: `pkg/service/roommanager.go` (TURN/TLS advertised as `turns:<domain>:443`),
  `pkg/service/turn.go` (external_tls, certificate loaded once), `pkg/service/server.go`
  (health check), `cmd/server/main.go` and `pkg/config/config.go` (key file checks)
- LiveKit's generator (Caddy layer4 SNI split, external_tls): https://github.com/livekit/deploy/tree/main/generate
- Release and checksums: https://github.com/livekit/livekit/releases/tag/v1.13.11
- Hetzner Cloud prices: https://www.hetzner.com/cloud/cost-optimized/, https://www.hetzner.com/cloud/general-purpose/
- Hetzner traffic billing: https://docs.hetzner.com/cloud/billing/faq/, https://docs.hetzner.com/cloud/servers/overview/
- Hetzner shared vs dedicated vCPU, rescale limits: https://docs.hetzner.com/cloud/servers/faq/
- Hetzner firewalls: https://docs.hetzner.com/cloud/firewalls/overview/
