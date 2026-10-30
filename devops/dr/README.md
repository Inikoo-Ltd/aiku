# Bring aiku back on AWS when the Hetzner servers are lost

This is the runbook for the disaster-recovery document's scenarios 4 and 5: boro, litio and
helio are gone and aiku has to run somewhere else today. It is written so that a person who is
not an engineer can do it with the help of an AI chat (Claude, ChatGPT) or an engineer.
Rehearsed on 30 Oct 2026 (INI-133): about 20 minutes of machine time plus the person's steps.

## For the AI helping

You are guiding a person who is probably not technical, during a real emergency, very
likely alone: the person who always fixed these things is not there, every website is down
and people are calling them. Be calm, kind and plain-spoken. Tell them the data is safe (it
is: section "What it uses"), say after each step how far along they are, and never make
them feel a mistake was theirs: every step can be run again.

- Everything happens in two browser tabs: **AWS CloudShell** (a terminal inside the AWS
  console, already logged in) and the **Cloudflare dashboard**. Nothing is installed on the
  person's computer.
- Give **one command at a time**. Each script ends with lines starting `OK:` or `FAIL:`. Ask
  the person to paste back the last lines, and only go on after `OK:`. Every step is safe to
  run again.
- Steps marked **(person)** need a login, a password, a payment or a decision: tell the person
  exactly what to click or type and wait. Never ask them to paste a password, key or
  passphrase into the chat.
- If this file and the PDF disagree, this file wins (it is newer). If something fails twice,
  stop and suggest hiring a Laravel, PostgreSQL and Linux engineer, with this file.

## What it uses

| What | Where | Who can open it |
| --- | --- | --- |
| Database backup (continuous) | AWS S3 `aiku-pgbackrest`, Frankfurt `eu-central-1` | read by the server's role `aiku-dr-restore`, no key needed |
| Server image with everything installed | AWS AMI `aiku-dr-YYYY-MM-DD`, Frankfurt | the AWS login |
| Code | github.com/Inikoo-Ltd/aiku, branch `production` (public) | anyone |
| Settings and keys (`.env`, OAuth keys, `shared/private`), encrypted | AWS S3 `aiku-pgbackrest/aiku-secrets`; second copy Cloudflare R2 `aiku-db-backups` | read by the server's role + the private key and passphrase in the PDF |
| Product images (204 GB) | AWS S3 `aiku-pgbackrest/aiku-media`; second copy Cloudflare R2 `aiku-media-backup` | read by the server's role |
| Web addresses | Cloudflare DNS, 49 zones in two accounts | the Cloudflare login |

## 1. Start the server (5 minutes)

1. **(person)** Log in to the AWS console, account 562305369535. Top right: choose region
   **Europe (Frankfurt)**. Open **CloudShell** (the `>_` icon in the top bar).
2. In CloudShell:

       git clone --depth 1 -b production https://github.com/Inikoo-Ltd/aiku.git
       aiku/devops/dr/launch.sh dr

   It ends with `OK: server i-... is up at IP. Next: ssh ubuntu@IP`. Write the IP down. If it
   says there is no aiku-dr image, build one first (section "Refresh the image") - about 25
   minutes more.
3. `ssh ubuntu@IP` (answer `yes` the first time). Every command below runs on the server,
   inside this ssh session, unless it says CloudShell.

## 2. The code

    sudo git clone --depth 1 -b production https://github.com/Inikoo-Ltd/aiku.git /home/aiku/aiku/releases/1

## 3. The settings and keys

1. **(person)** Save the private key from the PDF (attached file `aiku-backup.private.asc`, or
   the printed block on the last pages, copied exactly) as `aiku-backup.private.asc`.
2. **(person)** In CloudShell: Actions > Upload file, that file. Then in CloudShell:

       scp aiku-backup.private.asc ubuntu@IP:
       rm aiku-backup.private.asc

3. On the server; **(person)** types the passphrase from the PDF when asked:

       sudo /home/aiku/aiku/releases/1/devops/dr/install-secrets.sh aiku-backup.private.asc
       rm aiku-backup.private.asc

   It fetches the newest encrypted settings from AWS S3 by itself. If it says there is none
   there, **(person)** downloads the newest `aiku-secrets-YYYY-MM-DD.tar.gpg` from Cloudflare >
   R2 > `aiku-db-backups` (EU list), uploads and copies it like the key, and adds its name at
   the end of the command.

## 4. Build the code (4 minutes)

    sudo /home/aiku/aiku/releases/1/devops/dr/deploy-code.sh 1

## 5. The database (10 minutes)

    sudo /home/aiku/aiku/current/devops/dr/restore-db.sh

It prepares the empty data disk, downloads the backup from S3 and replays every change up to
the last minute before the servers were lost. **Do not stop it** while it waits: it ends with
`OK: database restored`. To bring the data back as it was at a given moment instead (for
example just before damage), add the time in UTC: `restore-db.sh "2026-10-30 10:15:00+00"`.

## 6. Start aiku (2 minutes)

    sudo /home/aiku/aiku/current/devops/dr/start-app.sh

It ends with `OK: aiku is running on this server` after a list of OK lines: database, staff
app, a shop website, images, live updates, page rendering, background jobs. Search keeps
rebuilding in the background for a while.

## 7. Open it to the world

1. In CloudShell: `aiku/devops/dr/launch.sh allow-cloudflare`
2. **(person)** Cloudflare dashboard > My Profile > API Tokens > Create Token > template
   "Edit zone DNS" > Zone resources: "All zones from an account" for each account listed >
   Create. In CloudShell type `export CF_API_TOKEN=` and paste the token right after the `=`.
3. In CloudShell, preview, then **(person)** decides, then apply:

       aiku/devops/dr/dns-switch.sh IP
       aiku/devops/dr/dns-switch.sh IP --apply

   Keep the `dns-switch-*.log` file it writes. Sites answer from the new server within
   minutes.
4. Images, on the server:

       sudo /home/aiku/aiku/current/devops/dr/fetch-media.sh

   It takes a while (204 GB); aiku works meanwhile and images appear as they arrive. If the
   AWS copy is missing, the script's first lines say how to fetch from Cloudflare R2 instead.

## Waiting on the old servers

Steps 1 to 5 never touch the old servers, so they can run while it is still unclear whether
Hetzner comes back. Stop after step 5. To go live later: step 5 again (it restores everything up to the last minute,
about 8 minutes), then 6 and 7. A server started with `start-app.sh --rehearsal`
can never go live: its copy has the payment keys wiped. After step 7, never point the web
addresses back to the old servers without an engineer: orders taken in between would be lost.

## 8. After it runs

- Mail, payments and marketplaces work as before: the server **is** production now.
- Backups: the database is no longer copied anywhere. Within days an engineer sets up
  pgBackRest to a new path (`repo1-path=/aiku-ec2`) and plans the move to a new dedicated
  server (streaming replica, then switch), which is `dns-switch.sh NEW_IP --from IP`.
- If ssh from CloudShell stops working, CloudShell's address changed: `launch.sh allow`.

## Rehearsal (every three months)

Steps 1-6 with `start-app.sh --rehearsal`, which first cuts the server off from the internet
(only AWS S3 stays reachable), wipes payment keys and tokens from the copy, sends mail to the
log and runs no scheduler. **Never run step 7 in a rehearsal.** To look at it in a browser, on
a Mac:

    open -na "Google Chrome" --args --user-data-dir=/tmp/aiku-dr-chrome --ignore-certificate-errors --host-resolver-rules="MAP app.aiku.io IP, MAP media.aiku.io IP, MAP soketi.aiku.io IP"

When done, in CloudShell: `aiku/devops/dr/launch.sh terminate i-...`

## Refresh the image

When packages change, or if there is no image: in CloudShell

    aiku/devops/dr/launch.sh build
    scp -r aiku/devops ubuntu@BUILD_IP:
    ssh ubuntu@BUILD_IP sudo ./devops/dr/build-image.sh
    aiku/devops/dr/launch.sh image i-BUILD_INSTANCE

Then deregister the older `aiku-dr-*` image and delete its snapshot (EC2 > AMIs, Snapshots).

## Times

| Step | 30 Oct 2026 |
| --- | --- |
| Build the image | 12 min, save it 7 min |
| Server up from the image | 43 s |
| Code build | 4 min |
| Database from S3 (323 GB) | 8 min 20 s, data up to 2.5 min before ready |
| Start and check | 2 min |
| Images (204 GB) | not measured yet |
