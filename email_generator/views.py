"""Vistas de GestioBioV3: generador, vista previa, exportación y gestión."""

from django.conf import settings
from django.contrib import messages
from django.contrib.admin.views.decorators import staff_member_required
from django.db.models import Q
from django.http import HttpResponse, JsonResponse
from django.shortcuts import get_object_or_404, redirect, render
from django.template.loader import render_to_string
from django.views.decorators.http import require_GET, require_POST

from .forms import DiagnosticForm, FamilyForm, ItemCatalogForm, PriceForm
from .models import DiagnosticTicket, EquipmentFamily, ItemCatalog
from .services.docx_export import build_docx
from .services.email_builder import build_document
from .services.formatting import money
from .services.pipeline import register_ticket, run_generation
from .services.pricing import administrative_channel, build_quote

DOCX_CONTENT_TYPE = (
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
)


def brand_context():
    """Datos de marca compartidos por todas las plantillas."""

    cfg = getattr(settings, "GESTIOBIO", {}) or {}
    return {
        "brand_name": cfg.get("BRAND_NAME", "GestioBioV3"),
        "brand_subtitle": cfg.get("BRAND_SUBTITLE", "Asistente de Soporte Técnico"),
        "company": cfg.get("COMPANY", "Biopago C.A."),
        "currency": cfg.get("CURRENCY", "USD"),
    }


def document_for_ticket(ticket):
    """Reconstruye el documento institucional de un ticket guardado."""

    items = list(ticket.selected_items.all())
    quote = build_quote(family=ticket.family, items=items)
    return build_document(
        family=ticket.family,
        ticket_number=ticket.ticket_number,
        serial_number=ticket.serial_number,
        warranty_status=ticket.warranty_status,
        items=items,
        quote=quote,
        technical_report=ticket.technical_report,
    )


def docx_response(document):
    response = HttpResponse(build_docx(document), content_type=DOCX_CONTENT_TYPE)
    response["Content-Disposition"] = f'attachment; filename="{document.filename}"'
    return response


def generator(request):
    """Formulario de captura y generación del diagnóstico."""

    form = DiagnosticForm()
    result = None
    ticket = None

    if request.method == "POST":
        form = DiagnosticForm(request.POST)
        if form.is_valid():
            result = run_generation(form.cleaned_data)
            ticket = register_ticket(form.cleaned_data, result, request.user)
            messages.success(
                request,
                f"Diagnóstico del ticket #{ticket.ticket_number} generado y "
                "guardado en el historial.",
            )
        else:
            messages.error(
                request,
                "Revise los datos: hay campos obligatorios pendientes o "
                "inconsistentes con la familia del equipo.",
            )

    context = {
        **brand_context(),
        "form": form,
        "result": result,
        "ticket": ticket,
        "family": getattr(form, "family_obj", None),
        "total_reference": result.quote.total if result else None,
    }
    return render(request, "email_generator/generator.html", context)


@require_GET
def preview(request):
    """Vista previa en tiempo real (consumida por JavaScript)."""

    form = DiagnosticForm(request.GET or None)
    result = run_generation(form.cleaned_data) if form.is_valid() else None
    context = {
        "form": form,
        "result": result,
        "family": getattr(form, "family_obj", None),
    }
    return HttpResponse(
        render_to_string(
            "email_generator/partials/preview.html", context, request=request
        )
    )


@require_GET
def api_context(request):
    """Autodetección de familia y fallas compatibles (JSON)."""

    serial = (request.GET.get("serial") or "").strip()
    family_id = request.GET.get("family") or ""
    family = None
    if family_id:
        family = EquipmentFamily.objects.filter(pk=family_id, is_active=True).first()
    if family is None:
        family = EquipmentFamily.detect(serial)

    items = []
    if family is not None:
        items = [
            {
                "id": item.pk,
                "name": item.name,
                "price": money(item.price),
                "url": item.get_absolute_url(),
            }
            for item in ItemCatalog.for_family(family)
        ]

    return JsonResponse(
        {
            "family": (
                {
                    "id": family.pk,
                    "name": family.name,
                    "prefix": family.prefix,
                    "allows_quotes": family.allows_quotes,
                    "channel": administrative_channel(family),
                }
                if family is not None
                else None
            ),
            "items": items,
        }
    )


@require_POST
def download_docx(request):
    """Genera y descarga ``Diagnostico_{ticket}.docx``."""

    form = DiagnosticForm(request.POST)
    if not form.is_valid():
        return JsonResponse({"ok": False, "errors": form.errors}, status=400)
    result = run_generation(form.cleaned_data)
    return docx_response(result.document)



def history(request):
    """Historial de evaluaciones guardadas."""

    tickets = DiagnosticTicket.objects.select_related("family").prefetch_related(
        "selected_items"
    )
    query = (request.GET.get("q") or "").strip()
    warranty = (request.GET.get("garantia") or "").strip()
    family_id = request.GET.get("familia") or ""

    if query:
        tickets = tickets.filter(
            Q(ticket_number__icontains=query) | Q(serial_number__icontains=query)
        )
    if warranty:
        tickets = tickets.filter(warranty_status=warranty)
    if family_id:
        tickets = tickets.filter(family_id=family_id)

    context = {
        **brand_context(),
        "tickets": tickets[:100],
        "total_count": tickets.count(),
        "query": query,
        "warranty": warranty,
        "family_id": family_id,
        "families": EquipmentFamily.objects.all(),
        "warranty_choices": DiagnosticTicket.Warranty.choices,
    }
    return render(request, "email_generator/history.html", context)


def ticket_detail(request, pk):
    """Detalle de un ticket con su documento institucional."""

    ticket = get_object_or_404(
        DiagnosticTicket.objects.select_related("family").prefetch_related(
            "selected_items"
        ),
        pk=pk,
    )
    context = {
        **brand_context(),
        "ticket": ticket,
        "document": document_for_ticket(ticket),
    }
    return render(request, "email_generator/ticket_detail.html", context)


def ticket_docx(request, pk):
    """Descarga el Word de un ticket ya guardado."""

    ticket = get_object_or_404(DiagnosticTicket, pk=pk)
    return docx_response(document_for_ticket(ticket))


@staff_member_required
def management(request):
    """Panel de gestión de repuestos, precios y compatibilidades."""

    items = ItemCatalog.objects.prefetch_related("allowed_families").order_by("name")
    families = EquipmentFamily.objects.prefetch_related("items").order_by("name")
    context = {
        **brand_context(),
        "items": items,
        "families": families,
        "price_form": PriceForm(),
        "stats": {
            "items": items.count(),
            "active_items": items.filter(is_active=True).count(),
            "families": families.count(),
            "tickets": DiagnosticTicket.objects.count(),
        },
    }
    return render(request, "email_generator/management/dashboard.html", context)


@staff_member_required
def catalog_create(request):
    form = ItemCatalogForm(request.POST or None)
    if request.method == "POST" and form.is_valid():
        item = form.save()
        messages.success(
            request, f"Artículo «{item.name}» creado con precio ${money(item.price)}."
        )
        return redirect("email_generator:management")
    context = {**brand_context(), "form": form, "item": None}
    return render(request, "email_generator/management/item_form.html", context)


@staff_member_required
def catalog_edit(request, pk):
    item = get_object_or_404(ItemCatalog, pk=pk)
    form = ItemCatalogForm(request.POST or None, instance=item)
    if request.method == "POST" and form.is_valid():
        item = form.save()
        messages.success(request, f"Artículo «{item.name}» actualizado.")
        return redirect("email_generator:management")
    context = {**brand_context(), "form": form, "item": item}
    return render(request, "email_generator/management/item_form.html", context)


@staff_member_required
@require_POST
def catalog_price(request, pk):
    item = get_object_or_404(ItemCatalog, pk=pk)
    form = PriceForm(request.POST, instance=item)
    if form.is_valid():
        item = form.save()
        messages.success(
            request, f"Precio de «{item.name}» actualizado a ${money(item.price)}."
        )
    else:
        messages.error(request, "El precio indicado no es válido.")
    return redirect("email_generator:management")


@staff_member_required
@require_POST
def catalog_toggle(request, pk):
    item = get_object_or_404(ItemCatalog, pk=pk)
    item.is_active = not item.is_active
    item.save(update_fields=["is_active", "updated_at"])
    state = "habilitado" if item.is_active else "deshabilitado"
    messages.success(request, f"Artículo «{item.name}» {state}.")
    return redirect("email_generator:management")


@staff_member_required
def family_create(request):
    form = FamilyForm(request.POST or None)
    if request.method == "POST" and form.is_valid():
        family = form.save()
        messages.success(request, f"Familia «{family.name}» creada.")
        return redirect("email_generator:management")
    context = {**brand_context(), "form": form, "family": None}
    return render(request, "email_generator/management/family_form.html", context)


@staff_member_required
def family_edit(request, pk):
    family = get_object_or_404(EquipmentFamily, pk=pk)
    form = FamilyForm(request.POST or None, instance=family)
    if request.method == "POST" and form.is_valid():
        family = form.save()
        messages.success(request, f"Familia «{family.name}» actualizada.")
        return redirect("email_generator:management")
    context = {**brand_context(), "form": form, "family": family}
    return render(request, "email_generator/management/family_form.html", context)
