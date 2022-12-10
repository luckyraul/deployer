FROM docker:20-git

MAINTAINER Nikita Tarasov <nikita@mygento.ru>

ENV VAULT_VERSION=1.12.2 WAYPOINT_VERSION=0.10.4 NOMAD_VERSION=1.4.3 LEVANT_VERSION=0.3.2 NOMADPACK_VERSION=0.0.1-techpreview.4

COPY --from=hairyhenderson/gomplate:v3.11.3 /gomplate /bin/gomplate

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

RUN wget -q https://releases.hashicorp.com/levant/${LEVANT_VERSION}/levant_${LEVANT_VERSION}_linux_amd64.zip && \
    unzip levant_${LEVANT_VERSION}_linux_amd64.zip && \
    mv levant /usr/local/bin/levant && \
    chmod +x /usr/local/bin/levant && \
    rm levant_${LEVANT_VERSION}_linux_amd64.zip

RUN wget -q -O /etc/apk/keys/sgerrand.rsa.pub https://alpine-pkgs.sgerrand.com/sgerrand.rsa.pub && \
    wget https://github.com/sgerrand/alpine-pkg-glibc/releases/download/2.34-r0/glibc-2.34-r0.apk && \
    apk del libc6-compat && \
    apk add --no-cache --force-overwrite glibc-2.34-r0.apk && \
    wget -q https://releases.hashicorp.com/nomad/${NOMAD_VERSION}/nomad_${NOMAD_VERSION}_linux_amd64.zip && \
    unzip nomad_${NOMAD_VERSION}_linux_amd64.zip && \
    mv nomad /usr/local/bin/nomad && \
    chmod +x /usr/local/bin/nomad && \
    /usr/local/bin/nomad -v && \
    rm nomad_${NOMAD_VERSION}_linux_amd64.zip

RUN wget -q https://github.com/hashicorp/nomad-pack/releases/download/nightly/nomad-pack_${NOMADPACK_VERSION}_linux_amd64.zip && \
    unzip nomad-pack_${NOMADPACK_VERSION}_linux_amd64.zip && \
    mv nomad-pack /usr/local/bin/nomad-pack && \
    chmod +x /usr/local/bin/nomad-pack && \
    rm nomad-pack_${NOMADPACK_VERSION}_linux_amd64.zip

RUN apk add --no-cache php81-curl php81-iconv php81-mbstring php81-openssl php81-phar php81-zip curl php81-pecl-imagick && \
    # ln -s /usr/bin/php81 /usr/bin/php && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer && \
    composer global require symfony/console && \
    composer global require guzzlehttp/guzzle && \
    rm -fR ~/.composer/cache

RUN apk add --no-cache nodejs yarn npm

RUN apk add --no-cache ruby ruby-dev ruby-ffi && \
    gem install specific_install && \
    gem install -N bundler && \
    gem specific_install https://github.com/luckyraul/mina.git relative_path && \
    gem install scss_lint
