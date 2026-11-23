"""Orquestacion de la evaluacion: validaciones, tarifario y redaccion."""

from dataclasses import dataclass, field

from ..models import DiagnosticTicket
from .email_builder import DiagnosticDocument, build_document
from .naming import (
    find_glossary_issues,
    find_informal_terms,
    normalize_institutional_text,
)
from .pricing import QuoteResult, build_quote


@dataclass
class ComplianceReport:
    """Hallazgos de la validacion del formulario."""

    errors: list[str] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)
    infos: list[str] = field(default_factory=list)

    @property
    def is_valid(self):
        return not self.errors

    @property
    def label(self):
        if self.errors:
            return "Con errores"
        if self.warnings:
            return "Con advertencias"
        return "Validado"

    @property
    def tone(self):
        if self.errors:
            return "danger"
        if self.warnings:
            return "warning"
        return "success"


def build_snapshot(cleaned, quote: QuoteResult, report: ComplianceReport):
    """Instantanea serializable del diagnostico (auditoria / JSON)."""

    family = cleaned.get("family")
    items = [item for item in (cleaned.get("items") or []) if item is not None]
    return {
        "ticket_number": (cleaned.get("ticket_number") or "").strip(),
        "serial_number": (cleaned.get("serial_number") or "").strip().upper(),
        "family_id": getattr(family, "pk", None),
        "family": getattr(family, "name", ""),
        "warranty_status": cleaned.get("warranty_status") or "",
        "technical_report": cleaned.get("technical_report") or "",
        "items": [
            {"id": item.pk, "name": item.name, "price": str(item.price)}
            for item in items
        ],
        "quote_allowed": quote.quote_allowed,
        "channel": quote.channel,
        "total": str(quote.total),
        "lines": [
            {
                "model": line.model,
                "article": line.article,
                "price": str(line.price),
                "is_base": line.is_base,
            }
            for line in quote.lines
        ],
        "warnings": list(report.warnings),
        "infos": list(report.infos),
    }


def run_generation(cleaned):
    """Devuelve el documento redactado, el tarifario y el informe."""

    family = cleaned.get("family")
    items = [item for item in (cleaned.get("items") or []) if item is not None]
    ticket = (cleaned.get("ticket_number") or "").strip()
    serial = (cleaned.get("serial_number") or "").strip().upper()
    warranty = (cleaned.get("warranty_status") or "").strip()
    report_text = cleaned.get("technical_report") or ""

    quote = build_quote(family=family, items=items)

    report = ComplianceReport(
        errors=list(quote.errors),
        warnings=list(quote.warnings),
        infos=list(quote.notes),
    )

    if not ticket:
        report.errors.append("Debe indicar el número de ticket.")
    if not serial:
        report.errors.append("Debe indicar el serial del equipo.")
    if family is None:
        report.errors.append(
            "No se pudo determinar la familia del equipo: verifique el prefijo del serial."
        )
    elif serial and not family.matches_serial(serial):
        report.warnings.append(
            f"El serial {serial} no coincide con el prefijo {family.prefix} "
            f"registrado para la familia {family.name}."
        )
    if not items:
        report.errors.append("Seleccione al menos una falla o repuesto.")

    if not report_text.strip():
        report.infos.append(
            "No se registró informe técnico: el documento indica «Sin observaciones "
            "adicionales registradas»."
        )
    else:
        informal = find_informal_terms(report_text)
        if informal:
            report.warnings.append(
                "Se detectó tratamiento informal (tuteo) en el informe técnico: "
                + ", ".join(informal)
                + ". El texto se publica con tratamiento de usted; corrija la redacción."
            )

        glossary_issues = find_glossary_issues(report_text)
        if glossary_issues:
            report.infos.append(
                "Se aplicó el glosario institucional sobre: "
                + ", ".join(glossary_issues)
                + "."
            )

    if quote.channel:
        report.infos.append(f"Canal institucional: {quote.channel}.")
    if quote.has_lines:
        report.infos.append(
            f"Tabla tarifaria con {len(quote.lines)} partida(s) para un total "
            f"referencial de {quote.total} USD."
        )

    document = build_document(
        family=family,
        ticket_number=ticket,
        serial_number=serial,
        warranty_status=warranty,
        items=items,
        quote=quote,
        technical_report=normalize_institutional_text(report_text),
    )

    return GenerationResult(document=document, quote=quote, report=report)


def register_ticket(cleaned, result, user=None):
    """Persiste la evaluacion en el historial (``DiagnosticTicket``)."""

    family = cleaned.get("family")
    ticket = DiagnosticTicket.objects.create(
        ticket_number=(cleaned.get("ticket_number") or "").strip(),
        serial_number=(cleaned.get("serial_number") or "").strip().upper(),
        family=family,
        warranty_status=cleaned.get("warranty_status")
        or DiagnosticTicket.Warranty.VENCIDA,
        technical_report=cleaned.get("technical_report") or "",
        total_reference=result.quote.total,
        document_body=result.document.plain_text,
        created_by=user if getattr(user, "is_authenticated", False) else None,
    )
    ticket.selected_items.set(
        [item for item in (cleaned.get("items") or []) if item is not None]
    )
    return ticket


@dataclass
class GenerationResult:
    """Agrupa el documento, el tarifario y el informe de cumplimiento."""

    document: DiagnosticDocument
    quote: QuoteResult
    report: ComplianceReport

    @property
    def total(self):
        return self.quote.total

    @property
    def plain_text(self):
        return self.document.plain_text

