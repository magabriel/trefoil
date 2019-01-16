FROM ubuntu:18.04

ENV DEBIAN_FRONTEND noninteractive

RUN locale-gen es_ES.UTF-8
ENV LANG       es_ES.UTF-8
ENV LC_ALL     es_ES.UTF-8

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        apt-transport-https \
        php7-cli \
        git-core \
        curl \
        ssh \
        vim-tiny \
        php7-curl \
        php7-gd \
        php7-imagick \
        php7-intl \
        php7-mcrypt \
        php7-tidy \
        libgif4 \
        software-properties-common \
        python-software-properties \
        zip \
        libpixman-1-0 \
        && apt-get clean \
        && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN add-apt-repository -y "deb http://archive.ubuntu.com/ubuntu bionic multiverse"

RUN echo ttf-mscorefonts-installer msttcorefonts/accepted-mscorefonts-eula select true | debconf-set-selections

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ttf-mscorefonts-installer --quiet \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN curl -O https://www.princexml.com/download/prince_12.4-1_ubuntu18.04_amd64.deb \
    && dpkg -i prince_12.4-1_ubuntu18.04_amd64.deb \
    && apt-get -f install \ 
    && rm -f prince_12.4-1_ubuntu18.04_amd64.deb

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        wget \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

ADD ./ /app/trefoil
WORKDIR /app/trefoil

ENTRYPOINT ["/app/trefoil/book"]