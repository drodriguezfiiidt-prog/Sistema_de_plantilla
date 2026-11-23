"""Punto de entrada ASGI del proyecto GestioBioV3."""

import os

from django.core.asgi import get_asgi_application

os.environ.setdefault("DJANGO_SETTINGS_MODULE", "gestiobio.settings")

application = get_asgi_application()
