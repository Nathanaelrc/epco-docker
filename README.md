# EPCO - Portal Corporativo (Docker)

Portal corporativo de la Empresa Portuaria Coquimbo desplegado con Docker.

## Requisitos

- Docker Engine 20.10+
- Docker Compose v2+

## Inicio Rápido

### 1. Configurar variables de entorno

```bash
cp .env.example .env
# Editar .env con tus valores
nano .env
```

### 2. Construir e iniciar los contenedores

```bash
docker compose up -d --build
```

### 3. Acceder a la aplicación

| Servicio     | URL                          | Descripción                |
|-------------|------------------------------|----------------------------|
| **EPCO**     | http://localhost:8080        | Portal corporativo         |
| **phpMyAdmin** | http://localhost:8081      | Administrar base de datos  |

### 4. Credenciales por defecto

**Aplicación web** (contraseña: `password` para todos):

| Usuario              | Rol       | Email              |
|---------------------|-----------|---------------------|
| `admin.epco`        | admin     | admin@epco.cl       |
| `soporte.ti`        | soporte   | soporte@epco.cl     |
| `tecnico.soporte`   | soporte   | tecnico@epco.cl     |
| `comunicaciones.epco` | social  | social@epco.cl      |
| `comite.etica`      | denuncia  | etica@epco.cl       |
| `usuario.demo`      | user      | usuario@epco.cl     |

**phpMyAdmin:**
- Usuario: `root` / Contraseña: valor de `DB_ROOT_PASSWORD` en `.env`
- Usuario: `epco_user` / Contraseña: valor de `DB_PASS` en `.env`

## Comandos Útiles

```bash
# Iniciar servicios
docker compose up -d

# Detener servicios
docker compose down

# Ver logs en tiempo real
docker compose logs -f

# Ver logs de un servicio específico
docker compose logs -f app
docker compose logs -f db

# Reconstruir la imagen (después de cambios en código)
docker compose up -d --build

# Reiniciar un servicio
docker compose restart app

# Entrar al contenedor PHP
docker compose exec app bash

# Entrar a MySQL
docker compose exec db mysql -u epco_user -p epco

# Ver estado de los contenedores
docker compose ps
```

## Estructura del Proyecto

```
epco-docker/
├── Dockerfile              # Imagen PHP 8.2 + Apache
├── docker-compose.yml      # Orquestación de servicios
├── .env                    # Variables de entorno (no subir a git)
├── .env.example            # Ejemplo de variables de entorno
├── .dockerignore           # Archivos excluidos del build
├── docker/
│   └── php.ini             # Configuración PHP personalizada
├── config/
│   ├── app.php             # Configuración de la aplicación
│   ├── database.php        # Conexión BD (usa env vars de Docker)
│   └── mail.php            # Configuración de correo
├── database/
│   ├── init.sql            # SQL unificado (se ejecuta al crear BD)
│   └── ...                 # Scripts SQL originales (referencia)
├── includes/               # Lógica PHP del backend
├── public/                 # DocumentRoot de Apache
│   ├── .htaccess           # Reglas de rewrite
│   ├── css/
│   ├── js/
│   ├── uploads/            # Archivos subidos (volumen persistente)
│   └── *.php               # Páginas públicas
├── logs/                   # Logs de la aplicación (volumen)
└── vendor/                 # Dependencias PHP (PHPMailer)
```

## Volúmenes Persistentes

| Volumen           | Contenedor           | Descripción                    |
|-------------------|---------------------|--------------------------------|
| `epco-db-data`    | `/var/lib/mysql`    | Datos de MySQL                 |
| `./logs`          | `/var/www/html/logs` | Logs de la aplicación         |
| `./public/uploads`| `/var/www/html/public/uploads` | Archivos subidos |

## Puertos

Configurables en `.env`:

| Variable    | Default | Descripción           |
|-------------|---------|----------------------|
| `APP_PORT`  | 8090    | Puerto web EPCO      |
| `DB_PORT`   | 3306    | Puerto MySQL         |
| `PMA_PORT`  | 8082    | Puerto phpMyAdmin    |

## Reset completo

Para reiniciar todo desde cero (elimina datos):

```bash
docker compose down -v
docker compose up -d --build
```

## Producción

Para desplegar en producción:

1. Cambiar contraseñas en `.env`
2. Configurar `APP_ENV=production` en `.env`
3. Configurar proxy reverso (nginx/traefik) con SSL
4. Deshabilitar phpMyAdmin en `docker-compose.yml` o restringir acceso
