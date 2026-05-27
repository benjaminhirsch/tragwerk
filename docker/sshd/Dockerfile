FROM debian:bookworm-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
        openssh-server \
        git \
    && rm -rf /var/lib/apt/lists/*

RUN useradd -m -s /usr/bin/git-shell git \
    && passwd -d git \
    && mkdir -p /home/git/.ssh \
    && chmod 700 /home/git/.ssh \
    && mkdir -p /run/sshd

COPY sshd_config /etc/ssh/sshd_config

RUN ssh-keygen -A

EXPOSE 22

CMD ["/usr/sbin/sshd", "-D", "-e"]
