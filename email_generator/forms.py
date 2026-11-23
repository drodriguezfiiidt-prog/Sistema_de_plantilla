"""Formularios del asistente de soporte y del panel administrativo."""

from django import forms

from .models import DiagnosticTicket, EquipmentFamily, ItemCatalog
from .services.formatting import price_cell


class StyledFormMixin:
    """Aplica las clases CSS del sistema de diseno a todos los campos."""

    def _apply_styles(self):
        for field in self.fields.values():
            widget = field.widget
            if isinstance(widget, forms.CheckboxSelectMultiple):
                widget.attrs.setdefault("class", "checkbox-grid")
            elif isinstance(widget, forms.CheckboxInput):
                widget.attrs.setdefault("class", "form-check")
            else:
                widget.attrs.setdefault("class", "form-input")


class PricedModelMultipleChoiceField(forms.ModelMultipleChoiceField):
    """Checkboxes de fallas con el precio referencial en la etiqueta."""

    def label_from_instance(self, obj):
        return f"{obj.name} (${price_cell(obj.price)})"



class DiagnosticForm(StyledFormMixin, forms.Form):
    """Captura del ticket, serial, fallas, garantia e informe tecnico."""

    ticket_number = forms.CharField(
        label="Número de ticket",
        max_length=60,
        widget=forms.TextInput(
            attrs={
                "placeholder": "35212348",
                "autocomplete": "off",
                "data-ticket-input": "true",
            }
        ),
    )
    serial_number = forms.CharField(
        label="Serial del equipo",
        max_length=80,
        widget=forms.TextInput(
            attrs={
                "placeholder": "V10-000000",
                "autocomplete": "off",
                "data-serial-input": "true",
            }
        ),
        help_text="El prefijo del serial autodetecta la familia del equipo.",
    )
    family = forms.ModelChoiceField(
        label="Familia detectada",
        queryset=EquipmentFamily.objects.filter(is_active=True),
        required=False,
        empty_label="Autodetectar por serial",
    )
    items = PricedModelMultipleChoiceField(
        label="Fallas y repuestos diagnosticados",
        queryset=ItemCatalog.objects.none(),
        widget=forms.CheckboxSelectMultiple,
        error_messages={
            "required": "Marque al menos una falla o repuesto para el diagnóstico."
        },
    )
    warranty_status = forms.ChoiceField(
        label="Estado de garantía",
        choices=DiagnosticTicket.Warranty.choices,
        initial=DiagnosticTicket.Warranty.VENCIDA,
    )
    technical_report = forms.CharField(
        label="Informe técnico",
        required=False,
        widget=forms.Textarea(
            attrs={
                "rows": 5,
                "placeholder": (
                    "Detalle libre del diagnóstico: pruebas realizadas, "
                    "componentes afectados y observaciones."
                ),
            }
        ),
        help_text="Se publica con tratamiento de usted y glosario institucional.",
    )

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.family_obj = self._resolve_family()
        queryset = (
            ItemCatalog.for_family(self.family_obj)
            if self.family_obj
            else ItemCatalog.selectable_queryset()
        )
        self.fields["items"].queryset = queryset
        if self.family_obj:
            self.fields["family"].initial = self.family_obj.pk
            prefix = (self.family_obj.prefix or "").strip()
            if prefix:
                self.fields["serial_number"].widget.attrs.setdefault(
                    "placeholder", f"{prefix}000000"
                )
        self._apply_styles()

    def _resolve_family(self):
        if self.is_bound:
            value = self.data.get(self.add_prefix("family"))
        else:
            value = self.initial.get("family")
        if hasattr(value, "pk"):
            return value
        if value:
            family = EquipmentFamily.objects.filter(pk=value, is_active=True).first()
            if family is not None:
                return family
        serial = (
            self.data.get(self.add_prefix("serial_number"))
            if self.is_bound
            else self.initial.get("serial_number")
        )
        return EquipmentFamily.detect(serial)

    def clean_ticket_number(self):
        return (self.cleaned_data.get("ticket_number") or "").strip()

    def clean_serial_number(self):
        return (self.cleaned_data.get("serial_number") or "").strip().upper()

    def clean(self):
        data = super().clean()
        serial = data.get("serial_number") or ""
        family = data.get("family") or self.family_obj
        items = list(data.get("items") or [])

        if family is None:
            self.add_error(
                "serial_number",
                "No se reconoce el prefijo del serial. Verifique el número o "
                "seleccione manualmente la familia del equipo.",
            )
            return data

        data["family"] = family
        if serial and not family.matches_serial(serial):
            self.add_error(
                "serial_number",
                f"El serial no corresponde al prefijo {family.prefix} de la "
                f"familia {family.name}.",
            )

        incompatible = [
            item.name for item in items if not item.is_available_for(family)
        ]
        if incompatible:
            self.add_error(
                "items",
                f"Artículos no habilitados para {family.name}: "
                + ", ".join(incompatible)
                + ".",
            )
        return data


class FamilyForm(StyledFormMixin, forms.ModelForm):
    """Alta y edicion de familias de equipos (prefijo y cotizacion)."""

    class Meta:
        model = EquipmentFamily
        fields = ["name", "prefix", "allows_quotes", "support_channel", "is_active"]

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._apply_styles()


class ItemCatalogForm(StyledFormMixin, forms.ModelForm):
    """Alta y edicion de repuestos/fallas con precio y compatibilidad."""

    class Meta:
        model = ItemCatalog
        fields = ["name", "price", "is_mandatory_base", "is_active", "allowed_families"]
        widgets = {
            "price": forms.NumberInput(attrs={"step": "0.01", "min": "0"}),
            "allowed_families": forms.CheckboxSelectMultiple,
        }
        labels = {
            "is_mandatory_base": "Partida base obligatoria",
            "is_active": "Habilitado en el formulario",
            "allowed_families": "Familias compatibles",
        }

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._apply_styles()


class PriceForm(StyledFormMixin, forms.ModelForm):
    """Edicion rapida del precio referencial desde el panel."""

    class Meta:
        model = ItemCatalog
        fields = ["price"]
        widgets = {"price": forms.NumberInput(attrs={"step": "0.01", "min": "0"})}

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._apply_styles()

