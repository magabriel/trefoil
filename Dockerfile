FROM ubuntu:18.04

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        apt-transport-https \
        php7.2-cli \
        git-core \
        curl \
        wget \
        ssh \
        vim-tiny \
        php7.2-curl \
        php7.2-gd \
        php7.2-imagick \
        php7.2-intl \
#        php7.2-mcrypt \
        php7.2-tidy \
        php7.2-zip \
        libgif7 \
        software-properties-common \
#        python-software-properties \
        zip \
        libpixman-1-0 \
        && apt-get clean \
        && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# download and install msttcorefonts
RUN echo ttf-mscorefonts-installer msttcorefonts/accepted-mscorefonts-eula select true | debconf-set-selections
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ttf-mscorefonts-installer --quiet \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# download and install princexml
RUN curl -O https://www.princexml.com/download/prince_12.4-1_ubuntu18.04_amd64.deb \
    && dpkg -i prince_12.4-1_ubuntu18.04_amd64.deb \
    && apt-get -f install \ 
    && rm -f prince_12.4-1_ubuntu18.04_amd64.deb

# download and install kindlegen
RUN wget http://kindlegen.s3.amazonaws.com/kindlegen_linux_2.6_i386_v2_9.tar.gz -O /tmp/kindlegen_linux_2.6_i386_v2_9.tar.gz \
    && tar -xzf /tmp/kindlegen_linux_2.6_i386_v2_9.tar.gz -C /tmp \
    && mv /tmp/kindlegen /usr/local/bin \
    && rm -r /tmp/*

ADD ./ /app/trefoil
WORKDIR /app/trefoil

ENTRYPOINT ["/app/trefoil/book"]