import tkinter as tk
from tkinter import ttk, messagebox
from docx import Document
from docx.shared import Pt

class GeneradorDiagnostico:
    def __init__(self, root):
        self.root = root
        self.root.title("Asistente de Soporte Técnico - Biopago C.A.")
        
        # Base de datos de precios (según archivos suministrados)
        self.precios = {
            "Mantenimiento": 10,
            "Mano de Obra": 10,
            "Pantalla táctil": 80,
            "Lector biométrico": 80,
            "Pin de carga": 20,
            "Botón de encendido": 10
        }

        self.create_widgets()

    def create_widgets(self):
        # Campos de texto simples
        tk.Label(self.root, text="Número de Ticket:").grid(row=0, column=0, sticky="w", padx=10)
        self.ent_ticket = tk.Entry(self.root)
        self.ent_ticket.grid(row=0, column=1, pady=5)

        tk.Label(self.root, text="Serial del Equipo:").grid(row=1, column=0, sticky="w", padx=10)
        self.ent_serial = tk.Entry(self.root)
        self.ent_serial.grid(row=1, column=1, pady=5)

        # Selección de fallas (Permite hasta 3)
        tk.Label(self.root, text="Seleccione Fallas (Máx 3):").grid(row=2, column=0, sticky="w", padx=10)
        self.list_fallas = tk.Listbox(self.root, selectmode="multiple", height=6)
        fallas_ops = ["Lector biométrico", "Pantalla táctil", "Pin de carga", "Botón de encendido", "Mantenimiento menor"]
        for item in fallas_ops:
            self.list_fallas.insert(tk.END, item)
        self.list_fallas.grid(row=2, column=1, pady=5)

        # Garantía
        tk.Label(self.root, text="Estado de Garantía:").grid(row=3, column=0, sticky="w", padx=10)
        self.var_garantia = tk.StringVar(value="Vigente")
        combo_garantia = ttk.Combobox(self.root, textvariable=self.var_garantia)
        combo_garantia['values'] = ("Vigente", "Vencida", "Invalidada")
        combo_garantia.grid(row=3, column=1, pady=5)

        # Notas importantes
        tk.Label(self.root, text="Nota Administrativa:").grid(row=4, column=0, sticky="w", padx=10)
        self.var_nota = tk.StringVar(value="Ninguna")
        combo_nota = ttk.Combobox(self.root, textvariable=self.var_nota)
        combo_nota['values'] = ("Ninguna", "Nota 1: Prioridad alta", "Nota 2: Repuesto en tránsito", "Nota 3: Requiere pago previo")
        combo_nota.grid(row=4, column=1, pady=5)

        # Informe Técnico
        tk.Label(self.root, text="Informe Técnico (Libre):").grid(row=5, column=0, sticky="w", padx=10)
        self.txt_informe = tk.Text(self.root, height=4, width=30)
        self.txt_informe.grid(row=5, column=1, pady=5, padx=10)

        # Botón Generar
        btn_generar = tk.Button(self.root, text="Generar Documento Word", command=self.generar_word, bg="green", fg="white")
        btn_generar.grid(row=6, columnspan=2, pady=20)

    def generar_word(self):
        doc = Document()
        
        # Encabezado
        doc.add_paragraph("Estimado/a cliente:")
        doc.add_paragraph(f"En respuesta a su solicitud de servicio (ticket #{self.ent_ticket.get()}), "
                          f"hemos realizado una evaluación exhaustiva de su equipo con serial {self.ent_serial.get()}.")

        # Fallas detectadas
        indices = self.list_fallas.curselection()
        fallas_seleccionadas = [self.list_fallas.get(i) for i in indices]
        texto_fallas = ", ".join(fallas_seleccionadas)
        doc.add_paragraph(f"Hemos determinado que presenta falla corregible con: {texto_fallas}.")

        # Lógica de Garantía
        if self.var_garantia.get() == "Vencida":
            doc.add_paragraph("Se le recuerda que su equipo ya no cumple con los lapsos de tiempo establecidos.")
        elif self.var_garantia.get() == "Invalidada":
            doc.add_paragraph("Se le informa que su equipo no cumple con las cláusulas para la ejecución de la garantía.")

        # Tabla de Costos
        doc.add_heading('Propuesta de Solución', level=2)
        table = doc.add_table(rows=1, cols=3)
        table.style = 'Table Grid'
        hdr_cells = table.rows[0].cells
        hdr_cells[0].text = 'Modelo'
        hdr_cells[1].text = 'Artículo'
        hdr_cells[2].text = 'Costo ($)'

        # Insertar mano de obra si hay reemplazo físico
        if any("reemplazo" in f.lower() or "táctil" in f.lower() or "lector" in f.lower() for f in fallas_seleccionadas):
            row = table.add_row().cells
            row[0].text = "BIO500"
            row[1].text = "Reemplazo de partes y piezas"
            row[2].text = "10"

        # Insertar repuestos y servicios
        for falla in fallas_seleccionadas:
            row_cells = table.add_row().cells
            row_cells[0].text = "BIO500"
            row_cells[1].text = falla
            # Obtener precio del diccionario o 0 si no existe
            precio = self.precios.get(falla.replace("reemplazo de ", ""), 0)
            row_cells[2].text = str(precio)

        # Informe Técnico
        doc.add_heading('Informe Técnico', level=2)
        doc.add_paragraph(self.txt_informe.get("1.0", tk.END))

        doc.add_paragraph("\nReciba nuestros saludos.\nEquipo de Soporte técnico\nBiopago C.A.")

        # Guardar
        filename = f"Diagnostico_{self.ent_ticket.get()}.docx"
        doc.save(filename)
        messagebox.showinfo("Éxito", f"Documento {filename} generado correctamente.")

if __name__ == "__main__":
    root = tk.Tk()
    app = GeneradorDiagnostico(root)
    root.mainloop()