import tkinter as tk
from tkinter import ttk, messagebox
from docx import Document
from docx.shared import Pt

class GeneradorDiagnostico:
    def __init__(self, root):
        self.root = root
        self.root.title("Asistente de Soporte Técnico - Biopago C.A.")
        
        # Precios basados en la tabla suministrada
        self.precios = {
            "Lector biométrico": 80,
            "Pantalla táctil": 80,
            "Pin de carga": 20,
            "Botón de encendido": 10,
            "Mantenimiento menor": 10
        }

        self.create_widgets()

    def create_widgets(self):
        tk.Label(self.root, text="Número de Ticket:").grid(row=0, column=0, sticky="w", padx=10)
        self.ent_ticket = tk.Entry(self.root)
        self.ent_ticket.grid(row=0, column=1, pady=5)

        tk.Label(self.root, text="Serial del Equipo:").grid(row=1, column=0, sticky="w", padx=10)
        self.ent_serial = tk.Entry(self.root)
        self.ent_serial.grid(row=1, column=1, pady=5)

        tk.Label(self.root, text="Seleccione Fallas (Máx 3):").grid(row=2, column=0, sticky="w", padx=10)
        self.list_fallas = tk.Listbox(self.root, selectmode="multiple", height=6)
        fallas_ops = ["Lector biométrico", "Pantalla táctil", "Pin de carga", "Botón de encendido", "Mantenimiento menor"]
        for item in fallas_ops:
            self.list_fallas.insert(tk.END, item)
        self.list_fallas.grid(row=2, column=1, pady=5)

        tk.Label(self.root, text="Estado de Garantía:").grid(row=3, column=0, sticky="w", padx=10)
        self.var_garantia = tk.StringVar(value="Vencida")
        combo_garantia = ttk.Combobox(self.root, textvariable=self.var_garantia)
        combo_garantia['values'] = ("Vigente", "Vencida", "Invalidada")
        combo_garantia.grid(row=3, column=1, pady=5)

        tk.Label(self.root, text="Informe Técnico (Libre):").grid(row=4, column=0, sticky="w", padx=10)
        self.txt_informe = tk.Text(self.root, height=4, width=30)
        self.txt_informe.grid(row=4, column=1, pady=5, padx=10)

        btn_generar = tk.Button(self.root, text="Generar Documento Word", command=self.generar_word, bg="green", fg="white")
        btn_generar.grid(row=5, columnspan=2, pady=20)

    def generar_word(self):
        doc = Document()
        
        # Encabezado
        doc.add_paragraph("Estimado/a cliente:")
        doc.add_paragraph(f"En respuesta a su solicitud de servicio (ticket #{self.ent_ticket.get()}), "
                          f"hemos realizado una evaluación exhaustiva de su equipo con serial {self.ent_serial.get()}.")

        indices = self.list_fallas.curselection()
        fallas_seleccionadas = [self.list_fallas.get(i) for i in indices]
        texto_fallas = ", ".join(fallas_seleccionadas)
        doc.add_paragraph(f"Hemos determinado que presenta falla con el {texto_fallas}.")

        # Garantía
        if self.var_garantia.get() == "Vencida":
            doc.add_paragraph("Se le recuerda que su equipo ya no cumple con los lapsos de tiempo establecidos para el cumplimiento de garantía.")
        elif self.var_garantia.get() == "Invalidada":
            doc.add_paragraph("Se le informa que su equipo no cumple con las cláusulas establecidas para la ejecución de la garantía.")

        # Propuesta de solución (Texto resaltado amarillo 1)
        doc.add_heading('Propuesta de solución:', level=2)
        p_propuesta = doc.add_paragraph()
        run = p_propuesta.add_run(f"Para restablecer el funcionamiento óptimo de su equipo, recomendamos el reemplazo del {texto_fallas}.")
        run.bold = True
        doc.add_paragraph("Contamos con la pieza original para su modelo BIO500 disponible en nuestro inventario. Para ejecutar este procedimiento, a continuación, dejamos un precio aproximado de esta reparación:")

        # Tabla
        table = doc.add_table(rows=1, cols=3)
        table.style = 'Table Grid'
        hdr_cells = table.rows[0].cells
        hdr_cells[0].text = 'Modelo'
        hdr_cells[1].text = 'Articulo'
        hdr_cells[2].text = 'Costo referencial ($)'

        # Fila de mano de obra/servicio
        row_svc = table.add_row().cells
        row_svc[0].text = "Bio500"
        row_svc[1].text = "Reemplazo de partes y piezas de dispositivos biométricos"
        row_svc[2].text = "10"

        for falla in fallas_seleccionadas:
            row_cells = table.add_row().cells
            row_cells[0].text = "Bio500"
            row_cells[1].text = f"{falla} Bio500"
            row_cells[2].text = str(self.precios.get(falla, 0))

        # Información Administrativa (Texto resaltado amarillo 2)
        doc.add_paragraph("\nCon base en la información hasta este punto suministrada:")
        doc.add_paragraph("Nuestro departamento de administración se pondrá en contacto con usted a la brevedad, para enviarle una cotización formal detallada, la cual incluirá:")
        
        doc.add_paragraph(f"Costo del repuesto original: {texto_fallas} compatible con su modelo BIO500.", style='List Bullet')
        doc.add_paragraph("Gastos adicionales (si aplican): Cualquier otro costo asociado a la reparación, como componentes adicionales o servicios especializados.", style='List Bullet')
        
        doc.add_paragraph("Si decide no continuar con la reparación, le agradeceríamos que nos lo informara por este medio para proporcionarle el procedimiento de retiro de su equipo.")
        doc.add_paragraph("Le recordamos que para la facturación es indispensable que nos facilite su Rif.")

        # Diagnóstico e Informe
        doc.add_heading('Diagnóstico:', level=2)
        doc.add_paragraph(f"Falla diagnosticada: {texto_fallas}")
        doc.add_heading('Informe técnico:', level=2)
        doc.add_paragraph(self.txt_informe.get("1.0", tk.END))

        doc.add_paragraph("Agradecemos su comprensión.")
        doc.add_paragraph("Reciba nuestros saludos.\nEquipo de Soporte técnico\nBiopago C.A.")

        filename = f"Diagnostico_{self.ent_ticket.get()}.docx"
        doc.save(filename)
        messagebox.showinfo("Éxito", f"Documento {filename} generado correctamente.")

if __name__ == "__main__":
    root = tk.Tk()
    app = GeneradorDiagnostico(root)
    root.mainloop()