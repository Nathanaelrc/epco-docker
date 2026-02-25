#!/bin/bash
# =============================================
# EPCO - Mail Relay Entrypoint
# Configura Postfix como relay SMTP
# =============================================

set -e

echo "================================================"
echo " EPCO Mail Relay - Configurando Postfix"
echo "================================================"

# Variables con defaults
RELAY_HOST="${RELAY_HOST:-smtp.gmail.com}"
RELAY_PORT="${RELAY_PORT:-587}"
RELAY_USER="${RELAY_USER:-}"
RELAY_PASS="${RELAY_PASS:-}"
MAIL_HOSTNAME="${MAIL_HOSTNAME:-mail.epco.local}"
ALLOWED_NETWORKS="${ALLOWED_NETWORKS:-172.16.0.0/12 192.168.0.0/16 10.0.0.0/8}"

# Validar credenciales
if [ -z "$RELAY_USER" ] || [ -z "$RELAY_PASS" ]; then
    echo "[EPCO Mail] ADVERTENCIA: No se configuraron credenciales SMTP (RELAY_USER/RELAY_PASS)"
    echo "[EPCO Mail] El relay funcionará pero los correos podrían ser rechazados"
fi

echo "[EPCO Mail] Relay host: ${RELAY_HOST}:${RELAY_PORT}"
echo "[EPCO Mail] Usuario: ${RELAY_USER}"
echo "[EPCO Mail] Hostname: ${MAIL_HOSTNAME}"

# =============================================
# Configuración principal de Postfix
# =============================================
cat > /etc/postfix/main.cf << EOF
# Identificación
myhostname = ${MAIL_HOSTNAME}
mydomain = ${MAIL_HOSTNAME}
myorigin = \$myhostname

# Solo escuchar en todas las interfaces (contenedor Docker)
inet_interfaces = all
inet_protocols = ipv4

# Relay host externo (Outlook, Gmail, etc.)
relayhost = [${RELAY_HOST}]:${RELAY_PORT}

# Redes permitidas (solo contenedores Docker internos)
mynetworks = 127.0.0.0/8 ${ALLOWED_NETWORKS}

# No ser un relay abierto - solo aceptar de redes permitidas
smtpd_recipient_restrictions = permit_mynetworks, reject_unauth_destination

# SASL autenticación para el relay externo
smtp_sasl_auth_enable = yes
smtp_sasl_password_maps = lmdb:/etc/postfix/sasl_passwd
smtp_sasl_security_options = noanonymous
smtp_sasl_tls_security_options = noanonymous
smtp_sasl_mechanism_filter = plain login

# TLS para conexión al relay externo
smtp_use_tls = yes
smtp_tls_security_level = encrypt
smtp_tls_CAfile = /etc/ssl/certs/ca-certificates.crt
smtp_tls_loglevel = 1
smtp_tls_session_cache_database = lmdb:\${data_directory}/smtp_tls_session_cache

# TLS para conexiones entrantes (opcional pero buena práctica)
smtpd_use_tls = no

# Tamaño máximo de mensaje (25MB)
message_size_limit = 26214400

# Cola de correos
maximal_queue_lifetime = 3d
bounce_queue_lifetime = 1d
minimal_backoff_time = 300s
maximal_backoff_time = 4000s

# Logging
maillog_file = /dev/stdout

# Compatibilidad
compatibility_level = 3.6

# Header para identificar el relay
header_checks = regexp:/etc/postfix/header_checks
EOF

# =============================================
# Archivo de credenciales SASL
# =============================================
echo "[${RELAY_HOST}]:${RELAY_PORT} ${RELAY_USER}:${RELAY_PASS}" > /etc/postfix/sasl_passwd
postmap lmdb:/etc/postfix/sasl_passwd
chmod 600 /etc/postfix/sasl_passwd /etc/postfix/sasl_passwd.lmdb
echo "[EPCO Mail] ✓ Credenciales SASL configuradas"

# =============================================
# Header checks - agregar identificación
# =============================================
cat > /etc/postfix/header_checks << EOF
/^Subject:/ WARN
EOF

# =============================================
# Configuración master.cf - habilitar submission port
# =============================================
cat > /etc/postfix/master.cf << 'EOF'
# ==========================================================================
# service type  private unpriv  chroot  wakeup  maxproc command + args
# ==========================================================================
smtp      inet  n       -       n       -       -       smtpd
pickup    unix  n       -       n       60      1       pickup
cleanup   unix  n       -       n       -       0       cleanup
qmgr      unix  n       -       n       300     1       qmgr
tlsmgr    unix  -       -       n       1000?   1       tlsmgr
rewrite   unix  -       -       n       -       -       trivial-rewrite
bounce    unix  -       -       n       -       0       bounce
defer     unix  -       -       n       -       0       bounce
trace     unix  -       -       n       -       0       bounce
verify    unix  -       -       n       -       1       verify
flush     unix  n       -       n       1000?   0       flush
proxymap  unix  -       -       n       -       -       proxymap
proxywrite unix -       -       n       -       1       proxymap
smtp      unix  -       -       n       -       -       smtp
relay     unix  -       -       n       -       -       smtp
        -o syslog_name=postfix/$service_name
showq     unix  n       -       n       -       -       showq
error     unix  -       -       n       -       -       error
retry     unix  -       -       n       -       -       error
discard   unix  -       -       n       -       -       discard
local     unix  -       n       n       -       -       local
virtual   unix  -       n       n       -       -       virtual
lmtp      unix  -       -       n       -       -       lmtp
anvil     unix  -       -       n       -       1       anvil
scache    unix  -       -       n       -       1       scache
postlog   unix-dgram n  -       n       -       1       postlogd
EOF

# =============================================
# Crear directorios necesarios
# =============================================
mkdir -p /var/spool/postfix/pid
mkdir -p /var/mail
newaliases 2>/dev/null || true

echo "[EPCO Mail] ✓ Postfix configurado"
echo "[EPCO Mail] ✓ Relay: [${RELAY_HOST}]:${RELAY_PORT}"
echo "[EPCO Mail] Iniciando Postfix..."
echo "================================================"

# Iniciar Postfix en foreground
exec postfix start-fg
