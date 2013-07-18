FROM jbgi/arch-base
MAINTAINER Spencer Rinehart <anubis@overthemonkey.com>

RUN exec pacman -Syy >/dev/tty 2>/dev/tty </dev/tty
RUN pacman -S --noconfirm binutils gcc make >/dev/tty 2>/dev/tty </dev/tty

ADD php /tmp/php

RUN bash -c 'cd /tmp/php; makepkg -ci --asroot --noconfirm'

ADD php.ini /etc/php/php.ini
