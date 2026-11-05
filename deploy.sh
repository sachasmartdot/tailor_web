#!/usr/bin/env bash
# Deploiement de Tailor Platform : Ubuntu 22.04 ou 24.04, nginx, PHP-FPM, HTTPS.
# A lancer SUR LE SERVEUR : sudo bash deploy.sh
# Relancable sans risque : chaque etape verifie l'etat avant d'agir.

# s'arreter a la premiere erreur, refuser les variables vides
set -euo pipefail

log(){ echo -e "\n==> $*"; }

# dossier du projet = dossier ou se trouve ce script
APP_DIR="$(cd "$(dirname "$0")" && pwd)"

# valeurs personnelles dans deploy.env, jamais dans GitHub
source "$APP_DIR/deploy.env"

# refuse les variables absentes OU vides
: "${DOMAIN:?DOMAIN manquant dans deploy.env}"
: "${EMAIL:?EMAIL manquant dans deploy.env}"
: "${AANGARAA_KEY:?AANGARAA_KEY manquant dans deploy.env}"

step_verifier(){
  log "Verification de l'environnement"
  [ "$(id -u)" -eq 0 ] || { echo "Lancer avec sudo."; exit 1; }
  [ -f "$APP_DIR/composer.json" ] || { echo "composer.json introuvable dans $APP_DIR"; exit 1; }
  [ -f "$APP_DIR/cle-firebase.json" ] || { echo "cle-firebase.json absente : la copier avec scp."; exit 1; }
}

step_paquets(){
  log "Installation des paquets"
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y
  apt-get install -y nginx php-fpm php-cli php-curl php-mbstring php-xml \
    php-bcmath php-zip unzip git curl composer certbot python3-certbot-nginx \
    iptables-persistent
}

step_swap(){
  log "Swap de 1 Go (utile avec 1 Go de RAM)"
  if [ ! -f /swapfile ]; then
    fallocate -l 1G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
  fi
  swapon --show | grep -q /swapfile || swapon /swapfile
  grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
}

step_pare_feu(){
  log "Ouverture des ports 80 et 443 dans le systeme"
  # -C verifie si la regle existe deja : on n'ajoute que si elle manque
  for port in 80 443; do
    iptables -C INPUT -p tcp --dport "$port" -j ACCEPT 2>/dev/null \
      || iptables -I INPUT 1 -p tcp --dport "$port" -j ACCEPT
  done
  netfilter-persistent save
}

step_code(){
  log "Mise a jour du code depuis GitHub"
  git -C "$APP_DIR" pull --ff-only
}

step_composer(){
  log "Installation des dependances PHP"
  cd "$APP_DIR"
  # ext-grpc ignoree : l'application utilise le transport REST (voir bootstrap.php)
  COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader \
    --no-interaction --ignore-platform-req=ext-grpc
}

step_php(){
  log "Configuration de PHP-FPM"
  PHPV="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"

  cat > "/etc/php/$PHPV/fpm/conf.d/90-tailor.ini" <<EOF
display_errors = Off
log_errors = On
date.timezone = Africa/Douala
upload_max_filesize = 5M
post_max_size = 8M
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Lax
EOF

  # Pool dedie : c'est ici que PHP-FPM recoit les variables d'environnement
  cat > "/etc/php/$PHPV/fpm/pool.d/tailor.conf" <<EOF
[tailor]
user = www-data
group = www-data
listen = /run/php/tailor.sock
listen.owner = www-data
listen.group = www-data
pm = ondemand
pm.max_children = 5
pm.process_idle_timeout = 30s
env[APP_ENV] = production
env[AANGARAA_KEY] = "$AANGARAA_KEY"
env[AANGARAA_SITE] = "https://$DOMAIN"
php_admin_value[error_log] = /var/log/tailor-php-error.log
EOF
  chmod 600 "/etc/php/$PHPV/fpm/pool.d/tailor.conf"   # contient la cle Aangaraa

  touch /var/log/tailor-php-error.log
  chown www-data:www-data /var/log/tailor-php-error.log
  systemctl restart "php$PHPV-fpm"
}

step_droits(){
  log "Droits sur les dossiers et la cle Firebase"
  mkdir -p "$APP_DIR/storage/payments" "$APP_DIR/public/assets/users"
  chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/public/assets/users"
  # la cle est hors de public/ (non servie par nginx), lisible par PHP seulement
  chown root:www-data "$APP_DIR/cle-firebase.json"
  chmod 640 "$APP_DIR/cle-firebase.json"
}

step_nginx(){
  log "Configuration de nginx"
  # \$ = un $ litteral pour nginx (sinon bash le remplacerait)
  cat > /etc/nginx/sites-available/tailor <<EOF
server {
    listen 80;
    server_name $DOMAIN;
    root $APP_DIR/public;
    index index.php;
    client_max_body_size 8M;

    # jamais d'execution de PHP dans les dossiers d'envoi, ni de fichiers caches
    location ~* ^/assets/users/.*\.php\$ { deny all; }
    location ~ /\. { deny all; }

    location / {
        try_files \$uri \$uri/ =404;
    }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/tailor.sock;
    }
}
EOF
  ln -sf /etc/nginx/sites-available/tailor /etc/nginx/sites-enabled/tailor
  rm -f /etc/nginx/sites-enabled/default
  nginx -t
  systemctl enable --now nginx
  systemctl reload nginx
}

step_https(){
  log "Certificat HTTPS (Let's Encrypt)"
  # Le DNS de $DOMAIN doit deja pointer vers ce serveur, et le port 80 etre ouvert.
  # Eviter de relancer en boucle en cas d'echec : Let's Encrypt limite les essais.
  certbot --nginx -d "$DOMAIN" -m "$EMAIL" --agree-tos --non-interactive \
    --keep-until-expiring --redirect
}

step_test(){
  log "Test du site"
  code="$(curl -s -o /dev/null -w '%{http_code}' "https://$DOMAIN/")"
  body="$(curl -s "https://$DOMAIN/")"
  echo "Reponse HTTP : $code"
  # bootstrap.php affiche "Fatal: ..." avec un code 200 si Firebase ne s'initialise pas
  if grep -q "Fatal" <<< "$body"; then echo "Erreur Firebase detectee dans la page."; exit 1; fi
  [ "$code" = "200" ] || { echo "Code inattendu."; exit 1; }
  echo "Site en ligne : https://$DOMAIN/"
}

# ordre d'execution (commenter les dernieres lignes pour avancer pas a pas)
step_verifier
step_paquets
step_swap
step_pare_feu
step_code
step_composer
step_php
step_droits
step_nginx
step_https
step_test
