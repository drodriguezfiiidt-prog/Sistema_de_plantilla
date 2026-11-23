"""Modelos de datos de GestioBioV3.

Migración del script de escritorio ``generador.txt``: el catálogo de fallas y
repuestos es dinámico y cada familia de equipos define qué artículos admite.
"""

from decimal import Decimal

from django.core.validators import MinValueValidator
from django.db import models
from django.urls import reverse


class EquipmentFamily(models.Model):
    """Familia o modelo de equipo atendido por el soporte técnico."""

    name = models.CharField("nombre", max_length=80, unique=True)
    prefix = models.CharField(
        "prefijo de serial",
        max_length=20,
        help_text="Prefijo que identifica la familia. Ejemplo: V10, FP-, G62.",
    )
    allows_quotes = models.BooleanField(
        "permite tabla de cotización",
        default=True,
        help_text=(
            "Si se desactiva, el documento no incluye tabla tarifaria y remite "
            "al canal administrativo de la familia."
        ),
    )
    support_channel = models.CharField(
        "canal de atención",
        max_length=160,
        blank=True,
        help_text="Canal exigido cuando la familia no permite cotización.",
    )
    is_active = models.BooleanField("activa", default=True)
    created_at = models.DateTimeField("creada", auto_now_add=True)
    updated_at = models.DateTimeField("actualizada", auto_now=True)

    class Meta:
        verbose_name = "familia de equipos"
        verbose_name_plural = "familias de equipos"
        ordering = ["name"]

    def __str__(self):
        return self.name

    def get_absolute_url(self):
        return reverse("email_generator:family_edit", args=[self.pk])

    @property
    def short_name(self):
        """Nombre corto usado en la columna Modelo de la tabla tarifaria."""

        base = (self.name or "").split("/")[0].strip()
        return base or self.name

    def matches_serial(self, serial):
        prefix = (self.prefix or "").strip().upper()
        serial = (serial or "").strip().upper()
        return bool(prefix) and serial.startswith(prefix)

    @classmethod
    def detect(cls, serial):
        """Autodetecta la familia a partir del prefijo del serial."""

        serial = (serial or "").strip().upper()
        if not serial:
            return None
        candidates = [
            family
            for family in cls.objects.filter(is_active=True)
            if family.matches_serial(serial)
        ]
        if not candidates:
            return None
        return max(candidates, key=lambda family: len(family.prefix.strip()))


class ItemCatalog(models.Model):
    """Falla, repuesto o servicio con precio referencial en USD."""

    name = models.CharField("nombre del artículo", max_length=160, unique=True)
    price = models.DecimalField(
        "precio referencial en USD",
        max_digits=10,
        decimal_places=2,
        validators=[MinValueValidator(Decimal("0.00"))],
    )
    is_mandatory_base = models.BooleanField(
        "partida base obligatoria",
        default=False,
        help_text=(
            "Las partidas base se agregan automáticamente a la tabla tarifaria "
            "y no se muestran como opción en el formulario."
        ),
    )
    is_active = models.BooleanField(
        "habilitado",
        default=True,
        help_text="Si se desactiva, deja de aparecer en los formularios de diagnóstico.",
    )
    allowed_families = models.ManyToManyField(
        EquipmentFamily,
        related_name="items",
        verbose_name="familias compatibles",
        blank=True,
        help_text=(
            "Familias de equipos para las cuales está disponible este artículo. "
            "Sin familias asignadas no aparece en el formulario."
        ),
    )
    created_at = models.DateTimeField("creado", auto_now_add=True)
    updated_at = models.DateTimeField("actualizado", auto_now=True)

    class Meta:
        verbose_name = "artículo del catálogo"
        verbose_name_plural = "catálogo de artículos"
        ordering = ["name"]

    def __str__(self):
        return f"{self.name} (${self.price})"

    def get_absolute_url(self):
        return reverse("email_generator:catalog_edit", args=[self.pk])

    def display_name(self, family=None):
        """Nombre oficial usado en la tabla: ``{artículo} {modelo}``.

        Evita duplicar el modelo cuando el nombre del artículo ya lo contiene.
        """

        name = (self.name or "").strip()
        if family is None:
            return name
        model = family.short_name
        if not model or model.lower() in name.lower():
            return name
        return f"{name} {model}"

    def is_available_for(self, family):
        """Disponibilidad efectiva: activo y compatible con la familia."""

        if family is None or not self.is_active:
            return False
        return self.allowed_families.filter(pk=family.pk).exists()

    @classmethod
    def selectable_queryset(cls):
        """Artículos que el operador puede marcar en el formulario."""

        return (
            cls.objects.filter(is_active=True, is_mandatory_base=False)
            .prefetch_related("allowed_families")
            .order_by("price", "name")
        )

    @classmethod
    def for_family(cls, family):
        """Artículos activos y compatibles con la familia indicada."""

        if family is None:
            return cls.objects.none()
        return cls.selectable_queryset().filter(allowed_families=family)

    @classmethod
    def base_lines_for(cls, family):
        """Partidas base obligatorias activas y compatibles con la familia."""

        if family is None:
            return cls.objects.none()
        return cls.objects.filter(
            is_active=True,
            is_mandatory_base=True,
            allowed_families=family,
        ).order_by("price", "name")


class DiagnosticTicket(models.Model):
    """Registro de evaluaciones técnicas generadas desde el asistente."""

    class Warranty(models.TextChoices):
        VIGENTE = "Vigente", "Vigente"
        VENCIDA = "Vencida", "Vencida"
        INVALIDADA = "Invalidada", "Invalidada"

    ticket_number = models.CharField("número de ticket", max_length=60, db_index=True)
    serial_number = models.CharField("serial del equipo", max_length=80, db_index=True)
    family = models.ForeignKey(
        EquipmentFamily,
        verbose_name="familia detectada",
        on_delete=models.PROTECT,
        related_name="tickets",
        null=True,
        blank=True,
    )
    warranty_status = models.CharField(
        "estado de garantía",
        max_length=20,
        choices=Warranty.choices,
        default=Warranty.VENCIDA,
    )
    selected_items = models.ManyToManyField(
        ItemCatalog,
        verbose_name="fallas y repuestos",
        related_name="tickets",
        blank=True,
    )
    technical_report = models.TextField("informe técnico", blank=True)
    total_reference = models.DecimalField(
        "total referencial (USD)",
        max_digits=10,
        decimal_places=2,
        default=Decimal("0.00"),
        validators=[MinValueValidator(Decimal("0.00"))],
    )
    document_body = models.TextField("documento generado", blank=True)
    created_by = models.ForeignKey(
        "auth.User",
        verbose_name="registrado por",
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="diagnostic_tickets",
    )
    created_at = models.DateTimeField("creado", auto_now_add=True)

    class Meta:
        verbose_name = "evaluación técnica"
        verbose_name_plural = "evaluaciones técnicas"
        ordering = ["-created_at"]
        indexes = [
            models.Index(fields=["ticket_number"]),
            models.Index(fields=["serial_number"]),
        ]

    def __str__(self):
        return f"Ticket #{self.ticket_number} ({self.serial_number})"

    def get_absolute_url(self):
        return reverse("email_generator:ticket_detail", args=[self.pk])

    @property
    def items_summary(self):
        return ", ".join(item.name for item in self.selected_items.all())

    @property
    def document_filename(self):
        safe = "".join(
            char
            for char in (self.ticket_number or "")
            if char.isalnum() or char in ("-", "_", ".")
        )
        return f"Diagnostico_{safe or 'sin_ticket'}.docx"
