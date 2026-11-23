"""Redaccion corporativa: glosario obligatorio y control de tratamiento."""

import re

# Terminos normalizados automaticamente (glosario institucional).
CARCASA_RE = re.compile(r"\b(carcasas?|carcazas?)\b", re.IGNORECASE)
RIF_RE = re.compile(r"\brif\b", re.IGNORECASE)
USB_RE = re.compile(r"\busb\b", re.IGNORECASE)
ARTICULO_RE = re.compile(r"\b(articulos?|artículos?)\b", re.IGNORECASE)
REEMPLAZO_RE = re.compile(r"\b(remplazos?|reemplasos?|remplasos?)\b", re.IGNORECASE)
BIOMETRICO_RE = re.compile(r"\b(biometrico|biometricos|biometria)\b", re.IGNORECASE)

BIOMETRICO_MAP = {
    "biometrico": "biométrico",
    "biometricos": "biométricos",
    "biometria": "biometría",
}

INFORMAL_TERMS = (
    "tú",
    "tu",
    "tus",
    "tuyo",
    "tuya",
    "tienes",
    "puedes",
    "debes",
    "necesitas",
    "quieres",
    "sabes",
    "haz",
    "envíame",
    "dime",
    "mira",
    "recuerda",
)


def _articulo(match):
    word = match.group(0)
    plural = word.lower().endswith("s")
    if plural:
        return "Artículos" if word[:1].isupper() else "artículos"
    return "Artículo" if word[:1].isupper() else "artículo"


def _reemplazo(match):
    word = match.group(0)
    plural = word.lower().endswith("s")
    if plural:
        return "Reemplazos" if word[:1].isupper() else "reemplazos"
    return "Reemplazo" if word[:1].isupper() else "reemplazo"


def _biometrico(match):
    word = match.group(0)
    replacement = BIOMETRICO_MAP.get(word.lower(), word)
    return replacement[:1].upper() + replacement[1:] if word[:1].isupper() else replacement


def normalize_institutional_text(text: str) -> str:
    """Aplica el glosario y limpia el espaciado del texto institucional."""

    if not text:
        return ""
    normalized = text.replace("\t", " ")
    normalized = CARCASA_RE.sub("Case", normalized)
    normalized = RIF_RE.sub("RIF", normalized)
    normalized = USB_RE.sub("USB", normalized)
    normalized = ARTICULO_RE.sub(_articulo, normalized)
    normalized = REEMPLAZO_RE.sub(_reemplazo, normalized)
    normalized = BIOMETRICO_RE.sub(_biometrico, normalized)
    normalized = re.sub(r"[ ]{2,}", " ", normalized)
    normalized = "\n".join(line.rstrip() for line in normalized.splitlines())
    normalized = re.sub(r"\n{3,}", "\n\n", normalized)
    return normalized.strip()


def find_informal_terms(text: str) -> list[str]:
    """Detecta palabras de tratamiento informal (tuteo) en un texto."""

    if not text:
        return []
    lowered = text.lower()
    found = [
        term
        for term in INFORMAL_TERMS
        if re.search(rf"(?<![a-záéíóúñ]){re.escape(term)}(?![a-záéíóúñ])", lowered)
    ]
    return sorted(set(found))


def find_glossary_issues(text: str) -> list[str]:
    """Detecta terminos que el glosario prohibe o exige corregir."""

    issues = []
    if not text:
        return issues
    if re.search(r"\bcarcasas?\b", text, re.IGNORECASE):
        issues.append("carcasa")
    if re.search(r"\brif\b", text) and "RIF" not in text:
        issues.append("rif")
    if re.search(r"\busb\b", text) and "USB" not in text:
        issues.append("usb")
    if re.search(r"\barticulos?\b", text, re.IGNORECASE):
        issues.append("articulo")
    if re.search(r"\b(remplazo|reemplaso)\b", text, re.IGNORECASE):
        issues.append("reemplazo")
    return issues
