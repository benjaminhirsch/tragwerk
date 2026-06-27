#!/usr/bin/env bats
#
# Tests the security-critical parsing/authorization flow of git-auth-wrapper.
# curl and git-shell are stubbed via PATH; the stubs record their invocation and
# return a configurable exit code (CURL_EXIT).

setup() {
    WRAPPER="${BATS_TEST_DIRNAME}/../git-auth-wrapper"
    UUID="019e9958-ef8e-71f8-b2fe-665b0a594391"

    TMP="$(mktemp -d)"
    BINDIR="$TMP/bin"
    mkdir -p "$BINDIR"
    export CURL_LOG="$TMP/curl.log"
    export SHELL_LOG="$TMP/git-shell.log"

    cat > "$BINDIR/curl" <<EOF
#!/bin/sh
echo "\$@" >> "$CURL_LOG"
exit \${CURL_EXIT:-0}
EOF

    cat > "$BINDIR/git-shell" <<EOF
#!/bin/sh
echo "\$@" >> "$SHELL_LOG"
exit 0
EOF

    chmod +x "$BINDIR/curl" "$BINDIR/git-shell"
    export PATH="$BINDIR:$PATH"
    export APP_INTERNAL_URL="http://app"
}

teardown() {
    rm -rf "$TMP"
}

@test "rejects missing key id" {
    export SSH_ORIGINAL_COMMAND="git-upload-pack 'repos/${UUID}'"
    run sh "$WRAPPER"
    [ "$status" -eq 1 ]
    [ ! -f "$CURL_LOG" ]
}

@test "rejects interactive shell (no command)" {
    unset SSH_ORIGINAL_COMMAND
    run sh "$WRAPPER" keyid
    [ "$status" -eq 1 ]
    [ ! -f "$CURL_LOG" ]
}

@test "rejects non-git command" {
    export SSH_ORIGINAL_COMMAND="rm -rf /"
    run sh "$WRAPPER" keyid
    [ "$status" -eq 1 ]
    [ ! -f "$CURL_LOG" ]
}

@test "rejects path traversal before any auth call" {
    export SSH_ORIGINAL_COMMAND="git-receive-pack 'repos/../../etc'"
    run sh "$WRAPPER" keyid
    [ "$status" -eq 1 ]
    [ ! -f "$CURL_LOG" ]
}

@test "upload-pack maps to op=read and delegates when allowed" {
    export SSH_ORIGINAL_COMMAND="git-upload-pack 'repos/${UUID}'"
    export CURL_EXIT=0
    run sh "$WRAPPER" key-abc
    [ "$status" -eq 0 ]
    grep -q '"op":"read"' "$CURL_LOG"
    grep -q "$UUID" "$CURL_LOG"
    grep -q 'key-abc' "$CURL_LOG"
    [ -f "$SHELL_LOG" ]
}

@test "receive-pack maps to op=write and delegates when allowed" {
    export SSH_ORIGINAL_COMMAND="git-receive-pack 'repos/${UUID}'"
    export CURL_EXIT=0
    run sh "$WRAPPER" key-abc
    [ "$status" -eq 0 ]
    grep -q '"op":"write"' "$CURL_LOG"
    [ -f "$SHELL_LOG" ]
}

@test "denies (403) without delegating to git-shell" {
    export SSH_ORIGINAL_COMMAND="git-receive-pack 'repos/${UUID}'"
    export CURL_EXIT=22
    run sh "$WRAPPER" key-abc
    [ "$status" -eq 1 ]
    [ ! -f "$SHELL_LOG" ]
}

@test "fails closed when app endpoint is unreachable" {
    export SSH_ORIGINAL_COMMAND="git-upload-pack 'repos/${UUID}'"
    export CURL_EXIT=7
    run sh "$WRAPPER" key-abc
    [ "$status" -eq 1 ]
    [ ! -f "$SHELL_LOG" ]
}
