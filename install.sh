#!/usr/bin/env bash
#
# Tragwerk installer — sets up a Tragwerk control plane on a fresh Linux server.
#
#   curl -fsSL https://raw.githubusercontent.com/benjaminhirsch/tragwerk/main/install.sh | sudo bash
#
# It automates https://docs.tragwerk.app/install/docker-compose: installs Docker
# Engine + the Compose plugin from Docker's official package repository, clones
# this repository, generates .env with fresh secrets, checks DNS and ports,
# opens the firewall, starts the stack and creates the first user.
#
# Supported: every distribution Docker Engine itself supports
# (https://docs.docker.com/engine/install/) — Ubuntu, Debian, Raspberry Pi OS,
# Fedora, RHEL, CentOS, Rocky, AlmaLinux — plus derivatives via ID_LIKE.
#
# Run with --help for all flags, or --check-only for a read-only preflight.

set -Eeuo pipefail

readonly INSTALLER_VERSION='1.0.0'
readonly REPO_URL='https://github.com/benjaminhirsch/tragwerk.git'
readonly COMPOSE_FILE='docker-compose.prod.yaml'
readonly ENV_TEMPLATE='.env.prod.dist'
readonly ENV_FILE='.env'
readonly DOCKER_PACKAGES='docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin'
readonly DOCKER_DOCS='https://docs.docker.com/engine/install/'

# Distributions with a dedicated Docker dnf repository. Rocky and AlmaLinux are
# binary compatible with RHEL and use the CentOS repository (same mapping as
# app/src/Tragwerk/Domain/Docker/DockerInstallScript.php).
readonly DNF_DISTROS='rhel fedora centos rocky almalinux'
readonly APT_DISTROS='ubuntu debian raspbian'

# --- configuration, all overridable by flag or environment ------------------
APP_HOST="${APP_HOST:-}"
ACME_EMAIL="${ACME_EMAIL:-}"
DOCS_HOST="${DOCS_HOST:-}"
SSH_PORT="${SSH_PORT:-2222}"
INSTALL_DIR="${TRAGWERK_INSTALL_DIR:-/opt/tragwerk}"
REGISTRATION_ENABLED="${TRAGWERK_REGISTRATION_ENABLED:-true}"
ADMIN_EMAIL="${TRAGWERK_ADMIN_EMAIL:-}"
ADMIN_FIRSTNAME="${TRAGWERK_ADMIN_FIRSTNAME:-}"
ADMIN_LASTNAME="${TRAGWERK_ADMIN_LASTNAME:-}"
ASSUME_YES="${TRAGWERK_ASSUME_YES:-0}"
SKIP_DOCKER=0
SKIP_FIREWALL=0
SKIP_DNS_CHECK=0
CHECK_ONLY=0

# --- runtime state ----------------------------------------------------------
SUDO=''            # 'sudo' when we are not root but may escalate
DISTRO_ID=''       # $ID from /etc/os-release
DISTRO_LIKE=''     # $ID_LIKE from /etc/os-release
DISTRO_CODENAME='' # $VERSION_CODENAME, needed for the apt repository line
PKG_FAMILY=''      # apt|dnf
REPO_DISTRO=''     # distribution whose Docker repository we use
HAS_TTY=0
LOG_FILE=''
ENV_EXISTED=0
PROJECT_DIR=''
COLOR_RESET='' COLOR_RED='' COLOR_YELLOW='' COLOR_GREEN='' COLOR_BLUE='' COLOR_BOLD=''

# ---------------------------------------------------------------------------
# Output helpers
# ---------------------------------------------------------------------------

setup_colors() {
    if [[ -t 1 && "${NO_COLOR:-}" == '' ]]; then
        COLOR_RESET=$'\033[0m'
        COLOR_RED=$'\033[31m'
        COLOR_YELLOW=$'\033[33m'
        COLOR_GREEN=$'\033[32m'
        COLOR_BLUE=$'\033[34m'
        COLOR_BOLD=$'\033[1m'
    fi
}

say()  { printf '%s\n' "$*"; }
step() { printf '\n%s==>%s %s%s%s\n' "$COLOR_BLUE" "$COLOR_RESET" "$COLOR_BOLD" "$*" "$COLOR_RESET"; }
info() { printf '    %s\n' "$*"; }
ok()   { printf '    %s✓%s %s\n' "$COLOR_GREEN" "$COLOR_RESET" "$*"; }
# Warnings go to stdout on purpose: they are part of the installer's narrative and
# must keep their order relative to the steps, in the terminal and in the log.
warn() { printf '    %s!%s %s\n' "$COLOR_YELLOW" "$COLOR_RESET" "$*"; }

die() {
    printf '\n%serror:%s %s\n' "$COLOR_RED" "$COLOR_RESET" "$*" >&2
    [[ -n $LOG_FILE ]] && printf 'A full log is at %s\n' "$LOG_FILE" >&2
    exit 1
}

on_error() {
    local line=$1 failed=$2
    # set -E makes subshells inherit this trap; let the parent do the reporting so
    # a failing $(…) does not print the same message twice.
    [[ ${BASHPID:-$$} != "$$" ]] && exit 1
    printf '\n%serror:%s the installer aborted unexpectedly on line %s: %s\n' \
        "$COLOR_RED" "$COLOR_RESET" "$line" "$failed" >&2
    [[ -n $LOG_FILE ]] && printf 'A full log is at %s\n' "$LOG_FILE" >&2
    exit 1
}

# ---------------------------------------------------------------------------
# Privilege + command helpers
# ---------------------------------------------------------------------------

# Runs a command as root: directly when we already are root, through sudo
# otherwise. Every privileged call in this script goes through here so the
# non-root-but-sudoer case needs no separate code path.
run_priv() {
    if [[ -n $SUDO ]]; then
        sudo "$@"
    else
        "$@"
    fi
}

# docker compose for this project, always from the project directory.
dc() {
    run_priv docker compose -f "$COMPOSE_FILE" "$@"
}

have() { command -v "$1" > /dev/null 2>&1; }

# ---------------------------------------------------------------------------
# Prompting — curl-pipe safe
# ---------------------------------------------------------------------------

# With `curl … | bash` stdin is the download, not the keyboard. Prompts
# therefore read from fd 3, which we bind to the controlling terminal.
open_tty() {
    HAS_TTY=0
    [[ -e /dev/tty ]] || return 0
    # The braces matter: `exec` without a command applies its redirections to the
    # shell permanently, so the 2>/dev/null has to belong to the group instead —
    # otherwise a failing open would silence stderr for the rest of the run.
    if { exec 3< /dev/tty; } 2> /dev/null; then
        HAS_TTY=1
    fi
}

# ask <variable-name> <prompt> [default]
ask() {
    local __var=$1 prompt=$2 default=${3:-} answer=''

    if [[ $HAS_TTY -eq 0 ]]; then
        [[ -n $default ]] && { printf -v "$__var" '%s' "$default"; return 0; }
        die "$prompt is required but there is no terminal to ask on. Pass it as a flag (--help lists them)."
    fi

    if [[ -n $default ]]; then
        printf '    %s [%s]: ' "$prompt" "$default"
    else
        printf '    %s: ' "$prompt"
    fi
    IFS= read -r -u 3 answer || answer=''
    [[ -z $answer ]] && answer=$default
    printf -v "$__var" '%s' "$answer"
}

# confirm <question> [default:y|n]
confirm() {
    local question=$1 default=${2:-n} answer='' hint='[y/N]'
    [[ $default == 'y' ]] && hint='[Y/n]'

    [[ $ASSUME_YES -eq 1 ]] && return 0
    [[ $HAS_TTY -eq 0 ]] && { [[ $default == 'y' ]]; return; }

    while true; do
        printf '    %s %s ' "$question" "$hint"
        IFS= read -r -u 3 answer || answer=''
        [[ -z $answer ]] && answer=$default
        case ${answer,,} in
            y | yes) return 0 ;;
            n | no) return 1 ;;
        esac
    done
}

# ---------------------------------------------------------------------------
# Argument parsing
# ---------------------------------------------------------------------------

usage() {
    cat << EOF
Tragwerk installer ${INSTALLER_VERSION}

Sets up a Tragwerk control plane: Docker Engine, this repository, .env with
generated secrets, the production stack and the first user account.

USAGE
    install.sh [options]
    curl -fsSL https://raw.githubusercontent.com/benjaminhirsch/tragwerk/main/install.sh | sudo bash
    curl -fsSL … | sudo bash -s -- --app-host tragwerk.example.com --acme-email you@example.com --yes

OPTIONS
    --app-host HOST         Public hostname of the web UI. DNS must already point here.
    --acme-email EMAIL      Contact address for Let's Encrypt.
    --docs-host HOST        Serve the bundled documentation under this hostname. Optional.
    --ssh-port PORT         Host port for the git push target (default: 2222).
    --dir PATH              Where to clone the repository (default: /opt/tragwerk).
    --no-registration       Disable public sign-up at /register.
    --admin-email EMAIL     E-mail of the first user.
    --admin-firstname NAME  First name of the first user.
    --admin-lastname NAME   Last name of the first user.
    --skip-docker           Never install Docker; fail if it is missing.
    --skip-firewall         Do not touch ufw / firewalld.
    --skip-dns-check        Do not verify that the hostnames resolve to this machine.
    -y, --yes               Never ask; needs --app-host and --acme-email, unless an
                            existing .env already supplies them.
    --check-only            Run the preflight checks and exit without changing anything.
    -h, --help              Show this help.

Every option can also be given as an environment variable: APP_HOST, ACME_EMAIL,
DOCS_HOST, SSH_PORT, TRAGWERK_INSTALL_DIR, TRAGWERK_REGISTRATION_ENABLED,
TRAGWERK_ADMIN_EMAIL, TRAGWERK_ADMIN_FIRSTNAME, TRAGWERK_ADMIN_LASTNAME,
TRAGWERK_ASSUME_YES. Secrets are always generated and are never read from the
environment.

Documentation: https://docs.tragwerk.app/install/script
EOF
}

need_value() {
    [[ $# -ge 2 && -n ${2:-} ]] || die "$1 requires a value."
}

parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --app-host) need_value "$1" "${2:-}"; APP_HOST=$2; shift 2 ;;
            --acme-email) need_value "$1" "${2:-}"; ACME_EMAIL=$2; shift 2 ;;
            --docs-host) need_value "$1" "${2:-}"; DOCS_HOST=$2; shift 2 ;;
            --ssh-port) need_value "$1" "${2:-}"; SSH_PORT=$2; shift 2 ;;
            --dir) need_value "$1" "${2:-}"; INSTALL_DIR=$2; shift 2 ;;
            --admin-email) need_value "$1" "${2:-}"; ADMIN_EMAIL=$2; shift 2 ;;
            --admin-firstname) need_value "$1" "${2:-}"; ADMIN_FIRSTNAME=$2; shift 2 ;;
            --admin-lastname) need_value "$1" "${2:-}"; ADMIN_LASTNAME=$2; shift 2 ;;
            --no-registration) REGISTRATION_ENABLED='false'; shift ;;
            --skip-docker) SKIP_DOCKER=1; shift ;;
            --skip-firewall) SKIP_FIREWALL=1; shift ;;
            --skip-dns-check) SKIP_DNS_CHECK=1; shift ;;
            -y | --yes) ASSUME_YES=1; shift ;;
            --check-only) CHECK_ONLY=1; shift ;;
            -h | --help) usage; exit 0 ;;
            *) usage >&2; die "Unknown option: $1" ;;
        esac
    done
}

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------

start_logging() {
    local candidate
    for candidate in /var/log/tragwerk-install.log "${TMPDIR:-/tmp}/tragwerk-install.log" \
        "$PWD/tragwerk-install.log"; do
        # Append, never truncate: a second run should not destroy the first log.
        if : >> "$candidate" 2> /dev/null; then
            LOG_FILE=$candidate
            break
        fi
    done
    [[ -z $LOG_FILE ]] && return 0

    exec > >(tee -a "$LOG_FILE") 2>&1
    printf 'Tragwerk installer %s — %s\n' "$INSTALLER_VERSION" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" >> "$LOG_FILE"
}

# ---------------------------------------------------------------------------
# Preflight
# ---------------------------------------------------------------------------

check_privileges() {
    if [[ ${EUID:-$(id -u)} -eq 0 ]]; then
        SUDO=''
        ok 'Running as root.'
        return
    fi
    if have sudo && sudo -n true 2> /dev/null; then
        SUDO='sudo'
        ok "Running as $(id -un) with passwordless sudo."
        return
    fi
    die 'This installer needs root. Re-run it with sudo, e.g. curl -fsSL … | sudo bash'
}

detect_distro() {
    [[ -r /etc/os-release ]] || die "Cannot read /etc/os-release, so the distribution is unknown. See $DOCKER_DOCS"

    # shellcheck source=/dev/null
    DISTRO_ID=$(. /etc/os-release && printf '%s' "${ID:-}")
    # shellcheck source=/dev/null
    DISTRO_LIKE=$(. /etc/os-release && printf '%s' "${ID_LIKE:-}")
    # shellcheck source=/dev/null
    DISTRO_CODENAME=$(. /etc/os-release && printf '%s' "${VERSION_CODENAME:-}")
    local pretty
    # shellcheck source=/dev/null
    pretty=$(. /etc/os-release && printf '%s' "${PRETTY_NAME:-$DISTRO_ID}")

    if [[ " $DNF_DISTROS " == *" $DISTRO_ID "* ]]; then
        PKG_FAMILY='dnf'
        REPO_DISTRO=$DISTRO_ID
    elif [[ " $APT_DISTROS " == *" $DISTRO_ID "* ]]; then
        PKG_FAMILY='apt'
        REPO_DISTRO=$DISTRO_ID
    else
        # Derivatives (Linux Mint, Pop!_OS, Oracle Linux, …) are not tested by
        # Docker, but their parent's repository normally works.
        local like
        for like in $DISTRO_LIKE; do
            if [[ " $DNF_DISTROS " == *" $like "* ]]; then
                PKG_FAMILY='dnf'
                REPO_DISTRO=$like
                break
            fi
            if [[ " $APT_DISTROS " == *" $like "* ]]; then
                PKG_FAMILY='apt'
                REPO_DISTRO=$like
                break
            fi
        done
        [[ -z $PKG_FAMILY ]] && die "$pretty is not a distribution Docker supports. See $DOCKER_DOCS"
        warn "$pretty is a derivative Docker does not test. Using the ${REPO_DISTRO} repository."
    fi

    # Rocky and AlmaLinux ship no repository of their own.
    case $REPO_DISTRO in
        rocky | almalinux) REPO_DISTRO='centos' ;;
        raspbian) REPO_DISTRO='debian' ;;
    esac

    ok "Detected $pretty (${PKG_FAMILY} family, Docker repository: ${REPO_DISTRO})."
}

# Prints whoever currently holds the dpkg/apt lock, or returns 1 when it is free.
# `fuser` on the lock file is the accurate answer; pgrep is the fallback for
# images without psmisc.
apt_lock_holder() {
    local pids='' pid args out=''

    if have fuser; then
        pids=$(run_priv fuser /var/lib/dpkg/lock-frontend /var/lib/dpkg/lock /var/lib/apt/lists/lock 2> /dev/null \
            | tr -s ' ' '\n' | grep -E '^[0-9]+$' | sort -u || true)
    fi
    if [[ -z $pids ]] && have pgrep; then
        pids=$(pgrep -x 'apt|apt-get|aptitude|dpkg|unattended-upgr' 2> /dev/null || true)
    fi
    [[ -n $pids ]] || return 1

    for pid in $pids; do
        # Our own apt-get must never count as a competing holder.
        [[ $pid == "$$" ]] && continue
        args=$(ps -o args= -p "$pid" 2> /dev/null | head -n1 | cut -c1-60 || true)
        [[ -n $args ]] && out+="${args} (pid ${pid}); "
    done
    [[ -n $out ]] || return 1
    printf '%s' "${out%; }"
}

# Cloud and freshly booted images run apt-daily / unattended-upgrades and hold the
# dpkg lock, sometimes for a long time on a slow VPS. Report who has it instead of
# failing blind, and do not give up: apt-get waits for the lock itself via
# DPkg::Lock::Timeout (apt 1.9+, i.e. Debian 11 and Ubuntu 20.04 upwards; older
# apt silently ignores the unknown option).
APT_LOCK_WAIT=(-o 'DPkg::Lock::Timeout=900')

wait_for_apt_lock() {
    local waited=0 holder
    while holder=$(apt_lock_holder); do
        [[ $((waited % 30)) -eq 0 ]] && info "Waiting for another package manager: ${holder}"
        sleep 5
        waited=$((waited + 5))
        if [[ $waited -ge 900 ]]; then
            warn "The dpkg lock is still held after 15 minutes by: ${holder}"
            warn 'Handing over to apt-get, which will keep waiting for the lock on its own.'
            return 0
        fi
    done
    [[ $waited -gt 0 ]] && ok 'The dpkg lock is free again.'
    return 0
}

# Installs a package with whichever package manager exists, mirroring the
# fallback chain in SetupServerCommand::ensureCurl().
install_package() {
    local pkg=$1
    if have apt-get; then
        wait_for_apt_lock
        DEBIAN_FRONTEND=noninteractive run_priv apt-get "${APT_LOCK_WAIT[@]}" update -qq || true
        DEBIAN_FRONTEND=noninteractive run_priv apt-get "${APT_LOCK_WAIT[@]}" install -y "$pkg"
    elif have dnf; then
        run_priv dnf install -y "$pkg"
    elif have yum; then
        run_priv yum install -y "$pkg"
    elif have zypper; then
        run_priv zypper --non-interactive install "$pkg"
    elif have apk; then
        run_priv apk add --no-cache "$pkg"
    else
        die "No supported package manager found; please install $pkg manually."
    fi
}

check_base_tools() {
    local missing=()
    have curl || missing+=('curl')
    have git || missing+=('git')
    have openssl || missing+=('openssl')
    have ss || missing+=('iproute2')

    if [[ ${#missing[@]} -eq 0 ]]; then
        ok 'curl, git, openssl and iproute2 are present.'
        return
    fi

    if [[ $CHECK_ONLY -eq 1 ]]; then
        warn "Missing and would be installed: ${missing[*]}"
        return
    fi

    info "Installing missing tools: ${missing[*]}"
    local pkg
    for pkg in "${missing[@]}"; do
        # iproute2 is called iproute on the dnf side.
        [[ $pkg == 'iproute2' && $PKG_FAMILY == 'dnf' ]] && pkg='iproute'
        install_package "$pkg"
    done
    ok 'Base tools installed.'
}

check_resources() {
    local mem_kb mem_mb free_gb
    mem_kb=$(awk '/^MemTotal:/ {print $2}' /proc/meminfo 2> /dev/null || printf '0')
    mem_mb=$((mem_kb / 1024))
    if [[ $mem_mb -gt 0 && $mem_mb -lt 1900 ]]; then
        warn "This machine has ${mem_mb} MB RAM; Tragwerk wants 2 GB or more."
    fi

    free_gb=$(df -BG --output=avail / 2> /dev/null | tail -n1 | tr -dc '0-9' || printf '')
    if [[ -n $free_gb && $free_gb -lt 10 ]]; then
        warn "Only ${free_gb} GB free on /. Images, repositories and the database need room to grow."
    fi
}

# Returns 0 and prints the listener when the port is taken.
port_listener() {
    local port=$1 out
    out=$(run_priv ss -ltnpH 2> /dev/null | awk -v p=":$port\$" '$4 ~ p {print; exit}') || true
    [[ -n $out ]] || return 1
    printf '%s' "$out"
}

# True when this machine already runs our own stack — then Traefik legitimately
# holds 80/443 and the port check must not abort a re-run.
own_stack_running() {
    have docker || return 1
    [[ -f $COMPOSE_FILE ]] || return 1
    local ids
    ids=$(dc ps -q 2> /dev/null) || return 1
    [[ -n $ids ]]
}

check_ports() {
    if ! have ss; then
        warn 'iproute2 (ss) is unavailable, so ports 80, 443 and the git push port were not checked.'
        return
    fi

    if own_stack_running; then
        ok 'The Tragwerk stack is already running here; skipping the port check.'
        return
    fi

    local port listener blocked=()
    for port in 80 443 "$SSH_PORT"; do
        if listener=$(port_listener "$port"); then
            warn "Port ${port} is already in use: ${listener}"
            blocked+=("$port")
        fi
    done

    if [[ ${#blocked[@]} -eq 0 ]]; then
        ok "Ports 80, 443 and ${SSH_PORT} are free."
        return
    fi

    # A busy SSH_PORT is usually the host's own sshd and is easy to work around;
    # 80/443 are not negotiable, Traefik must own them.
    if [[ " ${blocked[*]} " == *" 80 "* || " ${blocked[*]} " == *" 443 "* ]]; then
        die 'Traefik needs ports 80 and 443. Stop whatever holds them (a host nginx/apache, or another Traefik) and run the installer again.'
    fi

    [[ $CHECK_ONLY -eq 1 ]] && return

    local new_port=''
    ask new_port "Port ${SSH_PORT} is taken. Use which port for the git push target?" ''
    [[ -z $new_port ]] && die "Port ${SSH_PORT} is in use. Pick a free one with --ssh-port."
    SSH_PORT=$new_port
    validate_ssh_port
    port_listener "$SSH_PORT" > /dev/null && die "Port ${SSH_PORT} is in use as well."
    ok "Using port ${SSH_PORT} for the git push target."
}

preflight() {
    step 'Checking this machine'
    validate_ssh_port
    check_privileges
    detect_distro
    check_base_tools
    check_resources
    check_ports
}

# ---------------------------------------------------------------------------
# Docker
# ---------------------------------------------------------------------------

docker_ready() {
    have docker || return 1
    run_priv docker info > /dev/null 2>&1 || return 1
    run_priv docker compose version > /dev/null 2>&1
}

# Ports the logic of app/src/Tragwerk/Domain/Docker/DockerInstallScript.php to
# bash, so the control plane and the deploy targets are installed identically.
# Deliberately not `curl … get.docker.com | sh`: Docker documents that script as
# testing/dev only, the repository method uses GPG-verified, pinnable packages.
install_docker_apt() {
    local repo="https://download.docker.com/linux/${REPO_DISTRO}"
    local codename=$DISTRO_CODENAME

    # Derivatives put their own name in VERSION_CODENAME (e.g. Mint's "vanessa"),
    # which the Docker repository does not know. UBUNTU_CODENAME/DEBIAN_* is the
    # upstream release we actually need.
    if [[ $DISTRO_ID != "$REPO_DISTRO" || -z $codename ]]; then
        # shellcheck disable=SC1091
        codename=$(. /etc/os-release && printf '%s' "${UBUNTU_CODENAME:-${VERSION_CODENAME:-}}")
    fi
    [[ -n $codename ]] || die "Cannot determine the ${REPO_DISTRO} release codename for the Docker repository. Install Docker manually: $DOCKER_DOCS"

    export DEBIAN_FRONTEND=noninteractive
    run_priv install -m 0755 -d /etc/apt/keyrings
    run_priv curl -fsSL "${repo}/gpg" -o /etc/apt/keyrings/docker.asc
    run_priv chmod a+r /etc/apt/keyrings/docker.asc
    printf 'deb [arch=%s signed-by=/etc/apt/keyrings/docker.asc] %s %s stable\n' \
        "$(dpkg --print-architecture)" "$repo" "$codename" \
        | run_priv tee /etc/apt/sources.list.d/docker.list > /dev/null

    wait_for_apt_lock
    run_priv apt-get "${APT_LOCK_WAIT[@]}" update
    # shellcheck disable=SC2086
    run_priv apt-get "${APT_LOCK_WAIT[@]}" install -y $DOCKER_PACKAGES
}

install_docker_dnf() {
    local repo_url="https://download.docker.com/linux/${REPO_DISTRO}/docker-ce.repo"

    # Written directly instead of `dnf config-manager --add-repo`, which dnf5
    # (Fedora 41+) removed. A plain file drop works on dnf4, dnf5 and yum alike;
    # `install -y` imports the repository's GPG key with gpgcheck left on.
    run_priv curl -fsSL "$repo_url" -o /etc/yum.repos.d/docker-ce.repo
    if have dnf; then
        # shellcheck disable=SC2086
        run_priv dnf install -y $DOCKER_PACKAGES
    else
        # shellcheck disable=SC2086
        run_priv yum install -y $DOCKER_PACKAGES
    fi
}

ensure_docker() {
    step 'Docker Engine'

    if docker_ready; then
        ok "$(run_priv docker --version) with $(run_priv docker compose version --short 2> /dev/null || printf 'the Compose plugin')."
        return
    fi

    if [[ $CHECK_ONLY -eq 1 ]]; then
        warn 'Docker Engine or the Compose plugin is missing and would be installed.'
        return
    fi

    [[ $SKIP_DOCKER -eq 1 ]] && die "Docker Engine with the Compose plugin is required but --skip-docker was given. See $DOCKER_DOCS"

    info "Docker Engine or the Compose plugin is missing. It will be installed from Docker's official ${REPO_DISTRO} repository."
    confirm 'Install Docker now?' 'y' || die "Cannot continue without Docker. See $DOCKER_DOCS"

    if [[ $PKG_FAMILY == 'dnf' ]]; then
        install_docker_dnf
    else
        install_docker_apt
    fi

    run_priv systemctl enable --now docker \
        || die 'The Docker packages installed but the service would not start. Check: systemctl status docker'

    # A non-root sudoer needs docker-group membership to run `docker` without
    # sudo afterwards. Root already has socket access.
    if [[ -n $SUDO ]]; then
        local target_user=${SUDO_USER:-$(id -un)}
        run_priv usermod -aG docker "$target_user" || true
        warn "Added ${target_user} to the docker group — log out and back in for that to take effect."
    fi

    docker_ready || die 'Docker was installed but is not usable. Check: systemctl status docker'
    ok "$(run_priv docker --version) installed."
}

# ---------------------------------------------------------------------------
# Repository
# ---------------------------------------------------------------------------

# Uses the checkout the script sits in when there is one, otherwise clones.
prepare_repository() {
    step 'Repository'

    if [[ -f $COMPOSE_FILE && -f $ENV_TEMPLATE ]]; then
        PROJECT_DIR=$PWD
        ok "Using the checkout in ${PROJECT_DIR}."
        return
    fi

    local script_dir=''
    if [[ -n ${BASH_SOURCE[0]:-} && -f ${BASH_SOURCE[0]} ]]; then
        script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
    fi
    if [[ -n $script_dir && -f "$script_dir/$COMPOSE_FILE" && -f "$script_dir/$ENV_TEMPLATE" ]]; then
        PROJECT_DIR=$script_dir
        cd "$PROJECT_DIR"
        ok "Using the checkout in ${PROJECT_DIR}."
        return
    fi

    if [[ -d "$INSTALL_DIR/.git" ]]; then
        info "Updating the existing checkout in ${INSTALL_DIR}…"
        run_priv git -C "$INSTALL_DIR" pull --ff-only || warn 'Could not fast-forward the checkout; continuing with what is on disk.'
    else
        [[ -e $INSTALL_DIR && -n $(ls -A "$INSTALL_DIR" 2> /dev/null) ]] \
            && die "${INSTALL_DIR} exists and is not a Tragwerk checkout. Pick another path with --dir."
        info "Cloning ${REPO_URL} into ${INSTALL_DIR}…"
        run_priv git clone --depth 1 "$REPO_URL" "$INSTALL_DIR"
        # Hand the checkout to the invoking user so later `git pull` needs no sudo.
        [[ -n $SUDO ]] && run_priv chown -R "$(id -u):$(id -g)" "$INSTALL_DIR"
    fi

    PROJECT_DIR=$INSTALL_DIR
    cd "$PROJECT_DIR"
    [[ -f $COMPOSE_FILE && -f $ENV_TEMPLATE ]] \
        || die "${PROJECT_DIR} does not contain ${COMPOSE_FILE} and ${ENV_TEMPLATE}."
    ok "Repository ready in ${PROJECT_DIR}."
}

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

validate_host() {
    local host=$1 label=$2
    [[ $host =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$ && $host == *.* ]] \
        || die "${label} \"${host}\" is not a valid hostname."
}

validate_email() {
    local email=$1 label=$2
    [[ $email =~ ^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$ ]] \
        || die "${label} \"${email}\" is not a valid e-mail address."
}

validate_ssh_port() {
    [[ $SSH_PORT =~ ^[0-9]+$ && $SSH_PORT -ge 1 && $SSH_PORT -le 65535 ]] \
        || die "--ssh-port must be a number between 1 and 65535, got \"${SSH_PORT}\"."
}

collect_settings() {
    step 'Configuration'

    # A re-run inherits whatever the existing .env already says, which is also
    # what makes an unattended `--yes` re-run work without repeating every flag.
    if [[ -f $ENV_FILE ]]; then
        [[ -z $APP_HOST ]] && APP_HOST=$(get_env APP_HOST)
        [[ -z $ACME_EMAIL ]] && ACME_EMAIL=$(get_env ACME_EMAIL)
        [[ -z $DOCS_HOST ]] && DOCS_HOST=$(get_env DOCS_HOST)
    fi

    if [[ $ASSUME_YES -eq 1 ]]; then
        [[ -n $APP_HOST ]] || die '--yes needs --app-host (or an .env that already has APP_HOST).'
        [[ -n $ACME_EMAIL ]] || die '--yes needs --acme-email (or an .env that already has ACME_EMAIL).'
    fi

    while [[ -z $APP_HOST ]]; do
        ask APP_HOST 'Public hostname of the web UI (e.g. tragwerk.example.com)'
    done
    validate_host "$APP_HOST" 'APP_HOST'

    while [[ -z $ACME_EMAIL ]]; do
        ask ACME_EMAIL "Contact e-mail for Let's Encrypt"
    done
    validate_email "$ACME_EMAIL" 'ACME_EMAIL'

    if [[ -z $DOCS_HOST && $ASSUME_YES -eq 0 ]]; then
        ask DOCS_HOST 'Hostname for the bundled documentation (leave empty to skip)' ''
    fi
    [[ -n $DOCS_HOST ]] && validate_host "$DOCS_HOST" 'DOCS_HOST'

    validate_ssh_port

    [[ $REGISTRATION_ENABLED == 'false' || $REGISTRATION_ENABLED == 'true' ]] \
        || die "TRAGWERK_REGISTRATION_ENABLED must be true or false, got \"${REGISTRATION_ENABLED}\"."

    ok "Web UI:        https://${APP_HOST}"
    ok "ACME contact:  ${ACME_EMAIL}"
    ok "Documentation: ${DOCS_HOST:-not served}"
    ok "Git push port: ${SSH_PORT}"
    ok "Public sign-up: ${REGISTRATION_ENABLED}"
}

# get_env KEY — prints the value from .env, empty when unset.
get_env() {
    [[ -f $ENV_FILE ]] || return 0
    KEY=$1 awk 'BEGIN { key = ENVIRON["KEY"] }
        index($0, key "=") == 1 { print substr($0, length(key) + 2); exit }' "$ENV_FILE"
}

# set_env KEY VALUE — replaces the first assignment in place, appends when the
# key is absent. Done with awk rather than sed so base64 secrets containing
# slashes and plus signs need no escaping, and so comments survive.
set_env() {
    local key=$1 value=$2 tmp
    tmp=$(mktemp)
    KEY=$key VALUE=$value awk '
        BEGIN { key = ENVIRON["KEY"]; value = ENVIRON["VALUE"]; done = 0 }
        !done && index($0, key "=") == 1 { print key "=" value; done = 1; next }
        { print }
        END { if (!done) print key "=" value }
    ' "$ENV_FILE" > "$tmp"
    cat "$tmp" > "$ENV_FILE" # keeps the existing mode/ownership
    rm -f "$tmp"
}

gen_secret() {
    openssl rand -base64 "$1" | tr -d '\n'
}

# Fills KEY with a fresh secret only when it is still empty, so a re-run can
# never rotate a key that already encrypts data.
set_secret_if_empty() {
    local key=$1 bytes=$2
    [[ -n $(get_env "$key") ]] && return 0
    set_env "$key" "$(gen_secret "$bytes")"
    info "Generated ${key}."
}

write_env() {
    step 'Environment file'

    if [[ -f $ENV_FILE ]]; then
        ENV_EXISTED=1
        warn "${PROJECT_DIR}/.env already exists — keeping it. Existing secrets are never rotated."
        warn 'CREDENTIAL_ENCRYPTION_KEY and TWO_FACTOR_KEY encrypt data in the database; replacing them would make stored SSH keys and 2FA secrets permanently unreadable.'
    else
        cp "$ENV_TEMPLATE" "$ENV_FILE"
        chmod 600 "$ENV_FILE"
        ok "Created ${PROJECT_DIR}/.env from ${ENV_TEMPLATE}."
    fi

    set_env APP_HOST "$APP_HOST"
    set_env ACME_EMAIL "$ACME_EMAIL"
    set_env DOCS_HOST "$DOCS_HOST"
    set_env SSH_PORT "$SSH_PORT"
    set_env TRAGWERK_REGISTRATION_ENABLED "$REGISTRATION_ENABLED"
    # Must match the public app URL or live updates never reach the browser.
    set_env MERCURE_TOPIC_BASE "https://${APP_HOST}"

    set_secret_if_empty TRAGWERK_DATABASE_PASSWORD 32
    set_secret_if_empty MERCURE_PUBLISHER_JWT_SECRET 48
    set_secret_if_empty MERCURE_SUBSCRIBER_JWT_SECRET 48
    set_secret_if_empty TWO_FACTOR_KEY 32
    set_secret_if_empty CREDENTIAL_ENCRYPTION_KEY 32

    chmod 600 "$ENV_FILE"
    ok 'Environment file is complete.'
}

# ---------------------------------------------------------------------------
# DNS
# ---------------------------------------------------------------------------

local_addresses() {
    if have ip; then
        ip -o addr show scope global 2> /dev/null | awk '{split($4, a, "/"); print a[1]}'
    else
        hostname -I 2> /dev/null | tr ' ' '\n'
    fi
}

# Compares the A/AAAA records against this machine's own addresses. Deliberately
# no call to an external "what is my IP" service — behind NAT or a cloud load
# balancer the comparison can legitimately fail, which is what the override is for.
check_dns() {
    [[ $SKIP_DNS_CHECK -eq 1 ]] && return 0
    step 'DNS'

    local mine host resolved addr matched
    # `|| true` throughout: neither a machine without `ip`/`hostname` nor a name
    # that does not resolve (getent exits 2) is an installer error — both are
    # findings this check exists to report.
    mine=$(local_addresses || true)

    for host in "$APP_HOST" "$DOCS_HOST"; do
        [[ -z $host ]] && continue

        resolved=$(getent ahosts "$host" 2> /dev/null | awk '{print $1}' | sort -u || true)
        if [[ -z $resolved ]]; then
            warn "${host} does not resolve at all."
        else
            matched=0
            for addr in $resolved; do
                if grep -qxF "$addr" <<< "$mine"; then
                    matched=1
                    break
                fi
            done
            if [[ $matched -eq 1 ]]; then
                ok "${host} resolves to this machine."
                continue
            fi
            warn "${host} resolves to $(tr '\n' ' ' <<< "$resolved" | sed 's/ $//'), which is not an address of this machine."
        fi

        say ''
        warn "Traefik requests certificates over the TLS-ALPN challenge, which needs ${host} pointing here."
        warn "Let's Encrypt rate-limits repeated failures, so fixing DNS first is cheaper than retrying."

        [[ $CHECK_ONLY -eq 1 ]] && continue
        [[ $ASSUME_YES -eq 1 ]] && die "DNS for ${host} does not point at this machine. Fix the record, or re-run with --skip-dns-check to proceed anyway."
        confirm "Start anyway and risk a failed certificate for ${host}?" 'n' \
            || die 'Aborted. Point the DNS record at this machine and run the installer again.'
    done
}

# ---------------------------------------------------------------------------
# Firewall
# ---------------------------------------------------------------------------

open_firewall() {
    [[ $SKIP_FIREWALL -eq 1 ]] && return 0
    step 'Firewall'

    local ports=(80 443 "$SSH_PORT")
    local port_list="80/tcp, 443/tcp and ${SSH_PORT}/tcp"

    if have ufw && run_priv ufw status 2> /dev/null | head -n1 | grep -q 'active'; then
        if [[ $CHECK_ONLY -eq 1 ]]; then
            warn "ufw is active; ${port_list} would be opened."
        elif confirm "ufw is active. Open ${port_list}?" 'y'; then
            local port
            for port in "${ports[@]}"; do
                run_priv ufw allow "${port}/tcp" > /dev/null
            done
            ok "Opened ${port_list} in ufw."
        else
            warn 'Left ufw untouched — Tragwerk will be unreachable until those ports are open.'
        fi
    elif have firewall-cmd && run_priv firewall-cmd --state 2> /dev/null | grep -q 'running'; then
        if [[ $CHECK_ONLY -eq 1 ]]; then
            warn "firewalld is running; ${port_list} would be opened."
        elif confirm "firewalld is running. Open ${port_list}?" 'y'; then
            local port
            for port in "${ports[@]}"; do
                run_priv firewall-cmd --permanent --add-port="${port}/tcp" > /dev/null
            done
            run_priv firewall-cmd --reload > /dev/null
            ok "Opened ${port_list} in firewalld."
        else
            warn 'Left firewalld untouched — Tragwerk will be unreachable until those ports are open.'
        fi
    else
        ok 'No active host firewall found.'
    fi

    info "If your provider has a security group or cloud firewall in front of this machine, open ${port_list} there too — the installer cannot."
}

# ---------------------------------------------------------------------------
# Stack
# ---------------------------------------------------------------------------

container_field() {
    local service=$1 template=$2 cid
    cid=$(dc ps -aq "$service" 2> /dev/null | head -n1) || return 1
    [[ -n $cid ]] || return 1
    run_priv docker inspect -f "$template" "$cid" 2> /dev/null
}

start_stack() {
    step 'Starting the stack'

    info 'Pulling images…'
    dc pull

    info 'Starting services…'
    # `up -d` blocks on migrate because the app and workers declare
    # service_completed_successfully on it.
    if ! dc up -d; then
        say ''
        warn 'Startup failed. The last lines of the migrate log:'
        dc logs --tail 50 migrate || true
        die 'The stack did not come up.'
    fi

    local migrate_state
    migrate_state=$(container_field migrate '{{.State.Status}}:{{.State.ExitCode}}' || printf '')
    case $migrate_state in
        exited:0) ok 'Database migrations applied.' ;;
        '') warn 'Could not determine the state of the migrate service.' ;;
        *)
            warn 'The migrate service did not finish successfully:'
            dc logs --tail 50 migrate || true
            die 'Migrations failed; the app and workers stay down on purpose. Fix the cause and re-run the installer.'
            ;;
    esac

    wait_for_app
    say ''
    dc ps
}

wait_for_app() {
    local waited=0 status
    info 'Waiting for the app to become healthy…'
    while [[ $waited -lt 120 ]]; do
        status=$(container_field app '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' || printf '')
        case $status in
            healthy | running)
                ok 'The app is up.'
                return 0
                ;;
            exited | dead)
                warn 'The app container stopped:'
                dc logs --tail 50 app || true
                die 'The app did not start.'
                ;;
        esac
        sleep 3
        waited=$((waited + 3))
    done
    warn "The app is still not healthy after two minutes. Check: docker compose -f ${COMPOSE_FILE} logs app"
}

# ---------------------------------------------------------------------------
# First user
# ---------------------------------------------------------------------------

create_first_user() {
    step 'First user'

    local cmd="cd ${PROJECT_DIR} && ${SUDO:+sudo }docker compose -f ${COMPOSE_FILE} exec app bin/cli user:create you@example.com Ada Lovelace"

    if [[ $HAS_TTY -eq 0 ]]; then
        info 'No terminal available, so no account was created. Create yours with:'
        say ''
        say "    ${cmd}"
        return
    fi

    if [[ -z $ADMIN_EMAIL && $ASSUME_YES -eq 1 ]]; then
        info 'Skipped in non-interactive mode. Create your account with:'
        say ''
        say "    ${cmd}"
        return
    fi

    if [[ -z $ADMIN_EMAIL ]]; then
        confirm 'Create the first user account now?' 'y' || {
            info "Later: ${cmd}"
            return
        }
        while [[ -z $ADMIN_EMAIL ]]; do ask ADMIN_EMAIL 'E-mail'; done
    fi
    validate_email "$ADMIN_EMAIL" 'admin e-mail'
    while [[ -z $ADMIN_FIRSTNAME ]]; do ask ADMIN_FIRSTNAME 'First name'; done
    while [[ -z $ADMIN_LASTNAME ]]; do ask ADMIN_LASTNAME 'Last name'; done

    # --password is deliberately not used: it would land in the shell history and
    # in the process list. bin/cli asks for the password on a hidden prompt, which
    # needs a real terminal on both ends of `docker compose exec`.
    say ''
    if dc exec app bin/cli user:create "$ADMIN_EMAIL" "$ADMIN_FIRSTNAME" "$ADMIN_LASTNAME" \
        < /dev/tty > /dev/tty 2>&1; then
        ok "User ${ADMIN_EMAIL} created and confirmed."
    else
        warn 'Creating the user failed. Retry with:'
        say "    ${cmd}"
    fi
}

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------

summary() {
    say ''
    printf '%s%sTragwerk is running.%s\n' "$COLOR_GREEN" "$COLOR_BOLD" "$COLOR_RESET"
    say ''
    say "  Web UI          https://${APP_HOST}"
    [[ -n $DOCS_HOST ]] && say "  Documentation   https://${DOCS_HOST}"
    say "  Git push port   ${SSH_PORT}/tcp"
    say "  Installation    ${PROJECT_DIR}"
    if [[ $ENV_EXISTED -eq 1 ]]; then
        say "  Configuration   ${PROJECT_DIR}/.env (existing file kept, secrets untouched)"
    else
        say "  Configuration   ${PROJECT_DIR}/.env"
    fi
    [[ -n $LOG_FILE ]] && say "  Install log     ${LOG_FILE}"
    say ''
    printf '%sBack up %s/.env now.%s\n' "$COLOR_YELLOW" "$PROJECT_DIR" "$COLOR_RESET"
    say "CREDENTIAL_ENCRYPTION_KEY encrypts your servers' SSH keys, TWO_FACTOR_KEY encrypts your"
    say "users' 2FA secrets. Lose them and a database backup alone cannot restore that data:"
    say 'https://docs.tragwerk.app/install/backup'
    say ''
    say 'Next:'
    say "  1. Sign in at https://${APP_HOST} and enable two-factor authentication."
    say '  2. Register your first deploy target: https://docs.tragwerk.app/guide/getting-started'
    say ''
    say "Useful commands (from ${PROJECT_DIR}):"
    say "  docker compose -f ${COMPOSE_FILE} ps"
    say "  docker compose -f ${COMPOSE_FILE} logs -f app"
    say "  git pull && docker compose -f ${COMPOSE_FILE} pull && docker compose -f ${COMPOSE_FILE} up -d"
}

# ---------------------------------------------------------------------------

main() {
    # $BASH_COMMAND names the command that actually failed, which matters because
    # $LINENO points into run_priv() for every privileged call.
    trap 'on_error "$LINENO" "$BASH_COMMAND"' ERR
    setup_colors
    open_tty
    parse_args "$@"

    printf '%s%sTragwerk installer %s%s\n' "$COLOR_BOLD" "$COLOR_BLUE" "$INSTALLER_VERSION" "$COLOR_RESET"

    [[ $CHECK_ONLY -eq 0 ]] && start_logging

    preflight
    ensure_docker

    if [[ $CHECK_ONLY -eq 1 ]]; then
        [[ -n $APP_HOST ]] && check_dns
        open_firewall
        say ''
        ok 'Preflight finished. Nothing was changed.'
        exit 0
    fi

    prepare_repository
    collect_settings
    write_env
    check_dns
    open_firewall
    start_stack
    create_first_user
    summary
}

main "$@"
