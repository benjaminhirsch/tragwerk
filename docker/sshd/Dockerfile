# trixie (git >= 2.47) is required for the `safe.directory` path glob below;
# bookworm ships git 2.39, which only understands exact paths or a bare `*`.
FROM debian:trixie-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
        openssh-server \
        git \
        curl \
        passwd \
    && rm -rf /var/lib/apt/lists/*

# Login shell is /bin/sh so the forced-command wrapper can run; interactive
# access stays impossible (every key carries a forced command, AllowUsers git).
# git-shell remains installed — the wrapper delegates to it.
RUN useradd -m -s /bin/sh git \
    && passwd -d git \
    && mkdir -p /home/git/.ssh \
    && chmod 700 /home/git/.ssh \
    && mkdir -p /run/sshd

# Safety net for repositories the entrypoint could not reclaim (e.g. a read-only
# volume): git would otherwise refuse them outright.
RUN git config --system --add safe.directory '/home/git/repos/*'

COPY sshd_config /etc/ssh/sshd_config
COPY git-auth-wrapper /usr/local/bin/git-auth-wrapper
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod 755 /usr/local/bin/git-auth-wrapper /usr/local/bin/entrypoint.sh

RUN ssh-keygen -A

EXPOSE 22

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/sbin/sshd", "-D", "-e"]
