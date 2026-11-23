"""Redaccion del documento institucional de soporte tecnico.

El documento se modela como una lista de bloques (parrafos, titulos, vinetas y
tabla tarifaria) para renderizarlo de forma identica en la vista previa HTML,
en el texto copiado al portapapeles y en el archivo Word (.docx).
"""

from dataclasses import dataclass, field
from decimal import Decimal

from django.conf import settings

from .formatting import price_cell
from .naming import normalize_institutional_text
from .pricing import QuoteResult, warranty_clause

KIND_PARAGRAPH = "paragraph"
KIND_HEADING = "heading"
KIND_BULLETS = "bullets"
KIND_TABLE = "table"


@dataclass
class Block:
    """Bloque de contenido del documento."""

    kind: str
    text: str = ""
    bold: bool = False
    level: int = 2
    items: list[str] = field(default_factory=list)
    columns: list[str] = field(default_factory=list)
    rows: list[list[str]] = field(default_factory=list)


@dataclass
class DiagnosticDocument:
    """Documento listo para previsualizar, copiar o exportar a Word."""

    subject: str
    issue: str
    blocks: list[Block]
    quote: QuoteResult
    family_name: str = ""
    ticket_number: str = ""
    serial_number: str = ""
    warranty_status: str = ""

    @property
    def lines(self):
        return self.quote.lines

    @property
    def total(self) -> Decimal:
        return self.quote.total

    @property
    def has_quote_table(self):
        return any(block.kind == KIND_TABLE for block in self.blocks)

    @property
    def plain_text(self):
        return render_plain_text(self.blocks)

    @property
    def filename(self):
        safe = "".join(
            char
            for char in (self.ticket_number or "")
            if char.isalnum() or char in ("-", "_", ".")
        )
        return f"Diagnostico_{safe or 'sin_ticket'}.docx"


def brand_settings():
    """Configuracion institucional centralizada en ``settings.GESTIOBIO``."""

    return getattr(settings, "GESTIOBIO", {}) or {}


def render_plain_table(block):
    """Tabla en texto plano alineado (para el correo copiado)."""

    rows = [list(block.columns)] + [list(row) for row in block.rows]
    if not rows:
        return ""
    width = len(block.columns)
    widths = [
        min(max(len(str(row[index])) for row in rows if index < len(row)), 60)
        for index in range(width)
    ]

    def line(row):
        cells = [str(row[index] if index < len(row) else "")[: widths[index]] for index in range(width)]
        return " | ".join(cell.ljust(widths[index]) for index, cell in enumerate(cells)).rstrip()

    header = line(block.columns)
    separator = "-+-".join("-" * size for size in widths)
    body = [line(row) for row in block.rows]
    return "\n".join([header, separator, *body])


def render_plain_text(blocks):
    """Convierte los bloques en texto plano listo para copiar."""

    chunks = []
    for block in blocks:
        if block.kind == KIND_TABLE:
            chunks.append(render_plain_table(block))
        elif block.kind == KIND_BULLETS:
            chunks.append("\n".join(f"• {item}" for item in block.items))
        else:
            chunks.append(block.text)
    return "\n\n".join(chunk for chunk in chunks if chunk).strip()




def build_document(
    *,
    family,
    ticket_number,
    serial_number,
    warranty_status,
    items,
    quote=None,
    technical_report="",
):
    """Compone el documento institucional migrado de ``generador.txt``."""

    quote = quote or QuoteResult()
    cfg = brand_settings()
    company = cfg.get("COMPANY", "Biopago C.A.")
    platform_line = cfg.get("PLATFORM_LINE", "Se envía adjunto código de oficinas.")
    signature = cfg.get("SIGNATURE", "Equipo de Soporte técnico Biopago C.A.")

    ticket = (ticket_number or "").strip()
    serial = (serial_number or "").strip().upper()
    model = family.short_name if family is not None else ""
    selected = [item for item in items if item is not None]
    issue = normalize_institutional_text(
        ", ".join(item.name for item in selected)
    ) or "las fallas reportadas"
    report = normalize_institutional_text(technical_report or "")

    subject = f"Soporte técnico {company} - Ticket #{ticket}"
    if serial:
        subject = f"{subject} - Serial {serial}"

    blocks = [
        Block(KIND_PARAGRAPH, "Estimado/a cliente:"),
        Block(
            KIND_PARAGRAPH,
            f"En respuesta a su solicitud de servicio (ticket #{ticket}), hemos "
            "realizado una evaluación exhaustiva de su equipo con serial "
            f"{serial}.",
        ),
        Block(KIND_PARAGRAPH, f"Hemos determinado que presenta falla con: {issue}."),
    ]

    clause = warranty_clause(warranty_status)
    if clause:
        blocks.append(Block(KIND_PARAGRAPH, clause))

    blocks.append(Block(KIND_HEADING, "Propuesta de solución:"))
    blocks.append(
        Block(
            KIND_PARAGRAPH,
            "Para restablecer el funcionamiento óptimo de su equipo, recomendamos "
            f"el reemplazo de: {issue}.",
            bold=True,
        )
    )

    if quote.quote_allowed and quote.has_lines:
        blocks.append(
            Block(
                KIND_PARAGRAPH,
                f"Contamos con la pieza original para su modelo {model} "
                "disponible en nuestro inventario. Para ejecutar este "
                "procedimiento, a continuación, dejamos un precio aproximado de "
                "esta reparación:",
            )
        )
        blocks.append(
            Block(
                KIND_TABLE,
                columns=["Modelo", "Artículo", "Costo referencial ($)"],
                rows=[
                    [line.model, line.article, price_cell(line.price)]
                    for line in quote.lines
                ],
            )
        )
    elif quote.quote_allowed:
        blocks.append(
            Block(
                KIND_PARAGRAPH,
                "No se registraron repuestos ni servicios para incluir en la "
                "tabla tarifaria referencial.",
            )
        )
    else:
        channel = quote.channel or cfg.get("ADMINISTRATIVE_CHANNEL", "")
        message = (
            f"Para esta familia de equipos ({model}) la cotización formal se "
            "gestiona directamente con el departamento de administración"
        )
        message += f" a través del canal {channel}." if channel else "."
        blocks.append(Block(KIND_PARAGRAPH, message))

    blocks.extend(
        [
            Block(
                KIND_PARAGRAPH,
                "Con base en la información hasta este punto suministrada:",
            ),
            Block(
                KIND_PARAGRAPH,
                "Nuestro departamento de administración se pondrá en contacto con "
                "usted a la brevedad, para enviarle una cotización formal "
                "detallada, la cual incluirá:",
            ),
            Block(
                KIND_BULLETS,
                items=[
                    f"Costo del repuesto original: {issue} compatible con su "
                    f"modelo {model}.",
                    "Gastos adicionales (si aplican): Cualquier otro costo "
                    "asociado a la reparación, como componentes adicionales o "
                    "servicios especializados.",
                ],
            ),
            Block(
                KIND_PARAGRAPH,
                "Si decide no continuar con la reparación, le agradeceríamos que "
                "nos lo informara por este medio para proporcionarle el "
                "procedimiento de retiro de su equipo.",
            ),
            Block(
                KIND_PARAGRAPH,
                "Le recordamos que para la facturación es indispensable que nos "
                "facilite su RIF.",
            ),
            Block(KIND_PARAGRAPH, platform_line),
            Block(KIND_HEADING, "Diagnóstico:"),
            Block(KIND_PARAGRAPH, f"Falla diagnosticada: {issue}"),
            Block(KIND_HEADING, "Informe técnico:"),
            Block(
                KIND_PARAGRAPH,
                report or "Sin observaciones adicionales registradas.",
            ),
            Block(KIND_PARAGRAPH, "Agradecemos su comprensión."),
            Block(KIND_PARAGRAPH, f"Reciba nuestros saludos.\n{signature}"),
        ]
    )

    return DiagnosticDocument(
        subject=subject.strip()[:255],
        issue=issue,
        blocks=blocks,
        quote=quote,
        family_name=family.name if family is not None else "",
        ticket_number=ticket,
        serial_number=serial,
        warranty_status=warranty_status or "",
    )

