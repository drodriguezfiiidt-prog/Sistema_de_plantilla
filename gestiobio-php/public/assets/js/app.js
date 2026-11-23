/* ==========================================================================
   GestioBioV3 · Interacciones del asistente de soporte técnico
   - Autodetección de familia por prefijo de serial (api/contexto/)
   - Vista previa en vivo sobre partials/preview.html
   - Copiado del documento al portapapeles
   - Descarga del .docx generado en el servidor
   ========================================================================== */
(function () {
  "use strict";

  const form = document.getElementById("diagnostic-form");
  if (!form) return;

  const panel = document.getElementById("preview-panel");
  const statusLabel = document.getElementById("preview-status");
  const chip = document.getElementById("family-chip");
  const serialInput = form.querySelector("[data-serial-input]");
  const familySelect = form.querySelector("#id_family");
  const itemsBox = form.querySelector("#id_items");
  const previewUrl = form.dataset.previewUrl;
  const contextUrl = form.dataset.contextUrl;

  const IDLE_CHIP = "Sin familia detectada";
  let previewTimer = null;
  let contextTimer = null;
  let previewController = null;

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function setStatus(text) {
    if (statusLabel) statusLabel.textContent = text;
  }

  function updateChip(family) {
    if (!chip) return;
    if (!family) {
      chip.textContent = IDLE_CHIP;
      return;
    }
    const parts = [family.name, "prefijo " + family.prefix];
    if (!family.allows_quotes) parts.push("sin tabla de cotización");
    chip.textContent = parts.join(" · ");
  }

  function formatPrice(value) {
    const normalized = String(value).replace(/\./g, "").replace(",", ".");
    const number = Number(normalized);
    if (!isFinite(number)) return String(value);
    return Number.isInteger(number)
      ? String(number)
      : number.toFixed(2).replace(".", ",");
  }

  function renderItems(items) {
    if (!itemsBox) return;
    const checked = new Set(
      Array.from(itemsBox.querySelectorAll('input[type="checkbox"]:checked')).map(
        (input) => input.value
      )
    );

    if (!items.length) {
      itemsBox.innerHTML =
        '<p class="empty-state">No hay repuestos compatibles habilitados para esta familia.</p>';
      return;
    }

    itemsBox.innerHTML = items
      .map((item, index) => {
        const inputId = "id_items_" + index;
        const label = item.name + " ($" + formatPrice(item.price) + ")";
        const mark = checked.has(String(item.id)) ? " checked" : "";
        return (
          '<div><label for="' + inputId + '">' +
          '<input type="checkbox" name="items[]" value="' + item.id + '" id="' + inputId + '"' + mark + "> " +
          escapeHtml(label) +
          "</label></div>"
        );
      })
      .join("");
  }

  function schedulePreview() {
    window.clearTimeout(previewTimer);
    previewTimer = window.setTimeout(refreshPreview, 450);
  }

  function refreshPreview() {
    if (!previewUrl || !panel) return;
    if (previewController) previewController.abort();
    previewController = new AbortController();

    const params = new URLSearchParams(new FormData(form));
    params.delete("_token");
    setStatus("Actualizando…");

    fetch(previewUrl + "?" + params.toString(), {
      signal: previewController.signal,
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then(function (response) {
        if (!response.ok) throw new Error("preview");
        return response.text();
      })
      .then(function (html) {
        panel.innerHTML = html;
        setStatus("Actualización en vivo");
      })
      .catch(function (error) {
        if (error.name !== "AbortError") setStatus("Vista previa no disponible");
      });
  }

  function loadContext(mode) {
    if (!contextUrl) return;

    const serial = serialInput ? serialInput.value.trim() : "";
    const familyId = familySelect ? familySelect.value : "";
    const params = new URLSearchParams();

    if (mode === "serial") {
      if (!serial) {
        updateChip(null);
        renderItems([]);
        schedulePreview();
        return;
      }
      params.set("serial", serial);
    } else if (familyId) {
      params.set("family", familyId);
    } else if (serial) {
      params.set("serial", serial);
    } else {
      updateChip(null);
      renderItems([]);
      schedulePreview();
      return;
    }

    fetch(contextUrl + "?" + params.toString(), {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then(function (response) {
        if (!response.ok) throw new Error("context");
        return response.json();
      })
      .then(function (data) {
        if (
          mode === "serial" &&
          data.family &&
          familySelect &&
          familySelect.value !== String(data.family.id)
        ) {
          familySelect.value = String(data.family.id);
        }
        updateChip(data.family);
        renderItems(data.items || []);
        schedulePreview();
      })
      .catch(function () {
        setStatus("No se pudo autodetectar la familia");
      });
  }

  form.addEventListener("input", function (event) {
    if (event.target === serialInput) {
      window.clearTimeout(contextTimer);
      contextTimer = window.setTimeout(function () {
        loadContext("serial");
      }, 300);
    }
    schedulePreview();
  });

  form.addEventListener("change", function (event) {
    if (event.target === familySelect) loadContext("family");
    schedulePreview();
  });

  form.addEventListener("reset", function () {
    window.setTimeout(function () {
      if (familySelect) familySelect.value = "";
      updateChip(null);
      schedulePreview();
    }, 0);
  });

  /* --- Portapapeles y descarga del Word ---------------------------------- */

  function flashLabel(button, text) {
    const original = button.dataset.originalLabel || button.textContent.trim();
    button.dataset.originalLabel = original;
    button.textContent = text;
    window.setTimeout(function () {
      button.textContent = original;
    }, 2000);
  }

  function fallbackCopy(text, done) {
    const area = document.createElement("textarea");
    area.value = text;
    area.setAttribute("readonly", "");
    area.style.position = "fixed";
    area.style.opacity = "0";
    document.body.appendChild(area);
    area.select();
    try {
      document.execCommand("copy");
      done();
    } catch (error) {
      window.alert("El navegador bloqueó el acceso al portapapeles.");
    }
    document.body.removeChild(area);
  }

  function copyPreview(button) {
    const source = document.getElementById("preview-text");
    const text = source ? source.value || source.textContent : "";
    if (!text.trim()) {
      window.alert("Genere primero el diagnóstico para copiar el documento.");
      return;
    }
    const done = function () {
      flashLabel(button, "¡Copiado!");
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done).catch(function () {
        fallbackCopy(text, done);
      });
    } else {
      fallbackCopy(text, done);
    }
  }

  function filenameFrom(header) {
    if (!header) return "Diagnostico.docx";
    const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(header);
    return match ? decodeURIComponent(match[1]) : "Diagnostico.docx";
  }

  function describeErrors(errors) {
    if (!errors) return "";
    return Object.keys(errors)
      .map(function (key) {
        const value = errors[key];
        return Array.isArray(value) ? value.join(" ") : String(value);
      })
      .join(" ");
  }

  function downloadDocx(button) {
    const url = button.dataset.url;
    if (!url) return;

    const original = button.textContent;
    button.disabled = true;
    button.textContent = "Generando Word…";

    fetch(url, {
      method: "POST",
      body: new FormData(form),
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then(function (response) {
        if (!response.ok) {
          return response
            .json()
            .catch(function () {
              return {};
            })
            .then(function (payload) {
              throw new Error(
                describeErrors(payload.errors) ||
                  "No se pudo generar el documento Word."
              );
            });
        }
        return response.blob().then(function (blob) {
          const link = document.createElement("a");
          link.href = URL.createObjectURL(blob);
          link.download = filenameFrom(response.headers.get("Content-Disposition"));
          document.body.appendChild(link);
          link.click();
          link.remove();
          window.setTimeout(function () {
            URL.revokeObjectURL(link.href);
          }, 1500);
        });
      })
      .catch(function (error) {
        window.alert(error.message);
      })
      .finally(function () {
        button.disabled = false;
        button.textContent = original;
      });
  }

  document.addEventListener("click", function (event) {
    const copyButton = event.target.closest("[data-copy-preview]");
    if (copyButton) {
      copyPreview(copyButton);
      return;
    }
    const docxButton = event.target.closest("[data-download-docx]");
    if (docxButton) downloadDocx(docxButton);
  });
})();
