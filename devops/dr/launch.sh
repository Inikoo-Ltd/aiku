#!/usr/bin/env bash
#
# Start aiku's disaster-recovery servers at AWS Frankfurt (INI-133). Runs anywhere with bash
# and the AWS CLI: an engineer's Mac or Linux box with the `aiku-dr` profile (IAM user
# aiku-dr-operator: EC2 in eu-central-1 only, no S3), or AWS CloudShell in the console, which
# uses the console login and needs no key at all. Without either, start the server by hand in the EC2 console from the newest aiku-dr image.
#
#     launch.sh build              # small plain Ubuntu server to build the image on (README "Refresh the image")
#     launch.sh image <instance>   # stop it, save the AMI aiku-dr-YYYY-MM-DD, terminate it (README "Refresh the image")
#     launch.sh dr                 # the DR server from the newest aiku-dr AMI, with the fast data disk (README step 1)
#     launch.sh allow              # this machine's current address may reach ssh/80/443 again
#     launch.sh allow-cloudflare   # Cloudflare may reach 80/443: needed once DNS points at the box
#     launch.sh terminate <instance>
#
# ssh, 80 and 443 are open only to the address this runs from (security group aiku-dr).
#
set -euo pipefail
export AWS_DEFAULT_REGION=eu-central-1
if [[ -z ${AWS_PROFILE:-} ]] && aws configure list-profiles 2>/dev/null | grep -qx aiku-dr; then
  export AWS_PROFILE=aiku-dr
fi

DR_TYPE=${DR_TYPE:-r7i.8xlarge}    # 32 vCPU, 256 GB: restore-db.sh sets shared_buffers=32GB
BUILD_TYPE=${BUILD_TYPE:-t3.large}
SSH_KEY=${SSH_KEY:-$HOME/.ssh/id_rsa.pub}
if [[ ! -f $SSH_KEY ]]; then
  SSH_KEY=$HOME/.ssh/id_ed25519.pub
  install -d -m 700 "$HOME/.ssh"
  [[ -f $SSH_KEY ]] || ssh-keygen -q -t ed25519 -N '' -f "${SSH_KEY%.pub}"
fi
# one key pair per machine's key, so whoever launches can log in
KEY_NAME=aiku-dr-$(awk '{print $2}' "$SSH_KEY" | openssl dgst -sha256 | awk '{print $NF}' | cut -c1-8)

security_group() {
  local sg
  sg=$(aws ec2 describe-security-groups --filters Name=group-name,Values=aiku-dr --query 'SecurityGroups[0].GroupId' --output text)
  if [[ $sg == None ]]; then
    sg=$(aws ec2 create-security-group --group-name aiku-dr --description 'aiku DR (INI-133): ssh/http only from the operator' --query GroupId --output text)
  fi
  echo "$sg"
}

# opening an already open port fails with "Duplicate": that is fine; anything else is not
open_port() {
  local err
  err=$(aws ec2 authorize-security-group-ingress --group-id "$1" --ip-permissions "$2" 2>&1 >/dev/null) && return 0
  [[ $err == *InvalidPermission.Duplicate* ]] && return 0
  echo "FAIL: could not open $2: $err" >&2
  exit 1
}

allow_me() {
  local me
  me=$(curl -fsS https://checkip.amazonaws.com)/32
  for port in 22 80 443; do
    open_port "$1" "IpProtocol=tcp,FromPort=$port,ToPort=$port,IpRanges=[{CidrIp=$me}]"
  done
  echo "OK: $me may reach the DR server" >&2
}

ensure_access() {
  local sg
  sg=$(security_group)
  allow_me "$sg"
  if ! aws ec2 describe-key-pairs --key-names "$KEY_NAME" >/dev/null 2>&1; then
    aws ec2 import-key-pair --key-name "$KEY_NAME" --public-key-material "fileb://$SSH_KEY" >/dev/null
  fi
  echo "$sg"
}

run() {
  local id ip
  id=$(aws ec2 run-instances "$@" --key-name "$KEY_NAME" --query 'Instances[0].InstanceId' --output text)
  aws ec2 wait instance-running --instance-ids "$id"
  ip=$(aws ec2 describe-instances --instance-ids "$id" --query 'Reservations[0].Instances[0].PublicIpAddress' --output text)
  echo "OK: server $id is up at $ip. Next: ssh ubuntu@$ip"
}

case ${1:-} in
  build)
    sg=$(ensure_access)
    ami=$(aws ssm get-parameter --name /aws/service/canonical/ubuntu/server/24.04/stable/current/amd64/hvm/ebs-gp3/ami-id --query Parameter.Value --output text)
    run --image-id "$ami" --instance-type "$BUILD_TYPE" --security-group-ids "$sg" \
      --block-device-mappings 'DeviceName=/dev/sda1,Ebs={VolumeSize=30,VolumeType=gp3}' \
      --tag-specifications 'ResourceType=instance,Tags=[{Key=Name,Value=aiku-dr-build}]'
    ;;
  image)
    id=${2:?instance id}
    name=aiku-dr-$(date -u +%Y-%m-%d-%H%M)
    aws ec2 stop-instances --instance-ids "$id" >/dev/null
    aws ec2 wait instance-stopped --instance-ids "$id"
    ami=$(aws ec2 create-image --instance-id "$id" --name "$name" --description 'aiku DR image, packages only (INI-133, devops/dr/build-image.sh)' --query ImageId --output text)
    aws ec2 wait image-available --image-ids "$ami"
    aws ec2 terminate-instances --instance-ids "$id" >/dev/null
    echo "$ami $name ready; build instance terminated. Deregister older aiku-dr images and their snapshots by hand."
    ;;
  dr)
    sg=$(ensure_access)
    ami=$(aws ec2 describe-images --owners self --filters 'Name=name,Values=aiku-dr-*' --query 'sort_by(Images,&CreationDate)[-1].ImageId' --output text)
    [[ $ami != None ]] || { echo "no aiku-dr AMI: run 'launch.sh build' first"; exit 1; }
    # root 300 GB: the media copy (194 GB in Oct 2026) lands in shared/storage/media.
    # /dev/sdf: the database, 1 TB at gp3's top speed; it dies with the instance.
    run --image-id "$ami" --instance-type "$DR_TYPE" --security-group-ids "$sg" \
      --iam-instance-profile Name=aiku-dr-restore \
      --block-device-mappings 'DeviceName=/dev/sda1,Ebs={VolumeSize=300,VolumeType=gp3}' \
        'DeviceName=/dev/sdf,Ebs={VolumeSize=1000,VolumeType=gp3,Iops=16000,Throughput=1000,DeleteOnTermination=true}' \
      --tag-specifications 'ResourceType=instance,Tags=[{Key=Name,Value=aiku-dr}]'
    ;;
  allow)
    allow_me "$(security_group)"
    ;;
  allow-cloudflare)
    sg=$(security_group)
    # devops/haproxy/CF_ips.lst is the list HAProxy already trusts
    while read -r cidr; do
      [[ -z $cidr ]] && continue
      for port in 80 443; do
        if [[ $cidr == *:* ]]; then
          open_port "$sg" "IpProtocol=tcp,FromPort=$port,ToPort=$port,Ipv6Ranges=[{CidrIpv6=$cidr}]"
        else
          open_port "$sg" "IpProtocol=tcp,FromPort=$port,ToPort=$port,IpRanges=[{CidrIp=$cidr}]"
        fi
      done
    done < "$(dirname "$0")/../haproxy/CF_ips.lst"
    echo "OK: Cloudflare may reach ports 80 and 443"
    ;;
  terminate)
    aws ec2 terminate-instances --instance-ids "${2:?instance id}" --query 'TerminatingInstances[0].CurrentState.Name' --output text
    ;;
  *) sed -n '3,15p' "$0"; exit 1 ;;
esac
