pg_restore --no-owner --no-acl --clean --if-exists -j 15 -U ${USER} -c -d aiku  "devops/devel/snapshots/$1.dump"
echo "$1"
