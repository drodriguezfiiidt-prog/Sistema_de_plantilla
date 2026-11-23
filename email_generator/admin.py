"""Configuración del panel de administración de Django."""

from django.contrib import admin

from .models import DiagnosticTicket, EquipmentFamily, ItemCatalog


@admin.register(EquipmentFamily)
class EquipmentFamilyAdmin(admin.ModelAdmin):
    list_display = ("name", "prefix", "allows_quotes", "support_channel", "is_active")
    list_editable = ("allows_quotes", "is_active")
    list_filter = ("allows_quotes", "is_active")
    search_fields = ("name", "prefix")


@admin.register(ItemCatalog)
class ItemCatalogAdmin(admin.ModelAdmin):
    list_display = (
        "name",
        "price",
        "is_mandatory_base",
        "is_active",
        "families_display",
    )
    list_editable = ("price", "is_active")
    list_filter = ("is_mandatory_base", "is_active", "allowed_families")
    search_fields = ("name",)
    filter_horizontal = ("allowed_families",)

    @admin.display(description="familias compatibles")
    def families_display(self, obj):
        names = list(obj.allowed_families.values_list("name", flat=True))
        return ", ".join(names) or "—"


@admin.register(DiagnosticTicket)
class DiagnosticTicketAdmin(admin.ModelAdmin):
    list_display = (
        "ticket_number",
        "serial_number",
        "family",
        "warranty_status",
        "total_reference",
        "created_at",
    )
    list_filter = ("warranty_status", "family", "created_at")
    search_fields = ("ticket_number", "serial_number")
    filter_horizontal = ("selected_items",)
    readonly_fields = ("total_reference", "document_body", "created_at")
