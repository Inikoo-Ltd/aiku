pg_restore  -U raul -c -d pika_test < "$1"
echo "$1"
