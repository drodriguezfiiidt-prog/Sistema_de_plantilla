"""Pruebas automatizadas de GestioBioV3.

Ejecucion::

    py manage.py test email_generator -v 2

La suite crea su propio catalogo en la base de datos de pruebas, por lo que no
depende de ``fixtures/initial_catalog.json`` ni de datos reales.
"""

from decimal import Decimal
from io import BytesIO

from django.contrib.auth import get_user_model
from django.test import TestCase
from django.urls import reverse

from .models import DiagnosticTicket, EquipmentFamily, ItemCatalog
from .services.docx_export import build_docx
from .services.email_builder import build_document
from .services.formatting import money, price_cell
from .services.pipeline import run_generation
from .services.pricing import build_quote, warranty_clause
from .views import DOCX_CONTENT_TYPE


class CatalogFixtureMixin:
    """Catalogo minimo compartido: 3 familias, partida base y 4 articulos."""

    @classmethod
    def setUpTestData(cls):
        cls.family = EquipmentFamily.objects.create(
            name="Bio500/V10", prefix="V10", allows_quotes=True
        )
        cls.other_family = EquipmentFamily.objects.create(
            name="Futronic 5", prefix="FP-", allows_quotes=True
        )
        cls.no_quote_family = EquipmentFamily.objects.create(
            name="BioMini",
            prefix="BM",
            allows_quotes=False,
            support_channel="administracion@biopago.test",
        )

        cls.base = ItemCatalog.objects.create(
            name="Reemplazo de partes y piezas de dispositivos biométricos",
            price=Decimal("10.00"),
            is_mandatory_base=True,
        )
        cls.base.allowed_families.add(cls.family, cls.other_family)

        cls.reader = ItemCatalog.objects.create(
            name="Lector biométrico", price=Decimal("80.00")
        )
        cls.reader.allowed_families.add(cls.family)

        cls.screen = ItemCatalog.objects.create(
            name="Pantalla", price=Decimal("80.00")
        )
        cls.screen.allowed_families.add(cls.family)

        cls.pin = ItemCatalog.objects.create(name="Pin", price=Decimal("20.00"))
        cls.pin.allowed_families.add(cls.family)

        cls.foreign_item = ItemCatalog.objects.create(
            name="Lector Futronic", price=Decimal("50.00")
        )
        cls.foreign_item.allowed_families.add(cls.other_family)

        cls.inactive_item = ItemCatalog.objects.create(
            name="Artículo fuera de línea", price=Decimal("5.00"), is_active=False
        )
        cls.inactive_item.allowed_families.add(cls.family)

    @classmethod
    def payload(cls, **overrides):
        """POST válido por defecto para el generador."""

        data = {
            "ticket_number": "35212348",
            "serial_number": "V10-000123",
            "family": cls.family.pk,
            "items": [cls.reader.pk, cls.screen.pk, cls.pin.pk],
            "warranty_status": "Vencida",
            "technical_report": "El lector no reconoce las huellas registradas.",
        }
        data.update(overrides)
        return data


class ModelTests(CatalogFixtureMixin, TestCase):
    """Autodetección de familia y disponibilidad del catálogo."""

    def test_detect_usa_el_prefijo_mas_largo(self):
        EquipmentFamily.objects.create(name="Bio500 viejo", prefix="V1")
        self.assertEqual(EquipmentFamily.detect("v10-000123"), self.family)

    def test_detect_ignora_familias_inactivas(self):
        EquipmentFamily.objects.filter(pk=self.family.pk).update(is_active=False)
        self.assertIsNone(EquipmentFamily.detect("V10-000123"))

    def test_detect_con_serial_vacio_o_desconocido(self):
        self.assertIsNone(EquipmentFamily.detect(""))
        self.assertIsNone(EquipmentFamily.detect("ZZZ-99"))

    def test_for_family_excluye_partida_base_e_inactivos(self):
        nombres = set(ItemCatalog.for_family(self.family).values_list("name", flat=True))
        self.assertEqual(nombres, {"Lector biométrico", "Pantalla", "Pin"})

    def test_base_lines_for_solo_partidas_base(self):
        self.assertEqual(list(ItemCatalog.base_lines_for(self.family)), [self.base])
        self.assertEqual(ItemCatalog.base_lines_for(self.other_family).count(), 1)

    def test_is_available_for_requiere_activo_y_compatible(self):
        self.assertTrue(self.reader.is_available_for(self.family))
        self.assertFalse(self.reader.is_available_for(self.other_family))
        self.assertFalse(self.inactive_item.is_available_for(self.family))

    def test_display_name_agrega_modelo_sin_duplicarlo(self):
        self.assertEqual(self.reader.display_name(self.family), "Lector biométrico Bio500")
        self.assertEqual(self.base.display_name(self.family), self.base.name + " Bio500")
        self.assertEqual(self.reader.display_name(None), "Lector biométrico")


class PricingTests(CatalogFixtureMixin, TestCase):
    """Reglas del tarifario referencial en USD."""

    def test_tarifario_incluye_partida_base_y_total(self):
        quote = build_quote(family=self.family, items=[self.reader, self.screen, self.pin])
        self.assertTrue(quote.quote_allowed)
        self.assertEqual(len(quote.lines), 4)
        self.assertEqual(quote.base_lines[0].article, self.base.name)
        self.assertEqual(quote.total, Decimal("190.00"))

    def test_tarifario_no_duplica_partidas_repetidas(self):
        quote = build_quote(family=self.family, items=[self.reader, self.reader])
        self.assertEqual(len(quote.lines), 2)
        self.assertEqual(quote.total, Decimal("90.00"))

    def test_familia_sin_cotizacion_usa_canal_institucional(self):
        quote = build_quote(family=self.no_quote_family, items=[self.reader])
        self.assertFalse(quote.quote_allowed)
        self.assertEqual(quote.lines, [])
        self.assertEqual(quote.channel, "administracion@biopago.test")
        self.assertTrue(quote.warnings)

    def test_articulo_incompatible_genera_error(self):
        quote = build_quote(family=self.family, items=[self.foreign_item])
        self.assertTrue(quote.errors)
        self.assertIn("Lector Futronic", quote.errors[0])

    def test_clausula_segun_estado_de_garantia(self):
        self.assertIn("ya no cumple", warranty_clause("Vencida"))
        self.assertIn("no cumple con las cláusulas", warranty_clause("Invalidada"))
        self.assertIn("garantía vigente", warranty_clause("Vigente"))
        self.assertEqual(warranty_clause("Desconocido"), "")

    def test_familia_nula_reporta_error(self):
        quote = build_quote(family=None)
        self.assertEqual(quote.total, Decimal("0.00"))
        self.assertTrue(quote.errors)


class FormattingTests(TestCase):
    """Formato numérico venezolano usado en pantalla y en el correo."""

    def test_money_usa_separadores_venezolanos(self):
        self.assertEqual(money("1234.5"), "1.234,50")
        self.assertEqual(money(Decimal("0")), "0,00")

    def test_price_cell_omite_decimales_en_enteros(self):
        self.assertEqual(price_cell("80.00"), "80")
        self.assertEqual(price_cell("12.5"), "12,50")


class DocumentTests(CatalogFixtureMixin, TestCase):
    """Redacción del documento institucional (preview, portapapeles y Word)."""

    def build(self, family=None, items=None, warranty="Vencida", report=""):
        family = family or self.family
        items = [self.reader, self.pin] if items is None else items
        quote = build_quote(family=family, items=items)
        return build_document(
            family=family,
            ticket_number="35212348",
            serial_number="V10-000123",
            warranty_status=warranty,
            items=items,
            quote=quote,
            technical_report=report,
        )

    def test_documento_incluye_asunto_serial_y_tabla(self):
        document = self.build()
        self.assertIn("Ticket #35212348", document.subject)
        self.assertIn("V10-000123", document.subject)
        self.assertEqual(document.family_name, "Bio500/V10")
        self.assertIn("Estimado/a cliente:", document.plain_text)
        self.assertIn("Costo referencial ($)", document.plain_text)
        self.assertIn("Lector biométrico Bio500", document.plain_text)
        self.assertIn("80", document.plain_text)
        self.assertEqual(document.filename, "Diagnostico_35212348.docx")

    def test_documento_respeta_clausula_de_garantia(self):
        self.assertIn("ya no cumple", self.build(warranty="Vencida").plain_text)
        self.assertIn("garantía vigente", self.build(warranty="Vigente").plain_text)

    def test_familia_sin_cotizacion_remite_al_canal(self):
        document = self.build(family=self.no_quote_family, items=[])
        self.assertNotIn("Costo referencial ($)", document.plain_text)
        self.assertIn("administracion@biopago.test", document.plain_text)

    def test_informe_vacio_usa_texto_institucional(self):
        document = self.build(report="")
        self.assertIn("Sin observaciones adicionales registradas", document.plain_text)

    def test_docx_es_un_word_valido(self):
        from docx import Document as WordDocument

        binary = build_docx(self.build())
        self.assertTrue(binary.startswith(b"PK"))
        word = WordDocument(BytesIO(binary))
        self.assertEqual(len(word.tables), 1)
        encabezado = [celda.text for celda in word.tables[0].rows[0].cells]
        self.assertEqual(encabezado, ["Modelo", "Artículo", "Costo referencial ($)"])

class GeneratorViewTests(CatalogFixtureMixin, TestCase):
    """Formulario, vista previa, API de contexto y descarga."""

    def test_generador_get_muestra_formulario(self):
        response = self.client.get(reverse("email_generator:generator"))
        self.assertEqual(response.status_code, 200)
        self.assertContains(response, 'id="diagnostic-form"')
        self.assertIsNone(response.context["result"])

    def test_generador_post_guarda_ticket_y_total(self):
        response = self.client.post(reverse("email_generator:generator"), self.payload())
        self.assertEqual(response.status_code, 200)
        self.assertIsNotNone(response.context["result"])
        self.assertEqual(response.context["total_reference"], Decimal("190.00"))
        ticket = DiagnosticTicket.objects.get()
        self.assertEqual(ticket.ticket_number, "35212348")
        self.assertEqual(ticket.total_reference, Decimal("190.00"))
        self.assertEqual(ticket.selected_items.count(), 3)
        self.assertIn("Costo referencial", ticket.document_body)

    def test_generador_post_invalido_no_guarda(self):
        response = self.client.post(
            reverse("email_generator:generator"), self.payload(items=[], serial_number="")
        )
        self.assertEqual(response.status_code, 200)
        self.assertIsNone(response.context["result"])
        self.assertIn("serial_number", response.context["form"].errors)
        self.assertIn("items", response.context["form"].errors)
        self.assertEqual(DiagnosticTicket.objects.count(), 0)

    def test_formulario_rechaza_articulos_de_otra_familia(self):
        response = self.client.post(
            reverse("email_generator:generator"), self.payload(items=[self.foreign_item.pk])
        )
        errores = response.context["form"].errors
        self.assertIn("items", errores)
        self.assertEqual(DiagnosticTicket.objects.count(), 0)

    def test_vista_previa_renderiza_tabla(self):
        response = self.client.get(reverse("email_generator:preview"), self.payload())
        self.assertEqual(response.status_code, 200)
        self.assertContains(response, "quote-table")
        self.assertContains(response, "Lector biométrico")

    def test_vista_previa_sin_datos_no_falla(self):
        response = self.client.get(
            reverse("email_generator:preview"), {"serial_number": "ZZZ"}
        )
        self.assertEqual(response.status_code, 200)
        self.assertNotContains(response, "quote-table")

    def test_vista_previa_no_permite_post(self):
        response = self.client.post(reverse("email_generator:preview"), {})
        self.assertEqual(response.status_code, 405)

    def test_api_contexto_por_serial(self):
        response = self.client.get(
            reverse("email_generator:api_context"), {"serial": "v10-555"}
        )
        payload = response.json()
        self.assertEqual(response.status_code, 200)
        self.assertEqual(payload["family"]["name"], "Bio500/V10")
        self.assertEqual(
            {item["name"] for item in payload["items"]},
            {"Lector biométrico", "Pantalla", "Pin"},
        )
        self.assertEqual(
            payload["family"]["channel"],
            "administracion.estaciones@biopagopdv.com.ve",
        )
        self.assertTrue(payload["family"]["allows_quotes"])

    def test_api_contexto_por_familia(self):
        response = self.client.get(
            reverse("email_generator:api_context"), {"family": self.other_family.pk}
        )
        payload = response.json()
        self.assertEqual(payload["family"]["name"], "Futronic 5")
        self.assertEqual([item["name"] for item in payload["items"]], ["Lector Futronic"])

    def test_api_contexto_serial_desconocido(self):
        response = self.client.get(
            reverse("email_generator:api_context"), {"serial": "ZZZ-1"}
        )
        payload = response.json()
        self.assertIsNone(payload["family"])
        self.assertEqual(payload["items"], [])

    def test_descarga_docx_devuelve_archivo_word(self):
        response = self.client.post(
            reverse("email_generator:download_docx"), self.payload()
        )
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response["Content-Type"], DOCX_CONTENT_TYPE)
        self.assertEqual(
            response["Content-Disposition"],
            'attachment; filename="Diagnostico_35212348.docx"',
        )
        self.assertTrue(response.content.startswith(b"PK"))

    def test_descarga_docx_invalida_devuelve_400(self):
        response = self.client.post(
            reverse("email_generator:download_docx"), {"serial_number": "ZZZ"}
        )
        self.assertEqual(response.status_code, 400)
        self.assertFalse(response.json()["ok"])
        self.assertIn("errors", response.json())

    def test_descarga_docx_rechaza_get(self):
        response = self.client.get(reverse("email_generator:download_docx"))
        self.assertEqual(response.status_code, 405)


class HistoryViewTests(CatalogFixtureMixin, TestCase):
    """Historial, detalle y descarga de tickets guardados."""

    def setUp(self):
        self.ticket = DiagnosticTicket.objects.create(
            ticket_number="T-001",
            serial_number="V10-111",
            family=self.family,
            warranty_status="Vigente",
            total_reference=Decimal("90.00"),
        )
        self.ticket.selected_items.set([self.reader])
        DiagnosticTicket.objects.create(
            ticket_number="T-002",
            serial_number="FP-222",
            family=self.other_family,
            warranty_status="Vencida",
        )

    def test_historial_lista_tickets(self):
        response = self.client.get(reverse("email_generator:history"))
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.context["total_count"], 2)

    def test_historial_filtra_por_texto(self):
        response = self.client.get(reverse("email_generator:history"), {"q": "T-001"})
        self.assertEqual([t.pk for t in response.context["tickets"]], [self.ticket.pk])

    def test_historial_filtra_por_garantia_y_familia(self):
        vigentes = self.client.get(
            reverse("email_generator:history"), {"garantia": "Vigente"}
        )
        self.assertEqual(vigentes.context["total_count"], 1)
        por_familia = self.client.get(
            reverse("email_generator:history"), {"familia": self.other_family.pk}
        )
        self.assertEqual(por_familia.context["tickets"][0].ticket_number, "T-002")

    def test_detalle_del_ticket_reconstruye_documento(self):
        response = self.client.get(
            reverse("email_generator:ticket_detail", args=[self.ticket.pk])
        )
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.context["document"].family_name, "Bio500/V10")
        self.assertContains(response, "T-001")

    def test_descarga_docx_del_ticket(self):
        response = self.client.get(
            reverse("email_generator:ticket_docx", args=[self.ticket.pk])
        )
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response["Content-Type"], DOCX_CONTENT_TYPE)
        self.assertIn("Diagnostico_T-001.docx", response["Content-Disposition"])

    def test_ticket_inexistente_devuelve_404(self):
        self.assertEqual(
            self.client.get(reverse("email_generator:ticket_detail", args=[9999])).status_code,
            404,
        )
        self.assertEqual(
            self.client.get(reverse("email_generator:ticket_docx", args=[9999])).status_code,
            404,
        )


class ManagementViewTests(CatalogFixtureMixin, TestCase):
    """Panel de gestión del catálogo (solo personal autorizado)."""

    @classmethod
    def setUpTestData(cls):
        super().setUpTestData()
        User = get_user_model()
        cls.staff = User.objects.create_user(
            username="soporte", password="clave-segura-123", is_staff=True
        )
        cls.operator = User.objects.create_user(
            username="operador", password="clave-segura-123"
        )

    def test_gestion_requiere_staff(self):
        url = reverse("email_generator:management")
        anonimo = self.client.get(url)
        self.assertEqual(anonimo.status_code, 302)
        self.assertIn("/admin/login/", anonimo["Location"])

        self.client.force_login(self.operator)
        self.assertEqual(self.client.get(url).status_code, 302)

    def test_gestion_muestra_estadisticas(self):
        self.client.force_login(self.staff)
        response = self.client.get(reverse("email_generator:management"))
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.context["stats"]["items"], 6)
        self.assertEqual(response.context["stats"]["active_items"], 5)

    def test_gestion_crea_articulo(self):
        self.client.force_login(self.staff)
        response = self.client.post(
            reverse("email_generator:catalog_create"),
            {
                "name": "Fuente de poder",
                "price": "45.50",
                "is_active": "on",
                "allowed_families": [self.family.pk],
            },
        )
        self.assertRedirects(response, reverse("email_generator:management"))
        item = ItemCatalog.objects.get(name="Fuente de poder")
        self.assertEqual(item.price, Decimal("45.50"))
        self.assertTrue(item.is_available_for(self.family))

    def test_gestion_actualiza_precio(self):
        self.client.force_login(self.staff)
        response = self.client.post(
            reverse("email_generator:catalog_price", args=[self.reader.pk]),
            {"price": "95.50"},
        )
        self.assertRedirects(response, reverse("email_generator:management"))
        self.reader.refresh_from_db()
        self.assertEqual(self.reader.price, Decimal("95.50"))

    def test_gestion_rechaza_precio_invalido(self):
        self.client.force_login(self.staff)
        self.client.post(
            reverse("email_generator:catalog_price", args=[self.reader.pk]),
            {"price": "-5"},
        )
        self.reader.refresh_from_db()
        self.assertEqual(self.reader.price, Decimal("80.00"))

    def test_gestion_alterna_visibilidad(self):
        self.client.force_login(self.staff)
        response = self.client.post(
            reverse("email_generator:catalog_toggle", args=[self.pin.pk])
        )
        self.assertRedirects(response, reverse("email_generator:management"))
        self.pin.refresh_from_db()
        self.assertFalse(self.pin.is_active)
        self.assertNotIn(self.pin, ItemCatalog.for_family(self.family))

    def test_gestion_crea_familia(self):
        self.client.force_login(self.staff)
        response = self.client.post(
            reverse("email_generator:family_create"),
            {
                "name": "Grantor G62",
                "prefix": "G62",
                "allows_quotes": "on",
                "support_channel": "",
                "is_active": "on",
            },
        )
        self.assertRedirects(response, reverse("email_generator:management"))
        family = EquipmentFamily.objects.get(name="Grantor G62")
        self.assertTrue(family.allows_quotes)
        self.assertEqual(EquipmentFamily.detect("g62-999"), family)

    def test_gestion_edita_familia(self):
        self.client.force_login(self.staff)
        response = self.client.post(
            reverse("email_generator:family_edit", args=[self.family.pk]),
            {
                "name": self.family.name,
                "prefix": self.family.prefix,
                "allows_quotes": "on",
                "support_channel": "soporte.v10@biopago.test",
                "is_active": "on",
            },
        )
        self.assertRedirects(response, reverse("email_generator:management"))
        self.family.refresh_from_db()
        self.assertEqual(self.family.support_channel, "soporte.v10@biopago.test")

    def test_operador_no_puede_crear_articulos(self):
        self.client.force_login(self.operator)
        response = self.client.post(
            reverse("email_generator:catalog_create"),
            {"name": "Intento no autorizado", "price": "1.00", "is_active": "on"},
        )
        self.assertEqual(response.status_code, 302)
        self.assertFalse(ItemCatalog.objects.filter(name="Intento no autorizado").exists())

