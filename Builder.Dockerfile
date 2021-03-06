FROM docker:20-git

MAINTAINER Nikita Tarasov <nikita@mygento.ru>

ENV VAULT_VERSION=1.7.3 WAYPOINT_VERSION=0.4.1 NOMAD_VERSION=1.1.2

COPY --from=hairyhenderson/gomplate:v3.9.0 /gomplate /bin/gomplate

RUN wget -q https://releases.hashicorp.com/vault/${VAULT_VERSION}/vault_${VAULT_VERSION}_linux_amd64.zip && \
    unzip vault_${VAULT_VERSION}_linux_amd64.zip && \
    mv vault /usr/local/bin/vault && \
    chmod +x /usr/local/bin/vault && \
    rm vault_${VAULT_VERSION}_linux_amd64.zip

RUN wget -q https://releases.hashicorp.com/waypoint/${WAYPOINT_VERSION}/waypoint_${WAYPOINT_VERSION}_linux_amd64.zip && \
    unzip waypoint_${WAYPOINT_VERSION}_linux_amd64.zip && \
    mv waypoint /usr/local/bin/waypoint && \
    chmod +x /usr/local/bin/waypoint && \
    rm waypoint_${WAYPOINT_VERSION}_linux_amd64.zip

RUN wget -q -O /etc/apk/keys/sgerrand.rsa.pub https://alpine-pkgs.sgerrand.com/sgerrand.rsa.pub && \
    wget https://github.com/sgerrand/alpine-pkg-glibc/releases/download/2.33-r0/glibc-2.33-r0.apk && \
    apk add glibc-2.33-r0.apk && \
    wget -q https://releases.hashicorp.com/nomad/${NOMAD_VERSION}/nomad_${NOMAD_VERSION}_linux_amd64.zip && \
    unzip nomad_${NOMAD_VERSION}_linux_amd64.zip && \
    mv nomad /usr/local/bin/nomad && \
    chmod +x /usr/local/bin/nomad && \
    /usr/local/bin/nomad -v && \
    rm nomad_${NOMAD_VERSION}_linux_amd64.zip

RUN apk add --no-cache php-cli php-curl composer && \
    composer global require symfony/console && \
    composer global require guzzlehttp/guzzle && \
    rm -fR ~/.composer/cache

RUN apk add --no-cache nodejs yarn npm

RUN apk add --no-cache ruby ruby-dev ruby-ffi && \
    gem install specific_install && \
    gem install -N bundler && \
    gem specific_install https://github.com/luckyraul/mina.git relative_path && \
    gem install scss_lint
