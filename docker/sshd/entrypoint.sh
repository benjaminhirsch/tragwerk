#!/bin/sh
#
# Runs as root before sshd starts.
#
# The repositories volume is written from two sides: the Tragwerk app container
# creates the bare repos (as root in production, since the app image declares no
# USER), while git-receive-pack here runs as the unprivileged `git` user. Git
# refuses repositories owned by anyone else ("detected dubious ownership") and
# could not write into them anyway, so the ownership has to be reconciled.
#
# New repositories are chowned by the app itself; this pass repairs the ones a
# previous Tragwerk version left behind as root.
set -eu

# GIT_UID/GIT_GID keep the development setup working, where the app container
# runs as the host user instead of uid 1000.
if [ -n "${GIT_GID:-}" ] && [ "$GIT_GID" != "$(id -g git)" ]; then
    groupmod -g "$GIT_GID" git
fi

if [ -n "${GIT_UID:-}" ] && [ "$GIT_UID" != "$(id -u git)" ]; then
    usermod -u "$GIT_UID" git
fi

if [ -d /home/git/repos ]; then
    find /home/git/repos -mindepth 1 -maxdepth 1 ! -user git \
        -exec chown -R git:git {} + 2>/dev/null || true
fi

exec "$@"
