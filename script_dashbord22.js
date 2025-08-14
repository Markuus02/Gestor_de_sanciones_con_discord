function toggleUserDetails() {
    const info = document.getElementById("userInfo");
    info.style.display = (info.style.display === "block") ? "none" : "block";
}
// --- Botón cancelar para resetear formulario y ocultar sección ---
// Si existe el botón cancelar, agrega evento para resetear el formulario y ocultar su sección al hacer click.
const cancelarBtn = document.querySelector('.cancelar');
if (cancelarBtn) {
  cancelarBtn.addEventListener('click', function () {
    const form = document.getElementById('formSancion');
    if (form) form.reset();
    const formSection = document.getElementById('formSection');
    if (formSection) formSection.style.display = 'none';
  });
}

// --- Función para limpiar campos específicos del formulario ---
// Limpia los campos indicados salvo los que se pasan como excepción.
function limpiarCampos(except = []) {
  const campos = ['nombre_usuario', 'discord_usuario_id', 'hexa_usuario'];
  campos.forEach(id => {
    if (!except.includes(id)) {
      const el = document.getElementById(id);
      if (el) el.value = '';
    }
  });
}

// --- Buscar usuario manualmente al hacer click en botón ---
// Valida que haya algún dato para buscar y realiza una petición fetch para obtener datos del usuario.
// Actualiza los campos del formulario si encuentra información o muestra mensajes según el resultado.
const buscarUsuarioBtn = document.getElementById('buscarUsuarioBtn');
if (buscarUsuarioBtn) {
  buscarUsuarioBtn.addEventListener('click', function () {
    const discordId = document.getElementById('discord_usuario_id').value.trim();
    const nombre = document.getElementById('nombre_usuario').value.trim();
    const hexa = document.getElementById('hexa_usuario').value.trim();

    if (!discordId && !nombre && !hexa) {
      showToast("Introduce un Discord ID, nombre de usuario o HEXA.");
      return;
    }

    let queryParam = '';
    if (nombre) queryParam = `nombre_usuario=${encodeURIComponent(nombre)}`;
    else if (discordId) queryParam = `discord_usuario_id=${encodeURIComponent(discordId)}`;
    else if (hexa) queryParam = `hexa_usuario=${encodeURIComponent(hexa)}`;

    fetch(`buscar_usuario.php?${queryParam}`)
      .then(response => {
        if (!response.ok) throw new Error("Respuesta de red no OK");
        return response.json();
      })
      .then(data => {
        if (data && (data.nombre_usuario || data.discord_usuario_id || data.hexa_usuario)) {
          if (data.nombre_usuario) document.getElementById('nombre_usuario').value = data.nombre_usuario;
          if (data.discord_usuario_id) document.getElementById('discord_usuario_id').value = data.discord_usuario_id;
          if (data.hexa_usuario) document.getElementById('hexa_usuario').value = data.hexa_usuario;
          showToast("Este usuario ya tiene sanciones registradas.");
        } else {
          showToast("Este usuario aún no ha sido sancionado.");
        }
      })
      .catch(() => {
        showToast("Ocurrió un error al buscar el usuario.");
      });
  });
}

// --- Autocompletar usuario al salir del campo (blur) ---
// Al perder foco en los inputs indicados, realiza búsqueda para autocompletar datos del usuario.
['discord_usuario_id', 'nombre_usuario', 'hexa_usuario'].forEach(id => {
  const input = document.getElementById(id);
  if (input) {
    input.addEventListener('blur', function () {
      const valor = this.value.trim();
      if (valor) autocompletarUsuario(id, valor);
    });
  }
});

function autocompletarUsuario(param, valor) {
  fetch(`buscar_usuario.php?${param}=${encodeURIComponent(valor)}`)
    .then(response => {
      if (!response.ok) throw new Error(`Error al buscar por ${param}`);
      return response.json();
    })
    .then(data => {
      if (data && (data.nombre_usuario || data.discord_usuario_id || data.hexa_usuario)) {
        if (data.nombre_usuario) document.getElementById('nombre_usuario').value = data.nombre_usuario;
        if (data.discord_usuario_id) document.getElementById('discord_usuario_id').value = data.discord_usuario_id;
        if (data.hexa_usuario) document.getElementById('hexa_usuario').value = data.hexa_usuario;
        showToast("Este usuario ya tiene sanciones registradas.");
      } else {
        showToast("Este usuario aún no ha sido sancionado.");
      }
    });
}

// --- Función para mostrar mensajes flotantes (toast) ---
// Muestra mensajes breves en pantalla con animación de entrada y salida automática.
function showToast(message, duration = 3000) {
  const toast = document.getElementById("toast");
  if (!toast) return;
  toast.textContent = message;
  toast.style.visibility = "visible";
  toast.style.opacity = "1";

  setTimeout(() => {
    toast.style.opacity = "0";
    setTimeout(() => {
      toast.style.visibility = "hidden";
    }, 300);
  }, duration);
}

// --- Envío de formulario de búsqueda de sanciones ---
// Evita que el formulario recargue la página, toma los datos y realiza petición POST para obtener sanciones.
// Muestra en pantalla los resultados, resumen de tipos de sanciones y detalles expandibles para cada sanción.
document.getElementById('formBuscarUsuario').addEventListener('submit', function (e) {
  e.preventDefault();

  const datos = {
    nombre_usuario: document.getElementById('buscador_nombre_usuario').value.trim(),
    discord_usuario_id: document.getElementById('buscador_discord_usuario_id').value.trim(),
    hexa_usuario: document.getElementById('buscador_hexa_usuario').value.trim()
  };

  if (!datos.nombre_usuario && !datos.discord_usuario_id && !datos.hexa_usuario) {
    showToast("Introduce al menos un dato para buscar.");
    return;
  }

  fetch('buscar_sanciones.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(datos)
  })
  .then(res => res.json())
  .then(respuesta => {
    const sanciones = respuesta.sanciones || [];
    const puedeEditar = respuesta.puede_editar || false;

    const contenedor = document.getElementById('resultadoSanciones');
    contenedor.innerHTML = '';

    if (sanciones.length === 0) {
      contenedor.innerHTML = '<p>No se encontraron sanciones.</p>';
      return;
    }

    const conteoTipos = { WARN: 0, STRIKE: 0, BAN: 0, PERMABAN: 0 };
    sanciones.forEach(s => {
      const tipo = s.tipo_sancion?.toUpperCase();
      if (conteoTipos.hasOwnProperty(tipo)) conteoTipos[tipo]++;
    });

    const primer = sanciones[0];
    const infoDiv = document.createElement('div');
    infoDiv.className = 'info-jugador';
    infoDiv.innerHTML = `
      <h2 style="color: white;">👤 Información del Usuario</h2>
      <p><strong style="font-size: 18px;">Nombre:</strong> <span style="font-style: italic">${primer.nombre_usuario || 'N/D'}</span> 
         <strong style="margin-left: 100px; font-size: 18px;">Discord ID:</strong> <span style="font-style: italic">${primer.discord_usuario_id || 'N/D'}</span> 
         <strong style="margin-left: 100px; font-size: 18px;">HEXA:</strong> <span style="font-style: italic">${primer.hexa_usuario || 'N/D'}</span></p>
      <p><strong>📌 Total de Sanciones:</strong> ${sanciones.length}</p>
      <p>
        <span style="color: white; background: green; border: #ccc solid 3px; padding: 4px; border-radius: 8px;"><strong>WARN:</strong> ${conteoTipos.WARN}</span> 
        <span style="color: white; background: orange; border: #ccc solid 3px; padding: 4px; border-radius: 8px;"><strong>STRIKE:</strong> ${conteoTipos.STRIKE}</span> 
        <span style="color: white; background: red; border: #ccc solid 3px; padding: 4px; border-radius: 8px;"><strong>BAN:</strong> ${conteoTipos.BAN}</span> 
        <span style="color: white; border: #ccc solid 3px; padding: 4px; border-radius: 8px;"><strong>PERMABAN:</strong> ${conteoTipos.PERMABAN}</span>
      </p>
      <hr/>
    `;
    contenedor.appendChild(infoDiv);

    sanciones.forEach((sancion, idx) => {
      const div = document.createElement('div');
      div.className = 'sancion-card';
      div.innerHTML = `
        <div class="card-header">
          <span class="badge ${sancion.tipo_sancion.toLowerCase()}">${sancion.tipo_sancion}</span>
          <h3>${sancion.nombre_usuario}</h3>
          <p class="id"><strong style="color: white; font-weight: bold;">ID Discord: </strong> ${sancion.discord_usuario_id}</p>
        </div>
        <div class="card-body">
          <p><strong>🎮 Hex:</strong> ${sancion.hexa_usuario || 'N/D'}</p>
          <p><strong>📅 Inicio:</strong> ${sancion.inicio_ban || 'N/D'} - <strong>Fin:</strong> ${sancion.fin_ban || 'N/D'}</p>
          <p><strong>📝 Motivo:</strong> ${sancion.motivo_ban || 'Sin motivo registrado'}</p>
          <p><strong>👮 Staff:</strong> ${sancion.nombre_staff || 'N/D'} (${sancion.staff_rango || 'N/D'})</p>
        </div>
        <div class="sancion-detalle" id="detalle-${idx}" style="display:none;"></div>
      `;

      div.addEventListener('click', (e) => {
        if (e.target.closest('button, input, textarea, select, label')) return;

        const detalleDiv = document.getElementById(`detalle-${idx}`);
        const isVisible = detalleDiv.style.display === 'block';
        document.querySelectorAll('.sancion-detalle').forEach(d => d.style.display = 'none');
        if (isVisible) {
          detalleDiv.style.display = 'none';
          return;
        }

        detalleDiv.style.display = 'block';
        detalleDiv.innerHTML = `
          <fieldset>
            <legend>Datos del Usuario</legend>
            <div class="grid-3">
              <div class="form-group"><label>🧍 Nombre</label><input type="text" value="${sancion.nombre_usuario}" readonly></div>
              <div class="form-group"><label>🆔 Discord</label><input type="text" value="${sancion.discord_usuario_id}" readonly></div>
              <div class="form-group"><label>🎮 Hexa Steam</label><input type="text" value="${sancion.hexa_usuario}" readonly></div>
            </div>
          </fieldset>
          <fieldset>
            <legend>Datos del Staff</legend>
            <div class="grid-4">
              <div class="form-group"><label>🧑‍💼 Nombre</label><input type="text" value="${sancion.nombre_staff}" readonly></div>
              <div class="form-group"><label>💼 Rango</label><input type="text" value="${sancion.staff_rango}" readonly></div>
              <div class="form-group"><label>🆔 Discord ID</label><input type="text" value="${sancion.discord_id_staff}" readonly></div>
              <div class="form-group"><label>🤝 Compañeros Staff</label><input type="text" name="companero_staff" value="${sancion.companero_staff || ''}" readonly></div>
            </div>
          </fieldset>
          <fieldset>
            <legend>📅 Datos de la Sanción</legend>
            <div class="grid-3">
              <div class="form-group"><label>⚠️ Tipo</label><input type="text" value="${sancion.tipo_sancion}" readonly></div>
              <div class="form-group"><label>📅 Inicio</label><input type="text" value="${sancion.inicio_ban}" readonly></div>
              <div class="form-group"><label>📅 Fin</label><input type="text" name="fecha_fin" class="editable" value="${sancion.fin_ban}" readonly></div>
            </div>
          </fieldset>
          <fieldset>
            <legend>📝 Motivo de Sanción</legend>
            <textarea name="motivo" class="editable" readonly>${sancion.motivo_ban || ''}</textarea>
          </fieldset>
          <fieldset>
            <legend>📷 Pruebas / Notas 👀</legend>
            <textarea name="pruebas" class="editable" readonly>${sancion.pruebas_ban || ''}</textarea>
          </fieldset>
          </fieldset>
              <fieldset>
                <legend>📂 Pruebas Subidas</legend>
                <div class="form-group">
                  <label>📎 Archivos adjuntos</label>
                  <div class="adjuntos-grid" style="display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    ${(() => {
                      try {
                        return (JSON.parse(sancion.archivos_adjuntos || '[]')).map(file => {
                          const isImage = /\.(jpg|jpeg|png|gif)$/i.test(file);
                          const isVideo = /\.(mp4|webm|ogg)$/i.test(file);
                          if (isImage) {
                            return `<img src="${file}" alt="Imagen" style="max-width:100%; max-height:180px; object-fit:cover; border-radius:6px; box-shadow:0 0 5px #ccc; cursor:pointer;" onclick="abrirLightbox('${file}')">`;
                          } else if (isVideo) {
                            return `<video controls style="max-width:100%; max-height:180px; border-radius:6px; box-shadow:0 0 5px #ccc;"><source src="${file}"></video>`;
                          } else {
                            return `<a href="${file}" target="_blank">📎 Ver archivo</a>`;
                          }
                        }).join('');
                      } catch (e) {
                        return '<p style="color:red;">Error al cargar los archivos</p>';
                      }
                    })()}
                  </div>
                </div>
              </fieldset>
          <div class="botones-accion">
            <button class="btn-editar-sancion" data-id="${sancion.id}" style="background-color: #2e8b2d; font-size: 19px; color: white; border: none; border-radius: 6px; cursor: pointer; width: 190px; height: 60px; transition: background-color 0.3s ease; margin-right: 50px;">✏️ Editar sanción</button>
            <button class="btn-borrar-sancion" data-id="${sancion.id}" style="background-color: #c72c41; font-size: 19px; color: white; border: none; border-radius: 6px; cursor: pointer; width: 190px; height: 60px; transition: background-color 0.3s ease;">🗑️ Eliminar sanción</button>
          </div>
        `;

        const btnEditar = detalleDiv.querySelector('.btn-editar-sancion');
        const inputs = detalleDiv.querySelectorAll('input.editable, textarea.editable');

        btnEditar.addEventListener('click', () => {
  const estaEditando = btnEditar.dataset.editando === 'true';
  const camposEditables = detalleDiv.querySelectorAll('input.editable, textarea.editable');

  if (estaEditando) {
    Swal.fire({
        title: '¿Guardar cambios en esta sanción?',
        text: 'Se actualizarán los datos modificados.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, guardar',
        cancelButtonText: 'Cancelar',
        background: 'linear-gradient(135deg, #f9a825, #fdd835)',
        color: '#000',
        confirmButtonColor: '#f57f17',
        cancelButtonColor: '#795548'
    }).then(result => {
      if (!result.isConfirmed) {
        camposEditables.forEach(input => {
          input.value = input.dataset.original;
          input.readOnly = true;
        });
        btnEditar.textContent = '✏️ Editar sanción';
        btnEditar.dataset.editando = 'false';
        return;
      }

      const formData = new FormData();
      formData.append('id', btnEditar.dataset.id);

      camposEditables.forEach(input => {
        if (input.name) {
          formData.append(input.name, input.value.trim());
        }
      });

      formData.append('pruebas_actuales', '[]');
      formData.append('pruebas_eliminadas', '[]');

      fetch('editar_sancion.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showSwalAlert({ icon: 'success', title: '✅ Cambios guardados' });
          camposEditables.forEach(input => input.readOnly = true);
          btnEditar.textContent = '✏️ Editar sanción';
          btnEditar.dataset.editando = 'false';
        } else {
          showSwalAlert({ icon: 'error', title: '❌ Error', text: data.message });
          camposEditables.forEach(input => {
            input.value = input.dataset.original;
            input.readOnly = true;
          });
          btnEditar.textContent = '✏️ Editar sanción';
          btnEditar.dataset.editando = 'false';
        }
      })
      .catch(err => {
        console.error('Error guardando sanción:', err);
        showSwalAlert({ icon: 'error', title: '❌ Error al guardar los cambios' });
        camposEditables.forEach(input => {
          input.value = input.dataset.original;
          input.readOnly = true;
        });
        btnEditar.textContent = '✏️ Editar sanción';
        btnEditar.dataset.editando = 'false';
      });
    });
      } else {
        camposEditables.forEach(input => {
          input.dataset.original = input.value;
          input.readOnly = false;
        });
        btnEditar.textContent = '💾 Guardar cambios';
        btnEditar.dataset.editando = 'true';
      }
    });
      });

      contenedor.appendChild(div);
    });
  })
  .catch(error => {
    console.error('Error al buscar sanciones:', error);
    showToast('Error al buscar sanciones. Inténtalo de nuevo.');
  });
});



// LIGHTBOX SIMPLE (opcional)
// Crea un modal sencillo para mostrar una imagen en pantalla completa con fondo oscuro.
// Al hacer click en cualquier parte del modal, se cierra y se elimina del DOM.
function abrirLightbox(src) {
  const modal = document.createElement('div');
  modal.style.cssText = `
    position:fixed; top:0; left:0; width:100%; height:100%;
    background:rgba(0,0,0,0.8); display:flex; align-items:center; justify-content:center;
    z-index:99999;
  `;
  modal.innerHTML = `<img src="${src}" style="max-width:90%; max-height:90%; border:8px solid #fff; border-radius:12px;">`;
  modal.addEventListener('click', () => document.body.removeChild(modal));
  document.body.appendChild(modal);
}
// Obtiene la fecha y hora actual en formato compatible con input type="datetime-local".
// Devuelve un string tipo "YYYY-MM-DDTHH:mm" para asignar como valor inicial.
function getCurrentDateTimeLocal() {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  const hour = String(now.getHours()).padStart(2, '0');
  const minutes = String(now.getMinutes()).padStart(2, '0');
  return `${year}-${month}-${day}T${hour}:${minutes}`;
}

// Asigna el valor inicial del input datetime con la fecha/hora actual formateada.
const datetimeInput = document.getElementById('datetimeInput');
if (datetimeInput) {
  datetimeInput.value = getCurrentDateTimeLocal();
}


// Listener global para clicks en botones con clase 'btn-borrar-sancion' y botones para cerrar detalles.
// - Para borrar sanción: pide confirmación, envía petición POST a PHP, elimina visualmente la sanción y muestra mensaje.
// - Para cerrar detalle: oculta la sección de detalles correspondiente.
document.addEventListener('click', async function (e) {
    if (e.target.classList.contains('btn-borrar-sancion')) {
      const id = e.target.getAttribute('data-id');
      if (!id) {
        showSwalAlert({ icon: 'error', title: '❌ ID faltante', text: 'No se encontró el ID de la sanción.' });
        return;
      }

      Swal.fire({
        title: '¿Eliminar sanción?',
        text: 'Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        background: 'linear-gradient(135deg, #b71c1c, #e53935)',
        color: '#fff',
        confirmButtonColor: '#c62828',
        cancelButtonColor: '#616161'
      }).then(async (result) => {
        if (!result.isConfirmed) return;

        try {
          const formData = new FormData();
          formData.append('id', id);

          const response = await fetch('eliminar_sancion.php', {
            method: 'POST',
            body: formData,
          });

          const data = await response.json();
          if (!response.ok || !data.success) {
            showSwalAlert({ icon: 'error', title: '❌ Error', text: data.message || 'No se pudo eliminar la sanción' });
            return;
          }

          const contenedorSancion = e.target.closest('.sancion-card');
          if (contenedorSancion) contenedorSancion.remove();
          showToast(data.message || "Sanción eliminada correctamente.");
        } catch (error) {
          showSwalAlert({ icon: 'error', title: '❌ Error', text: 'Error al conectar con el servidor o respuesta inválida.' });
          console.error(error);
        }
      });
    }

  // Botón cerrar detalle
  if (e.target.classList.contains('cerrar-detalle-btn')) {
    const index = e.target.getAttribute('data-index');
    const detalleDiv = document.getElementById(`detalle-${index}`);
    if (detalleDiv) {
      detalleDiv.style.display = 'none';
    }
  }
});

// Calcula y asigna la fecha de fin de sanción sumando días ingresados al valor de inicio.
// El usuario introduce el número de días en un prompt, y se valida antes de actualizar el input 'fechaFin'.
const btnDias = document.getElementById('dia_sanciones');
btnDias?.addEventListener('click', () => {
  const inicioInput = document.getElementById('datetimeInput');
  const finInput = document.getElementById('fechaFin');

  if (!inicioInput.value) {
    showSwalAlert({ icon: 'warning', title: 'Selecciona una fecha', text: 'Primero selecciona la fecha y hora de inicio.' });
    return;
  }

  Swal.fire({
  title: '¿Cuántos días dura la sanción?',
  input: 'number',
  inputAttributes: { min: 1 },
  showCancelButton: true,
  confirmButtonText: 'Aceptar',
  cancelButtonText: 'Cancelar',
  background: 'linear-gradient(135deg, #00838f, #00acc1)',
  color: '#fff',
  confirmButtonColor: '#00796b',
  cancelButtonColor: '#455a64',
  inputValidator: (value) => {
    if (!value || isNaN(value) || value <= 0) return 'Introduce un número válido de días';
  }
  }).then(result => {
    if (!result.isConfirmed) return;

    const diasSancion = parseInt(result.value);
    const inicioDateTime = new Date(inicioInput.value);
    const fechaFin = new Date(inicioDateTime);
    fechaFin.setDate(fechaFin.getDate() + diasSancion);
    finInput.value = formatDateTimeToLocal(fechaFin);
  });
});

// Formatea un objeto Date a string compatible con input type="datetime-local" (YYYY-MM-DDTHH:mm).
function formatDateTimeToLocal(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  const h = String(date.getHours()).padStart(2, '0');
  const min = String(date.getMinutes()).padStart(2, '0');
  return `${y}-${m}-${d}T${h}:${min}`;
}

// Muestra solo la sección indicada por id y oculta todas las demás.
// Además, cambia las clases del header para adaptar el diseño visual cuando se muestra un formulario.
function toggleSection(sectionId) {
  document.querySelectorAll('.section').forEach(section => {
    section.style.display = 'none';
  });

  const selected = document.getElementById(sectionId);
  if (selected) {
    selected.style.display = 'block';

    const header = document.getElementById('mainHeader');
    header.classList.remove('layout-inicio');
    header.classList.add('layout-formulario');
  }
}
// --- Cambiar color de fondo y texto del select según opción seleccionada ---
// Asigna un evento al select para cambiar su estilo según la opción seleccionada.
const select = document.getElementById('tipo_sancion');

select.addEventListener('change', () => {
  const selectedOption = select.options[select.selectedIndex];
  select.style.backgroundColor = selectedOption.style.backgroundColor;
  select.style.color = selectedOption.style.color;
});

// --- Añadir texto predefinido al textarea de motivo de sanción ---
document.addEventListener('DOMContentLoaded', () => {
  const botones = document.querySelectorAll('.boton_sanciones_prehechas');
  const textareaMotivo = document.getElementById('motivo_ban');

  if (!textareaMotivo) {
    console.error('No se encontró el textarea motivo_ban');
    return;
  }

  // Reemplazar botones con clones para limpiar listeners anteriores
  botones.forEach(boton => boton.replaceWith(boton.cloneNode(true)));

  const botonesLimpios = document.querySelectorAll('.boton_sanciones_prehechas');

  botonesLimpios.forEach(boton => {
    boton.addEventListener('click', () => {
      const textoParaAñadir = boton.getAttribute('data-text');
      if (!textoParaAñadir) return;

      // Elimina saltos de línea del contenido actual
      let contenidoActual = textareaMotivo.value.replace(/[\r\n]+/g, ' ').trim();

      // Agrega espacio si ya hay contenido
      if (contenidoActual !== '') {
        contenidoActual += ' ';
      }

      // Agrega el nuevo texto
      textareaMotivo.value = contenidoActual + textoParaAñadir;

      textareaMotivo.focus();
      textareaMotivo.selectionStart = textareaMotivo.selectionEnd = textareaMotivo.value.length;
    });
  });
});



  
document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("formSancion");
  if (!form) return; // 🚫 No ejecutar nada si no hay formulario

  async function enviarADiscord(data, modo = "manual") {
  try {
    const res = await fetch("enviar_webhook.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ data, modo })
    });

    const respuesta = await res.text(); // leer como texto por si no es JSON válido

    if (!res.ok) {
      console.error("❌ Error al enviar webhook:", respuesta);
      showSwalAlert({ icon: 'error', title: '❌ Error al enviar webhook', text: respuesta });
    } else {
      console.log("✅ Webhook enviado correctamente:", respuesta);
      showSwalAlert({ icon: 'success', title: '✅ Webhook enviado correctamente', text: 'La sanción se ha enviado correctamente a Discord.' });
    }
    } catch (error) {
      console.error("❌ Error de red:", error);
      showSwalAlert({ icon: 'error', title: '❌ Error de red', text: error.message });
  }
}


function obtenerDatosFormulario() {
  const tipo = document.getElementById("tipo_sancion");
  const motivo = document.getElementById("motivo_ban");
  const notas = document.querySelector("[name='pruebas_ban']");
  const usuario = document.getElementById("nombre_usuario");
  const staff = document.querySelector("[name='nombre_staff']");
  const staffId = document.querySelector("[name='discord_id_staff']");

  let camposFaltantes = [];

  if (!tipo || tipo.value === "none") camposFaltantes.push("tipo_sancion");
  if (!motivo || motivo.value.trim() === "") camposFaltantes.push("motivo_ban");
  if (!notas || notas.value.trim() === "") camposFaltantes.push("pruebas_ban");
  if (!usuario || usuario.value.trim() === "") camposFaltantes.push("nombre_usuario");
  if (!staff || staff.value.trim() === "") camposFaltantes.push("nombre_staff");
  if (!staffId || staffId.value.trim() === "") camposFaltantes.push("discord_id_staff");

  if (camposFaltantes.length > 0) {
    console.error("❌ Faltan campos: ", camposFaltantes.join(", "));
    showSwalAlert({
      icon: 'error',
      title: '❌ Faltan campos obligatorios',
      html: `<ul style="text-align: left;">${camposFaltantes.map(f => `<li>${f}</li>`).join('')}</ul>`,
      confirmButtonColor: '#ab47bc',
      background: '#1e1e2f',
      color: '#fff'
    });
    return null;
  }

  return {
    tipo: tipo.value,
    motivo: motivo.value,
    notas: notas.value,
    usuario: usuario.value,
    discord_usuario_id: document.getElementById("discord_usuario_id")?.value ?? "Desconocido",
    hexa_usuario: document.getElementById("hexa_usuario")?.value ?? "N/D",
    staff: staff.value,
    staff_id: staffId.value,
    staff_rango: document.querySelector("[name='staff_rango']")?.value ?? "Sin rango",
    companero_staff: document.getElementById("discord_staff_compañero")?.value ?? "", // ✅ añadido
    inicio: document.getElementById("datetimeInput")?.value ?? "No indicado",
    fin: document.getElementById("fechaFin")?.value ?? "Indefinido"
  };
}


form.addEventListener("submit", async function (e) {
  e.preventDefault();

  // ✅ Validaciones antes de cualquier confirmación
  const camposObligatorios = [
    'nombre_usuario', 'discord_usuario_id', 'hexa_usuario',
    'tipo_sancion', 'datetimeInput', 'fechaFin', 'motivo_ban'
  ];
  let camposFaltantes = [];

  camposObligatorios.forEach(id => {
    const el = document.getElementById(id);
    if (!el || !el.value || (el.tagName === 'SELECT' && el.value === 'none')) {
      camposFaltantes.push(id);
      if (el) el.classList.add('campo-error');
    } else {
      el.classList.remove('campo-error');
    }
  });

  const textarea = document.querySelector('textarea[name="pruebas_ban"]');
  if (!textarea || textarea.value.trim() === '') {
    camposFaltantes.push('pruebas_ban');
    textarea?.classList.add('campo-error');
  } else {
    textarea.classList.remove('campo-error');
  }

  const archivos = document.querySelector('input[name="archivos_prueba[]"]');
  if (!archivos || archivos.files.length === 0) {
    camposFaltantes.push('archivos_prueba');
    archivos?.classList.add('campo-error');
  } else {
    archivos.classList.remove('campo-error');
  }

  if (camposFaltantes.length > 0) {
    showSwalAlert({ icon: 'warning', title: '⚠️ Campos faltantes', text: 'Completa todos los campos obligatorios.' });
    return;
  }

  // ✅ Confirmación tras validación
  const preConfirm = await Swal.fire({
  title: '¿Listo para enviar la sanción?',
  text: 'Verifica todos los datos antes de confirmar.',
  icon: 'info',
  confirmButtonText: 'Continuar',
  cancelButtonText: 'Cancelar',
  showCancelButton: true,
  background: 'linear-gradient(-45deg, #117c11, #0b3d0b, #1fd655, #0f0f0f)',
  color: '#fff',
  confirmButtonColor: '#007863',
  cancelButtonColor: '#dc3545',
  customClass: { popup: 'swal-animated-gradient' }
});

  if (!preConfirm.isConfirmed) return;


  const data = obtenerDatosFormulario();
  if (!data) return;

  try {
    await enviarADiscord(data, 'auto');

    const publicar = await Swal.fire({
  title: '¿Deseas publicar esta sanción en Discord?',
  icon: 'question',
  confirmButtonText: 'Sí, publicar',
  cancelButtonText: 'No, solo guardar',
  showCancelButton: true,
  background: 'linear-gradient(135deg, #2f80ed, #56ccf2)',
  color: '#fff',
  confirmButtonColor: '#1e88e5',
  cancelButtonColor: '#7b1fa2',
  customClass: { popup: 'swal-discord-gradient' }
});

    if (publicar.isConfirmed) {
      await enviarADiscord(data, 'manual');
    }
// 🔄 Enviar a PHP para guardar en MySQL usando FormData
const formData = new FormData();

formData.append('nombre_usuario', data.usuario);
formData.append('discord_usuario_id', data.discord_usuario_id);
formData.append('hexa_usuario', data.hexa_usuario);
formData.append('nombre_staff', data.staff);
formData.append('staff_rango', data.staff_rango);
formData.append('discord_id_staff', data.staff_id);
formData.append('companero_staff', data.companero_staff ?? '');
formData.append('tipo_sancion', data.tipo);
formData.append('inicio_ban', data.inicio);
formData.append('fin_ban', data.fin);
formData.append('motivo_ban', data.motivo);
formData.append('pruebas_ban', data.notas);

// 📎 Adjuntar archivos
const archivosInput = document.querySelector('input[name="archivos_prueba[]"]');
if (archivosInput && archivosInput.files.length > 0) {
  Array.from(archivosInput.files).forEach(file => {
    formData.append('archivos_prueba[]', file);
  });
}

try {
  const response = await fetch("guardar_sancion.php", {
    method: "POST",
    body: formData
  });

  const result = await response.json();

  if (!result.success) {
    console.error("❌ Error al guardar sanción:", result.message);
    showSwalAlert({
      icon: 'error',
      title: 'Error al guardar',
      text: result.message || 'No se pudo guardar la sanción.'
    });
    return;
  }

  console.log("✅ Sanción guardada en base de datos.");
} catch (error) {
  console.error("❌ Error de red al guardar:", error);
  showSwalAlert({
    icon: 'error',
    title: 'Error de red',
    text: 'No se pudo contactar al servidor.'
  });
  return;
}
window.location.href = "dashboard.php"; // <-- Añade esto
  } catch (error) {
    console.error('Error al enviar la sanción:', error);
    showSwalAlert({ icon: 'error', title: '❌ Error al enviar la sanción', text: 'Intenta de nuevo más tarde.' });
  }
});



  // Botón de auto-sanción
  const botonAuto = document.getElementById("btnAutoSancion");
  if (botonAuto) {
    botonAuto.addEventListener("click", async () => {
      const data = obtenerDatosFormulario();
      await enviarADiscord(data, "auto"); // ✅
    });
  }
});

// === Buscador de compañeros de staff ===
document.addEventListener("DOMContentLoaded", () => {
  const input = document.getElementById('staffSearch');
  const resultado = document.getElementById('resultado');
  const staffList = document.getElementById('staffList');
  const hiddenInput = document.getElementById('discord_staff_compañero');
  let compañerosSeleccionados = [];

  if (input && resultado && staffList && hiddenInput) {
    input.addEventListener('input', () => {
      const query = input.value.trim();
      
      if (query.length < 2) {
        resultado.style.display = 'none';
        return;
      }

      // Hacer la búsqueda
      fetch(`buscar_compañero.php?q=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
          resultado.innerHTML = '';
          
          if (!data.length) {
            resultado.style.display = 'none';
            return;
          }

          data.forEach(user => {
            const item = document.createElement('div');
            item.style.cssText = 'padding: 8px; display: flex; align-items: center; cursor: pointer; border-bottom: 1px solid #333; color: white;';
            
            item.innerHTML = `
              ${user.avatar ? `<img src="https://cdn.discordapp.com/avatars/${user.id}/${user.avatar}.png" style="width: 24px; height: 24px; border-radius: 50%; margin-right: 8px;">` : ''}
              <span>${user.username}</span>
            `;

            // Evento click para seleccionar
            item.addEventListener('click', () => {
              if (!compañerosSeleccionados.includes(user.username)) {
                compañerosSeleccionados.push(user.username);
                const valor = `${user.username} (${user.id})`;
                hiddenInput.value = compañerosSeleccionados.join(', ');
                
                // Actualizar lista visual
                const div = document.createElement('div');
                div.innerHTML = `
                  <span style="color: white;">${user.username}</span>
                  <button type="button" style="margin-left: 8px;">×</button>
                `;
                staffList.appendChild(div);
              }
              input.value = '';
              resultado.style.display = 'none';
            });

            resultado.appendChild(item);
          });

          resultado.style.display = 'block';
        })
        .catch(error => {
          console.error('Error:', error);
          resultado.style.display = 'none';
        });
    });

    // Eliminar compañeros
    staffList.addEventListener('click', (e) => {
      if (e.target.tagName === 'BUTTON') { // Corregido: faltaba paréntesis
        const div = e.target.closest('div');
        if (div) {
          const username = div.querySelector('span').textContent;
          compañerosSeleccionados = compañerosSeleccionados.filter(n => n !== username);
          hiddenInput.value = compañerosSeleccionados.join(', ');
          div.remove();
        }
      }
    });
  }
});
// Función para mostrar alertas SweetAlert2 sin duplicados
function showSwalAlert(options) {
  // Si ya hay un modal abierto, no mostrar otro
  if (Swal.isVisible()) return;
  Swal.fire(options);
}