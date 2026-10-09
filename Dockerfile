FROM grafana/alloy:v1.20.1

# The official image runs as root, but ships an unprivileged alloy user (uid 473) - use it.
# The Docker socket it reads is reached through group_add in docker-compose.yml.
USER alloy
