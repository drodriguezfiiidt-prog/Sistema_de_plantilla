"""Exportacion del diagnostico a Word (.docx) con python-docx.

Replica el formato de ``generador.txt``: parrafos institucionales, titulos de
nivel 2, texto en negrita para la recomendacion, vinetas ``List Bullet`` y la
tabla ``Modelo | Articulo | Costo referencial ($)`` con estilo ``Table Grid``.
"""

from io import BytesIO

from docx import Document as DocxDocument
from docx.shared import Pt

from .email_builder import KIND_BULLETS, KIND_HEADING, KIND_TABLE

FONT_NAME = "Calibri"
FONT_SIZE = Pt(11)


def _apply_base_style(document):
    style = document.styles["Normal"]
    style.font.name = FONT_NAME
    style.font.size = FONT_SIZE


def _add_paragraph(document, block):
    paragraph = document.add_paragraph()
    lines = (block.text or "").split("\n")
    for index, line in enumerate(lines):
        if index:
            paragraph.add_run().add_break()
        run = paragraph.add_run(line)
        run.bold = bool(block.bold)
    return paragraph


def _add_table(document, block):
    table = document.add_table(rows=1, cols=len(block.columns))
    table.style = "Table Grid"
    header = table.rows[0].cells
    for index, title in enumerate(block.columns):
        header[index].text = title
    for row in block.rows:
        cells = table.add_row().cells
        for index, value in enumerate(row):
            cells[index].text = str(value)
    return table


def build_docx(diagnostic) -> bytes:
    """Devuelve el binario ``.docx`` del documento generado."""

    document = DocxDocument()
    _apply_base_style(document)

    for block in diagnostic.blocks:
        if block.kind == KIND_HEADING:
            document.add_heading(block.text, level=block.level or 2)
        elif block.kind == KIND_BULLETS:
            for item in block.items:
                document.add_paragraph(item, style="List Bullet")
        elif block.kind == KIND_TABLE:
            _add_table(document, block)
        else:
            _add_paragraph(document, block)

    buffer = BytesIO()
    document.save(buffer)
    return buffer.getvalue()
