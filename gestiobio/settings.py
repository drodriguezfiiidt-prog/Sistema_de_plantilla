"""Configuracion del proyecto GestioBioV3."""

from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent

# ADVERTENCIA: clave de desarrollo. En produccion debe definirse por variable
# de entorno (GESTIOBIO_SECRET_KEY) y mantenerla fuera del repositorio.
SECRET_KEY = "django-insecure-gestiobio-v3-cambiar-en-produccion"

DEBUG = True

ALLOWED_HOSTS = ["*"]

INSTALLED_APPS = [
    "django.contrib.admin",
    "django.contrib.auth",
    "django.contrib.contenttypes",
    "django.contrib.sessions",
    "django.contrib.messages",
    "django.contrib.staticfiles",
    "django.contrib.humanize",
    "email_generator",
]

MIDDLEWARE = [
    "django.middleware.security.SecurityMiddleware",
    "django.contrib.sessions.middleware.SessionMiddleware",
    "django.middleware.common.CommonMiddleware",
    "django.middleware.csrf.CsrfViewMiddleware",
    "django.contrib.auth.middleware.AuthenticationMiddleware",
    "django.contrib.messages.middleware.MessageMiddleware",
    "django.middleware.clickjacking.XFrameOptionsMiddleware",
]

ROOT_URLCONF = "gestiobio.urls"

TEMPLATES = [
    {
        "BACKEND": "django.template.backends.django.DjangoTemplates",
        "DIRS": [BASE_DIR / "templates"],
        "APP_DIRS": True,
        "OPTIONS": {
            "context_processors": [
                "django.template.context_processors.debug",
                "django.template.context_processors.request",
                "django.contrib.auth.context_processors.auth",
                "django.contrib.messages.context_processors.messages",
            ],
        },
    },
]

WSGI_APPLICATION = "gestiobio.wsgi.application"
ASGI_APPLICATION = "gestiobio.asgi.application"

DATABASES = {
    "default": {
        "ENGINE": "django.db.backends.sqlite3",
        "NAME": BASE_DIR / "db.sqlite3",
    }
}

AUTH_PASSWORD_VALIDATORS = [
    {
        "NAME": "django.contrib.auth.password_validation.UserAttributeSimilarityValidator",
    },
    {
        "NAME": "django.contrib.auth.password_validation.MinimumLengthValidator",
    },
    {
        "NAME": "django.contrib.auth.password_validation.CommonPasswordValidator",
    },
    {
        "NAME": "django.contrib.auth.password_validation.NumericPasswordValidator",
    },
]

LANGUAGE_CODE = "es"
TIME_ZONE = "America/Caracas"
USE_I18N = True
USE_TZ = True

STATIC_URL = "static/"
STATICFILES_DIRS = [BASE_DIR / "static"]
STATIC_ROOT = BASE_DIR / "staticfiles"

DEFAULT_AUTO_FIELD = "django.db.models.BigAutoField"

# Directorio de fixtures compartidos (fixtures/initial_data.json).
FIXTURE_DIRS = [BASE_DIR / "fixtures"]

LOGIN_URL = "/admin/login/"
LOGIN_REDIRECT_URL = "/gestion/"
LOGOUT_REDIRECT_URL = "/"

# ---------------------------------------------------------------------------
# Parametros institucionales de GestioBioV3 (editables sin tocar el codigo).
# ---------------------------------------------------------------------------
GESTIOBIO = {
    "BRAND_NAME": "GestioBioV3",
    "BRAND_SUBTITLE": "Soporte Postventa",
    "COMPANY": "Biopago C.A.",
    "SIGNATURE": "Equipo de Soporte técnico Biopago C.A.",
    "PLATFORM_LINE": "Se envía adjunto código de oficinas.",
    "CHANNELS": {
        "PICKUP": (
            "Retiro en Parque Cristal, Caracas, previa confirmación con el "
            "Equipo de Soporte técnico."
        ),
        "PARCEL": (
            "Encomiendas: el envío se realiza a nivel nacional y el costo del "
            "flete no está incluido en la cotización."
        ),
        "BANK_UNSUBSCRIBE_EMAIL": "servicio_biopago@banvenez.com",
        "SALES_EMAIL": "ventas@biopagobdv.com",
    },
    "ADMINISTRATIVE_CHANNEL": "administracion.estaciones@biopagopdv.com.ve",
    "CURRENCY": "USD",
    "QUOTE_STATUS": "A cotizar",
}
