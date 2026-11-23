"""Rutas de la app ``email_generator``."""

from django.urls import path

from . import views

app_name = "email_generator"

urlpatterns = [
    path("", views.generator, name="generator"),
    path("vista-previa/", views.preview, name="preview"),
    path("descargar/", views.download_docx, name="download_docx"),
    path("api/contexto/", views.api_context, name="api_context"),
    path("historial/", views.history, name="history"),
    path("historial/<int:pk>/", views.ticket_detail, name="ticket_detail"),
    path("historial/<int:pk>/docx/", views.ticket_docx, name="ticket_docx"),
    path("gestion/", views.management, name="management"),
    path("gestion/articulos/nuevo/", views.catalog_create, name="catalog_create"),
    path("gestion/articulos/<int:pk>/", views.catalog_edit, name="catalog_edit"),
    path("gestion/articulos/<int:pk>/precio/", views.catalog_price, name="catalog_price"),
    path(
        "gestion/articulos/<int:pk>/alternar/",
        views.catalog_toggle,
        name="catalog_toggle",
    ),
    path("gestion/familias/nueva/", views.family_create, name="family_create"),
    path("gestion/familias/<int:pk>/", views.family_edit, name="family_edit"),
]
