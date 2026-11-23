"""Reglas comerciales y tarifarias de GestioBioV3.

Replica la logica de ``generador.txt``: la tabla inicia siempre con la partida
base obligatoria ("Reemplazo de partes y piezas de dispositivos biometricos")
y luego agrega cada falla seleccionada con su precio referencial en USD.
"""

from dataclasses import dataclass, field
from decimal import Decimal

from django.conf import settings

from ..models import ItemCatalog

WARRANTY_CLAUSES = {
    "Vencida": (
        "Se le recuerda que su equipo ya no cumple con los lapsos de tiempo "
        "establecidos para el cumplimiento de garantía."
    ),
    "Invalidada": (
        "Se le informa que su equipo no cumple con las cláusulas establecidas "
        "para la ejecución de la garantía."
    ),
    "Vigente": "Su equipo se encuentra dentro del periodo de garantía vigente.",
}


@dataclass(frozen=True)
class QuoteLine:
    """Fila de la tabla tarifaria: Modelo | Articulo | Costo referencial ($)."""

    model: str
    article: str
    price: Decimal
    is_base: bool = False
    pk: int | None = None


@dataclass
class QuoteResult:
    """Resultado del motor tarifario."""

    lines: list[QuoteLine] = field(default_factory=list)
    total: Decimal = Decimal("0.00")
    quote_allowed: bool = False
    channel: str = ""
    notes: list[str] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)
    errors: list[str] = field(default_factory=list)

    @property
    def has_lines(self):
        return bool(self.lines)

    @property
    def base_lines(self):
        return [line for line in self.lines if line.is_base]

    @property
    def specific_lines(self):
        return [line for line in self.lines if not line.is_base]


def warranty_clause(status):
    """Devuelve la clausula institucional segun el estado de garantia."""

    return WARRANTY_CLAUSES.get((status or "").strip(), "")


def administrative_channel(family=None):
    """Canal institucional aplicable a la familia evaluada."""

    if family is not None and (family.support_channel or "").strip():
        return family.support_channel.strip()
    return (getattr(settings, "GESTIOBIO", {}) or {}).get("ADMINISTRATIVE_CHANNEL", "")



def build_quote(*, family, items=()):
    """Construye la tabla tarifaria de la evaluacion.

    Reglas:
      * La partida base obligatoria se inserta automaticamente.
      * Solo se incluyen articulos activos y compatibles con la familia.
      * Las familias con ``allows_quotes=False`` no reciben tabla tarifaria y se
        remiten al canal administrativo.
    """

    result = QuoteResult()
    if family is None:
        result.errors.append(
            "Debe indicar la familia del equipo para calcular los costos referenciales."
        )
        return result

    result.quote_allowed = bool(family.allows_quotes)
    result.channel = administrative_channel(family)
    selected = [item for item in items if item is not None]

    if not result.quote_allowed:
        result.warnings.append(
            f"La familia {family.name} no permite tabla de cotizacion en este documento."
        )
        if result.channel:
            result.notes.append(
                "La cotizacion formal se gestiona a traves del canal "
                f"{result.channel}."
            )
        return result

    seen = set()
    for base in ItemCatalog.base_lines_for(family):
        if base.pk in seen:
            continue
        seen.add(base.pk)
        result.lines.append(
            QuoteLine(
                model=family.short_name,
                article=base.name,
                price=base.price,
                is_base=True,
                pk=base.pk,
            )
        )

    for item in selected:
        if item.pk in seen:
            continue
        if not item.is_active:
            result.errors.append(
                f"El articulo {item.name} esta deshabilitado en el catalogo."
            )
            continue
        if not item.is_available_for(family):
            result.errors.append(
                f"El articulo {item.name} no esta habilitado para la familia "
                f"{family.name}."
            )
            continue
        seen.add(item.pk)
        result.lines.append(
            QuoteLine(
                model=family.short_name,
                article=item.display_name(family),
                price=item.price,
                pk=item.pk,
            )
        )

    result.total = sum((line.price for line in result.lines), Decimal("0.00"))

    if not selected:
        result.warnings.append("No se seleccionaron fallas ni repuestos para cotizar.")
    if not any(line.is_base for line in result.lines):
        result.warnings.append(
            "La familia no tiene partida base activa asignada: la tabla se emite "
            "solo con los repuestos seleccionados."
        )
    return result

