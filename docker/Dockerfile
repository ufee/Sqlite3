ARG PHP_VERSION=8.4

# SQLite with UPDATE/DELETE ... LIMIT support, built once for all PHP images.
# Canonical sources are required: the amalgamation ignores SQLITE_ENABLE_UPDATE_DELETE_LIMIT.
# Ubuntu 20.04 has glibc 2.31 (as Debian bullseye in php:7.4/8.0), so the library runs in every image.
FROM ubuntu:20.04 AS sqlite
ARG SQLITE_YEAR=2024
ARG SQLITE_VERSION=3460100
RUN set -eux; \
    apt-get update; \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends build-essential tcl curl ca-certificates unzip; \
    cd /tmp; \
    curl -fsSLO "https://www.sqlite.org/${SQLITE_YEAR}/sqlite-src-${SQLITE_VERSION}.zip"; \
    unzip -q "sqlite-src-${SQLITE_VERSION}.zip"; \
    cd "sqlite-src-${SQLITE_VERSION}"; \
    CFLAGS="-O2 -DSQLITE_ENABLE_UPDATE_DELETE_LIMIT=1 -DSQLITE_ENABLE_COLUMN_METADATA=1" \
        ./configure --prefix=/usr/local --disable-tcl --disable-static; \
    make -j"$(nproc)" libsqlite3.la; \
    mkdir -p /out; \
    cp -a .libs/libsqlite3.so* /out/

FROM php:${PHP_VERSION}-cli

# No apt here: repositories of old Debian releases used by php:7.4/8.0 are gone
COPY --from=sqlite /out/ /usr/local/lib/
RUN ldconfig
ENV LD_LIBRARY_PATH=/usr/local/lib

# Fail the build if PHP does not pick up SQLite with UPDATE/DELETE ... LIMIT support
RUN php -r '$db = new SQLite3(":memory:"); $db->exec("CREATE TABLE t (id INTEGER)"); \
    if (!@$db->prepare("DELETE FROM t LIMIT 1")) { fwrite(STDERR, "SQLITE_ENABLE_UPDATE_DELETE_LIMIT is not available\n"); exit(1); } \
    echo "PHP ", PHP_VERSION, ", SQLite ", SQLite3::version()["versionString"], "\n";'

WORKDIR /app
