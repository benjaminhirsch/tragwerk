FROM debian:bookworm-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
        openssh-server \
        git \
        curl \
    && rm -rf /var/lib/apt/lists/*

# Login shell is /bin/sh so the forced-command wrapper can run; interactive
# access stays impossible (every key carries a forced command, AllowUsers git).
# git-shell remains installed — the wrapper delegates to it.
RUN useradd -m -s /bin/sh git \
    && passwd -d git \
    && mkdir -p /home/git/.ssh \
    && chmod 700 /home/git/.ssh \
    && mkdir -p /run/sshd

COPY sshd_config /etc/ssh/sshd_config
COPY git-auth-wrapper /usr/local/bin/git-auth-wrapper
RUN chmod 755 /usr/local/bin/git-auth-wrapper

RUN ssh-keygen -A

EXPOSE 22

CMD ["/usr/sbin/sshd", "-D", "-e"]
